<?php

namespace Tainacan_Inventarios;

// Evita acesso direto ao arquivo
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Inventario_Post_Type {

    use Singleton;

    private $inventario_post_id_field = 'tainacan-inventarios-inventario-post-id';
    private $inventarios_collection_id_field = 'tainacan_inventarios_collection_id';

    protected function init() {

        // Lógica para adicionar a opção de coleção de inventários nas configurações do Tainacan
		add_action( 'admin_init', array( $this, 'settings_init' ) );

        // Lógica para registrar o tipo de post "inventários"
        add_action( 'init', array( $this, 'register_post_type' ) );

        // Lógica para adicionar a opção extra no formulário de item que vinculará o item a um inventário
        add_action( 'tainacan-register-admin-hooks', array( $this, 'register_hook' ) );
		add_action( 'tainacan-insert-tainacan-item', array( $this, 'save_data' ) );
		add_filter( 'tainacan-api-response-item-meta', array( $this, 'add_meta_to_response' ), 10, 2 );

        // Lógica para redirecionar o link do item para a página de inventário correspondente
        add_filter( 'the_content', array($this, 'custom_the_content'), 20, 1 );
    }

    /**
     * Adiciona opção de coleção de inventários nas configurações do Tainacan.
     */
    public function settings_init() {

		add_settings_section(
			'tainacan_settings_inventarios', // ID
			__( 'Tainacan Inventários', 'tainacan-inventarios' ), // Title
			array( $this, 'inventarios_section_description' ), // Callback
			'tainacan_settings'               		    // Page
		);
		
        $collections = \tainacan_collections()->fetch(array(), 'OBJECT');
        $collections_options = '';

        $collections_options .= '<option value="">' . __( 'Selecione uma coleção...', 'tainacan-inventarios' ) . '</option>';

        foreach( $collections as $collection ) {
            if ( !in_array( $collection->get_id(), Control_Collections::get_instance()->get_control_collections_ids() ) )
                $collections_options .= '<option value="' . esc_attr( $collection->get_id() ) . '">' . esc_html( $collection->get_name() ) . '</option>';
        }

		\Tainacan\Settings::get_instance()->create_tainacan_setting( array(
			'id' => $this->inventarios_collection_id_field,
			'title' => __( 'Coleção de Inventários', 'tainacan-inventarios' ),
			'section' => 'tainacan_settings_inventarios',
			'type' => 'string',
            'input_type' => 'select',
            'input_inner_html' => $collections_options,
            'description' => __( 'Selecione a coleção cujos itens serão inventários. Isto permitirá vincular seus itens com páginas do tipo inventário.', 'tainacan-inventarios' ),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
		) );

    }

    /**
     * Registers the inventarios post type.
     */
    public function register_post_type() {
        $args = array(
            'labels'             => array(
                'name'                  => _x('Inventários', 'Post type general name', 'tainacan-inventarios'),
                'singular_name'         => _x('Inventário', 'Post type singular name', 'tainacan-inventarios'),
                'menu_name'             => _x('Inventários', 'Admin Menu text', 'tainacan-inventarios'),
                'name_admin_bar'        => _x('Inventário', 'Add New on Toolbar', 'tainacan-inventarios'),
                'add_new'               => __('Adicionar Novo', 'tainacan-inventarios'),
                'add_new_item'          => __('Adicionar Novo Inventário', 'tainacan-inventarios'),
                'new_item'              => __('Novo Inventário', 'tainacan-inventarios'),
                'edit_item'             => __('Editar Inventário', 'tainacan-inventarios'),
                'view_item'             => __('Ver Inventário', 'tainacan-inventarios'),
                'all_items'             => __('Todos os Inventários', 'tainacan-inventarios'),
                'search_items'          => __('Pesquisar Inventários', 'tainacan-inventarios'),
                'parent_item_colon'     => __('Inventários pais:', 'tainacan-inventarios'),
                'not_found'             => __('Nenhum Inventário encontrado.', 'tainacan-inventarios'),
                'not_found_in_trash'    => __('Nenhum Inventário encontrado na lixeira.', 'tainacan-inventarios'),
                'featured_image'        => _x('Imagem de capa do Inventário', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'tainacan-inventarios'),
                'set_featured_image'    => _x('Configurar imagem de capa', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'tainacan-inventarios'),
                'remove_featured_image' => _x('Remover imagem de capa', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'tainacan-inventarios'),
                'use_featured_image'    => _x('Usar como imagem de capa', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'tainacan-inventarios'),
                'archives'              => _x('Lista de Inventários', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'tainacan-inventarios'),
                'insert_into_item'      => _x('Inserir no Inventário', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'tainacan-inventarios'),
                'uploaded_to_this_item' => _x('Enviado para este Inventário', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'tainacan-inventarios'),
                'filter_items_list'     => _x('Filtrar lista de Inventários', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'tainacan-inventarios'),
                'items_list_navigation' => _x('Navegação da lista de inventários', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'tainacan-inventarios'),
                'items_list'            => _x('Lista de Inventários', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'tainacan-inventarios'),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'inventarios'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'show_in_rest'       => true,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields'),
            'template' => $this->get_inventario_template(),
        );
        register_post_type('inventarios', $args);
    }

    public function inventarios_section_description() {
	?>
		<p class="settings-section-description">
			<?php echo _e('Opções do plugin "Tainacan Inventários"', 'tainacan-inventarios');?>
		</p>
	<?php
	}

    function register_hook() {
		if ( function_exists( 'tainacan_register_admin_hook' ) ) {
			tainacan_register_admin_hook( 'item', array( $this, 'form' ), 'begin-left', array( 'collectionId' => '' . $this->get_inventarios_collection_id() ) );
		}
	}

    function save_data( $object ) {
        if ( ! function_exists( 'tainacan_get_api_postdata' ) ) {
			return;
		}

		$post = tainacan_get_api_postdata();

		if ( $object->can_edit() ) {
			if ( isset( $post->{$this->inventario_post_id_field} ) ) {
				update_post_meta( $object->get_id(), $this->inventario_post_id_field, $post->{$this->inventario_post_id_field} );
			}
		}
    }

    function add_meta_to_response( $extra_meta, $request ) {
		$extra_meta = array(
			$this->inventario_post_id_field
		);
		return $extra_meta;
	}

    function form() {
		if ( !function_exists( 'tainacan_get_api_postdata' ) )
			return '';
        
        $inventario_posts = get_posts( array(
            'post_type' => 'inventarios'
        ) );

		ob_start();
		?>
		<div class="tainacan-inventarios-extra-fields"> 
            <h4><?php _e( 'Inventário', 'tainacan-inventarios'); ?></h4>
            <div class="field">
                <label class="label"><?php _e( 'Página de Inventário', 'tainacan-inventarios' ); ?></label>
                <div class="control">
                    <span class="select is-fullwidth">
                        <select name="<?php echo $this->inventario_post_id_field; ?>">
                            <option value=""><?php _e( 'Selecione uma página de Inventário', 'tainacan-inventarios' ); ?></option>
                            <?php foreach ( $inventario_posts as $post ) : ?>   
                                <option value="<?php echo esc_attr( $post->ID ); ?>">
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </div>
                <p class="help">
                    <?php _e( 'Escolha uma página da lista de páginas de inventário para mostrar mais informações sobre o item, além dos metadados.', 'tainacan-inventarios'); ?>
                </p>
            </div>
		</div>
		<?php
        
		return ob_get_clean();
	}
 
    function custom_the_content ($content) {
        if (!is_singular()) return $content;

        global $post;

        $redirect_id = get_post_meta($post->ID, $this->inventario_post_id_field, true);
        $redirect_id = absint($redirect_id);

        if (!$redirect_id || $redirect_id === $post->ID) {
            return $content; // evita loop ou valores inválidos
        }

        $redirect_post = get_post($redirect_id);

        if ( $redirect_post && $redirect_post->post_status === 'publish') {
            return do_blocks($redirect_post->post_content);
        }

        return $content;
    }


    function get_inventario_template() {
        return [
            [
                'core/columns',
                [
                    'align' => 'wide',
                ],
                [
                    [
                        'core/column',
                        [
                            'width' => '33.33%',
                        ],
                        [
                            [
                                'tainacan/item-metadata-sections',
                                [
                                    'collectionId' => $this->get_inventarios_collection_id(),
                                ],
                            ],
                        ],
                    ],
                    [
                        'core/column',
                        [
                            'width' => '66.66%',
                        ],
                        [
                            [
                                'core/post-title',
                                [
                                    'placeholder' => __('Título do Inventário', 'tainacan-inventarios'),
                                    'level' => 1,
                                ],
                            ],
                            [
                                'core/paragraph',
                                [
                                    'placeholder' => __('Insira aqui blocos apresentando o Inventário', 'tainacan-inventarios'),
                                ],
                            ],
                            [
                                'tainacan/related-items-list',
                                [
                                    'collectionId' => $this->get_inventarios_collection_id(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    function get_inventarios_collection_id() {
        return get_option('tainacan_option_' . $this->inventarios_collection_id_field);
    }

}

