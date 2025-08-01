<?php

namespace Tainacan_Inventarios;

// Evita acesso direto ao arquivo
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Restrict_Users_By_Team {

    use Singleton;

    private $team_metadatum_id_field = 'tainacan_inventarios_team_metadatum_id';

    protected function init() {

        // Lógica para adicionar a opção de metadado de equipe nas configurações do Tainacan
		add_action( 'admin_init', array( $this, 'settings_init' ) );

        // Lógica para adicionar a opção de restrição de acesso ao formulário de usuário
        add_action( 'tainacan-register-admin-hooks', array($this, 'register_admin_hooks') );
        
        // Lógica para salvar na entidade 'role' o campo extra com a opção de restrição de acesso para o perfil de usuário
        add_action( 'tainacan-api-role-prepare-for-response', array($this, 'set_role_to_restrict_access_items_create'), 10, 2 );

        // Lógica para restringir as permissões de edição para usuários a depender do item
        add_filter( 'user_has_cap', array($this, 'user_has_cap_filter'), 20, 4 );

        // Lógica para filtrar, inclusive na API os itens e coleções restringidos
        add_filter( 'tainacan-fetch-args', array($this, 'fetch_args'), 10, 2 );
    }

    /**
     * Adciona a opção de metadado de equipe nas configurações do Tainacan.
     */
    public function settings_init() {

        $user_metadata = [];
        $inventario_collection_id = Inventario_Post_Type::get_instance()->get_inventarios_collection_id();

        if ( $inventario_collection_id ) {
            $user_metadata = \tainacan_metadata()->fetch_by_collection(
                \tainacan_get_collection( array( 'collection_id' => $inventario_collection_id) ),
                [
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key'   => 'metadata_type',
                            'value' => 'Tainacan\Metadata_Types\User'
                        ]
                    ]
                ], 'OBJECT'
            );
        }
        $metadata_options = '';

        $metadata_options .= '<option value="">' . __( 'Selecione um metadado...', 'tainacan-inventarios' ) . '</option>';

        foreach( $user_metadata as $metadatum ) {
            $metadata_options .= '<option value="' . esc_attr( $metadatum->get_id() ) . '">' . esc_html( $metadatum->get_name() ) . '</option>';
        }

		\Tainacan\Settings::get_instance()->create_tainacan_setting( array(
			'id' => $this->team_metadatum_id_field,
			'title' => __( 'Metadado de Equipe', 'tainacan-inventarios' ),
			'section' => 'tainacan_settings_inventarios',
			'type' => 'string',
            'input_type' => 'select',
            'input_inner_html' => $metadata_options,
            'description' => __( 'Selecione o metadado que listará os integrantes da equipe de cada inventário. Isto impactará no acesso que alguns usuários terão aos itens de coleções relacionados ao inventário.', 'tainacan-inventarios' ),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
		) );
    }

    // Método auxiliar para facilitar a chamada ao metadado de equipe definido
    function get_team_metadatum_id() {
        return get_option('tainacan_option_' . $this->team_metadatum_id_field);
    }

    // Método auxiliar para facilitar a chamada dos perfis que terão o acesso restrito com base na equipe e relacionamentos
    public function get_restrictive_roles() {
        $roles = get_option('tainacan_inventarios_set_role_to_restrict_access', []);
        return $roles;
    }

    // public function get_collections_access_by_user() {
    //     $user = \wp_get_current_user();
    //     $roles_collections = get_option('tainacan_inventarios_collections_access_by_role', []);
    //     $collections_ids = [];
    //     $roles = $user->roles;

    //     foreach( $roles as $role ) {
    //         if ( isset($roles_collections[$role]) && is_array($roles_collections[$role]) ) {
    //             $collections_ids = array_merge($collections_ids, $roles_collections[$role]);
    //         }
    //     }
    //     return empty($collections_ids) ? false : $collections_ids;
    // }

    // Obtem os ids dos itens da coleção de inventário que o usuário atual tem acesso
    // baseado no metadado de equipe.
    public function get_current_user_allowed_inventarios_ids() {
        $restrictive_ids = array();

        $inventario_collection_id = Inventario_Post_Type::get_instance()->get_inventarios_collection_id();
        $team_metadatum_id = $this->get_team_metadatum_id();

        if ( !$inventario_collection_id || !$team_metadatum_id ) {
            return false;
        }

        $items = \tainacan_items()->fetch(
            array(
                'meta_query' => [
                    [
                        'key'   => $team_metadatum_id,
                        'value' => [ get_current_user_id() ],
                        'compare' => 'IN'
                    ]
                ],
                'status' => 'any'
            ),
            $inventario_collection_id,
            'OBJECT'
        );

        $restrictive_ids = array_map(function($item) { return $item->get_id(); }, $items);
        return $restrictive_ids;
    }

    public function get_allowed_users_ids($item) {
        $inventario_collection_id = Inventario_Post_Type::get_instance()->get_inventarios_collection_id();
        $team_metadatum_id = $this->get_team_metadatum_id();

        if ( !$inventario_collection_id || !$team_metadatum_id ) {
            return false;
        }

        $team_users = [];
        $item_metadata = $item->get_metadata();

        foreach ($item_metadata as $item_metadatum) {
            $metadatum = $item_metadatum->get_metadatum();
            
            if ( $metadatum->get_metadata_type() == 'Tainacan\\Metadata_Types\\Relationship' ) {
                $options = $metadatum->get_metadata_type_options();

                if ( 
                    isset($options['collection_id']) &&
                    $options['collection_id'] == $inventario_collection_id
                ) {
                    $inventario_items_ids = $item_metadatum->get_value();
                    $inventario_items_ids = is_array($inventario_items_ids) ? $inventario_items_ids: [ $inventario_items_ids ];

                    foreach($inventario_items_ids as $id) {
                        $inventario_team_users = get_post_meta( $id, $team_metadatum_id );

                        if ( !$inventario_team_users ) continue;

                        $inventario_team_users = is_array($inventario_team_users) ? $inventario_team_users: [ $inventario_team_users ];
                        $team_users = array_merge($team_users, $inventario_team_users);
                    }
                }
            }
        }
        return $team_users;
    }

    public function user_has_cap_filter( $allcaps, $caps, $args, $user ) {
        $exist_roles = !empty(array_intersect($this->get_restrictive_roles(), $user->roles));
        
        if ( $exist_roles && is_array($args) && count($args) >= 3 ) {
            $entity_id = $args[2];

            if ( is_numeric( $entity_id ) ) {
                $item = \tainacan_items()->fetch( (int) $entity_id );
                
                if ( $item instanceof \Tainacan\Entities\Item && $item->get_status() != 'auto-draft') {
                    $control_collections_ids = Control_Collections::get_instance()->get_control_collections_ids();
                    $col_id = $item->get_collection_id();

                    $allowed_users_ids = $this->get_allowed_users_ids($item);
                    //$collections_access_by_user = $this->get_collections_access_by_user();
                    
                    if ( 
                        $allowed_users_ids === false ||
                        in_array($col_id, $control_collections_ids)
                        //|| ($collections_access_by_user !== false && in_array($col_id, $collections_access_by_user))
                    ) {
                        return $allcaps;
                    }
                    $collection_id = $item->get_collection_id();

                    if ( !in_array($user->ID . '', $allowed_users_ids) ) {
                        $allcaps['read'] = false;
                        $allcaps["tnc_col_{$collection_id}_edit_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_edit_others_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_edit_published_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_read_private_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_publish_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_delete_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_delete_others_items"] = false;
                        $allcaps["tnc_col_{$collection_id}_delete_published_items"] = false;
                    }
                }
            }
        }
        
        return $allcaps;
    }

    /**
     * Filtra os argumentos para buscar itens com base nos papéis do usuário e nos metadados restritivos.
     */
    public function fetch_items_args($args, $user) {
        $exist_roles = !empty(array_intersect($this->get_restrictive_roles(), $user->roles));
        $post_type = $args['post_type'];
        
        // Se o usuário tem um papel restritivo e o tipo de post é um item de coleção...
        if (
            $exist_roles &&
            isset($post_type) &&
            count($post_type) == 1 && 
            \strpos($post_type[0], 'tnc_col_' ) === 0
        ) {
            $current_collection_id = preg_replace('/[a-z_]+(\d+)[a-z_]+?$/', '$1', $post_type[0] );
            $inventario_collection_id = Inventario_Post_Type::get_instance()->get_inventarios_collection_id();
            
            if ( !is_numeric($current_collection_id) || !$inventario_collection_id ) {
                return $args;
            }

            // Se estamos buscando itens da coleção do Inventário, restringimos pelo metadado da equipe
            if ( $current_collection_id == $inventario_collection_id ) {
                $team_metadatum_id = $this->get_team_metadatum_id();

                if ( $team_metadatum_id) {
                    if ( !isset($args['meta_query'] ) ) {
                        $args['meta_query'] = array();
                    }

                    $args['meta_query'][] = [
                        'key' => $team_metadatum_id,
                        'value' => [ $user->id ],
                        'compare' => 'IN'
                    ];
                }

            // Se estamos buscando itens de uma coleção que não é a do Inventário, mas que tem um 
            // relacionamento com a coleção do Inventário, restringimos pelo metadado de equipe
            // do item de inventário relacionado.
            } else {
                $current_collection = \tainacan_collections()->fetch($current_collection_id);
                $relationship_metadata = \tainacan_metadata()->fetch_by_collection($current_collection,
                    array(
                        'meta_query' => [
                            [
                                'key'   => 'metadata_type',
                                'value' => 'Tainacan\Metadata_Types\Relationship'
                            ],
                            [
                                'key' => '_option_collection_id',
                                'value' => $inventario_collection_id,
                            ]
                        ]
                    )
                );
                // Idealmente haverá apenas um metadado de relacionamento desta coleção com a
                // coleção do Inventário, mas vamos iterar...
                foreach ( $relationship_metadata as $relationship_metadatum ) {
                    
                    $inventario_items_ids = $this->get_current_user_allowed_inventarios_ids();
                    
                    if ( $inventario_items_ids === false ) {
                        continue;
                    }

                    if ( !isset($args['meta_query'] ) ) {
                        $args['meta_query'] = array();
                    }

                    $args['meta_query'][] = [
                        'key' => $relationship_metadatum->get_id(),
                        'value' => empty($inventario_items_ids)? [-1] : $inventario_items_ids,
                        'compare' => 'IN'
                    ];
                }
            }
        }

        return $args;
    }

    public function fetch_collections_args($args, $user) {
        if ( $args['posts_per_page'] == -1 ) {
            return $args;
        }

        $roles = $user->roles;
        $exist_restrictive_roles = !empty(array_intersect($this->get_restrictive_roles(), $roles));

        if ( $exist_restrictive_roles ) {
            $inventario_collection_id = Inventario_Post_Type::get_instance()->get_inventarios_collection_id();

            if ( !$inventario_collection_id ) {
                return $args;
            }

            $relationship_metadata = \tainacan_metadata()->fetch(
                array(
                    'meta_query' => [
                        [
                            'key'   => 'metadata_type',
                            'value' => 'Tainacan\Metadata_Types\Relationship'
                        ],
                        [
                            'key' => '_option_collection_id',
                            'value' => $inventario_collection_id,
                        ]
                    ]
                ), 'OBJECT'
            );
            if ( empty($relationship_metadata) ) {
                $args['post__in'] = [-1];
            } else {
                $collections_ids = array_map( function($relationship_metadatum) { return $relationship_metadatum->get_collection_id(); }, $relationship_metadata );
                $args['post__in'] = $collections_ids;
            }
        }
        
        // $col_ids = $this->get_collections_access_by_user();
        
        // if ( $col_ids !== false ) {
        //     if (  isset($args['post__in']) ) $col_ids = array_merge($col_ids, $args['post__in']);
        //     $args['post__in'] = $col_ids;
        // }

        if ( !$user->has_cap('manage_tainacan') ) {
            $control_collections_ids = Control_Collections::get_instance()->get_control_collections_ids();
            $args['post__not_in'] = $control_collections_ids;
        }

        return $args;
    }

    public function fetch_args($args, $type) {
        $user = \wp_get_current_user();
        
        if ( $type == 'items' ) {
            $args = $this->fetch_items_args($args, $user);
        } elseif ( $type == 'collections' ) {
            $args = $this->fetch_collections_args($args, $user);
        }

        return $args;
    }

    public function register_admin_hooks() {
        if ( function_exists( 'tainacan_register_admin_hook' ) ) {
            tainacan_register_admin_hook( 'role', [$this, 'set_role_to_restrict_access_items_form'], 'end-right' );
        }
    }

    public function set_role_to_restrict_access_items_form() {
        ob_start();
        ?>
            <div class="name-edition-box tainacan-set-role-to-restrict-access">
                <label for="set_role_to_restrict_access"><?php _e('Restringir edição dos itens baseando-se no metadado de equipe', 'tainacan-inventarios'); ?></label>
                <select name="set_role_to_restrict_access" id="set-user-to-restrict-access-select">
                    <option value="yes"><?php _e('Sim', 'tainacan-inventarios'); ?></option>
                    <option value="no"><?php _e('Não', 'tainacan-inventarios'); ?></option>
                </select>
                <p><span class="dashicons dashicons-info"></span>&nbsp;<?php _e('Com esta opção ativa, o usuário terá acesso restrito mesmo à coleções que pode editar. Se uma coleção tiver um metadado de relacionamento com a coleção de inventários, ele só poderá editar itens relacionados com inventários dos quais faça parte da equipe.', 'tainacan-inventarios'); ?></p>
            </div>
            <!-- <br>
            <div class="name-edition-box tainacan-collections_access_by_role" >
                <h2 style="margin-bottom: -1em; font-size: 0.875rem;"><?php _e('Conceder acesso também ao seguinte conjunto de coleções:', 'tainacan-inventarios'); ?></h2>
                <ul class="collections-container capabilities-list" style="justify-content: flex-start; 0 0.5em 0.5em;">
                    <?php foreach(\tainacan_collections()->fetch([], 'OBJECT') as $col): ?>
                        <li style="flex-basis: 400px; margin-right: unset;">
                            <span class="check-column">
                                <label for="<?php echo $col->get_id(); ?>" class="screen-reader-text">
                                    <?php echo $col->get_name(); ?>
                                </label>
                                <input type="checkbox" name="collections_access_by_role" id="<?php echo $col->get_id(); ?>" value="<?php echo $col->get_id(); ?>">
                            </span>
                            <span class="name column-name">
                                <?php echo $col->get_name(); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><span class="dashicons dashicons-info"></span>&nbsp;<?php _e('Se nenhuma coleção for marcada, todas as com direito de acesso serão vistas obedecendo seu status.', 'tainacan-inventarios'); ?></p>
            </div> -->
            
        <?php
        return ob_get_clean();
    }

    public function set_role_to_restrict_access_items_create($role, $request) {
        $slug = $role['slug'];
        $roles = get_option('tainacan_inventarios_set_role_to_restrict_access', []);
        //$roles_collections = get_option('tainacan_inventarios_collections_access_by_role', []);
        //$roles_collections = is_array($roles_collections) ? $roles_collections : [];

        if ( $request->get_method() != 'GET') {

            // if ( isset($request['collections_access_by_role']) ) {
            //     $update_col = $request['collections_access_by_role'];
            //     update_option('tainacan_inventarios_collections_access_by_role', array_merge($roles_collections, [ $slug => $update_col ] ) );
            //     $role['collections_access_by_role'] = $update_col;
            // } else {
            //     if ( isset($roles_collections[$slug]) ) unset($roles_collections[$slug]);
            //         update_option('tainacan_inventarios_collections_access_by_role', $roles_collections );
            // }

            if ( isset($request['set_role_to_restrict_access']) ) {

                if ($request['set_role_to_restrict_access'] == 'yes') {
                    update_option('tainacan_inventarios_set_role_to_restrict_access', array_merge($roles, [ $slug ] ) );
                    $role['set_role_to_restrict_access'] = 'yes';
                } else {
                    update_option('tainacan_inventarios_set_role_to_restrict_access', array_filter($roles, function($el) use ($slug) { return $el != $slug; } ) );
                    $role['set_role_to_restrict_access'] = 'no';
                }
            }

        } else {
            $set_role = in_array($slug, $roles);
            //$collections_role =  isset($roles_collections[$slug]) ? $roles_collections[$slug] : [];
            //$role['collections_access_by_role'] = $collections_role;
            $role['set_role_to_restrict_access'] = $set_role ? 'yes' : 'no';
        }

        return $role;
    }
}
