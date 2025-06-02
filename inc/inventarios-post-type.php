<?php

/**
 * Registers the inventarios post type.
 */
function tainacan_inventarios_inventario_post_type_init() {

    // Registers inventario post type 
    $args = array(
        'labels'             => array(
            'name'                  => _x( 'Inventários', 'Post type general name', 'tainacan-inventarios' ),
            'singular_name'         => _x( 'Inventário', 'Post type singular name', 'tainacan-inventarios' ),
            'menu_name'             => _x( 'Inventários', 'Admin Menu text', 'tainacan-inventarios' ),
            'name_admin_bar'        => _x( 'Inventário', 'Add New on Toolbar', 'tainacan-inventarios' ),
            'add_new'               => __( 'Adicionar Novo', 'tainacan-inventarios' ),
            'add_new_item'          => __( 'Adicionar Novo Inventário', 'tainacan-inventarios' ),
            'new_item'              => __( 'Novo Inventário', 'tainacan-inventarios' ),
            'edit_item'             => __( 'Editar Inventário', 'tainacan-inventarios' ),
            'view_item'             => __( 'Ver Inventário', 'tainacan-inventarios' ),
            'all_items'             => __( 'Todos os Inventários', 'tainacan-inventarios' ),
            'search_items'          => __( 'Pesquisar Inventários', 'tainacan-inventarios' ),
            'parent_item_colon'     => __( 'Inventários pais:', 'tainacan-inventarios' ),
            'not_found'             => __( 'Nenhum Inventário encontrado.', 'tainacan-inventarios' ),
            'not_found_in_trash'    => __( 'Nenhum Inventário encontrado na lixeira.', 'tainacan-inventarios' ),
            'featured_image'        => _x( 'Imagem de capa do Inventário', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'tainacan-inventarios' ),
            'set_featured_image'    => _x( 'Configurar imagem de capa', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'tainacan-inventarios' ),
            'remove_featured_image' => _x( 'Remover imagem de capa', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'tainacan-inventarios' ),
            'use_featured_image'    => _x( 'Usar como imagem de capa', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'tainacan-inventarios' ),
            'archives'              => _x( 'Lista de Inventários', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'tainacan-inventarios' ),
            'insert_into_item'      => _x( 'Inserir no Inventário', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'tainacan-inventarios' ),
            'uploaded_to_this_item' => _x( 'Enviado para este Inventário', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'tainacan-inventarios' ),
            'filter_items_list'     => _x( 'Filtrar lista de Inventários', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'tainacan-inventarios' ),
            'items_list_navigation' => _x( 'Navegação da lista de inventários', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'tainacan-inventarios' ),
            'items_list'            => _x( 'Lista de Inventários', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'tainacan-inventarios' ),
        ),
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'inventarios' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'show_in_rest'       => true,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
    );
    register_post_type( 'inventarios', $args );

    // Registers the post meta to handle the related item ID
    register_post_meta( 
        'inventarios',
        'inventario-item-id',
        array(
            'single'       => true,
            'type'         => 'integer',
            'default'      => 0,
            'show_in_rest' => true,
        )
    );
}
add_action( 'init', 'tainacan_inventarios_inventario_post_type_init' );

/**
 * Register meta boxes.
 */
function tainacan_inventarios_register_inventario_meta_boxes() {

        // Adds meta box to visually configure it
        add_meta_box(
            'inventario-item-id_metabox',
            __('Item Tainacan da Coleção Inventários', 'tainacan-inventarios'),
            'tainacan_inventarios_metabox_inventario_content',
            'inventarios',
            'side',
            'high',
            array()
        );
}
add_action( 'add_meta_boxes', 'tainacan_inventarios_register_inventario_meta_boxes' );

/**
 * Function to display the actual meta box content
 */
function tainacan_inventarios_metabox_inventario_content($inventario) {
    $collection_inventarios = get_theme_mod('tainacan_inventarios_collection_id', 0);

    if (!$collection_inventarios)
        return;
    
    $items_args = array(
        'posts_per_page' => 48
    );
    $items = \tainacan_items()->fetch($items_args, $collection_inventarios);
    
    if (!$items)
        return;

    $selected = esc_attr( get_post_meta( get_the_ID(), 'inventario-item-id', true ) );
?>
    <div class="tainacan_inventarios_meta_box">
        <style scoped>
            .tainacan_inventarios_meta_box {
                max-width: 100%;
                overflow: hidden;
            }
            .tainacan_inventarios_meta_box select {
                max-width: calc(100% - 2px);
                box-sizing: border-box;
                margin-top: 4px;
            }
        </style>
        <label for="inventario-item-id">
            Item
        </label>
        <select id="inventario-item-id" name="inventario-item-id">
            <option value=""><?php echo __('Nenhum item selecionado.' , 'tainacan-inventarios'); ?></option>
            <?php 
                foreach($items->posts as $item) {
                    echo '<option value="' . $item->ID . '" ' . (( !empty($selected) && $selected == $item->ID ) ? 'selected="selected"' : '') . '>' . $item->post_title . '</option>';
                }
            ?>
        </select>
    </div>
<?php
};

/**
 * Save meta box content.
 *
 * @param int $post_id Post ID
 */
function tainacan_inventarios_save_meta_box( $post_id ) {
    if ( get_post_type( $post_id ) !== 'inventarios' ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $parent_id = wp_is_post_revision( $post_id ) ) {
        $post_id = $parent_id;
    }
    $fields = [
        'inventario-item-id'
    ];
    foreach ( $fields as $field ) {
        if ( array_key_exists( $field, $_POST ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );

            $current_item = \tainacan_items()->fetch($_POST[$field], [], 'OBJECT');

            if ($current_item instanceof \Tainacan\Entities\Item) {
                $current_item->set_document( get_permalink( $post_id) );
                $current_item->set_document_type('url');

                if ( $current_item->validate() ) {
                    $current_item = \tainacan_items()->update($current_item);
                }
            }
        }
    }
}
add_action( 'save_post', 'tainacan_inventarios_save_meta_box' );

/**
 * Sobrescreve o conteúdo dos itens da coleção de inventário para exibir o template customizado 
 */
if ( !function_exists('tainacan_inventarios_the_content_for_inventario') ) {
	function tainacan_inventarios_the_content_for_inventario( $content ) {
		// This should only happen if we have Tainacan plugin installed
		if ( defined ('TAINACAN_VERSION') ) {
			
			if ( !is_single() || !is_singular() || !in_the_loop() || !is_main_query() )
				return $content;

			$post_type = get_post_type();
			
			if ( $post_type == 'tnc_col_' . get_theme_mod('tainacan_inventarios_collection_id') . '_item' ) { 
				ob_start();
				get_template_part( 'tainacan/item-single-page' );
				$new_content = ob_get_contents();
				ob_end_clean();
				return $new_content;
			}

		}	
	
		return $content;
	}
}
add_filter( 'the_content', 'tainacan_inventarios_the_content_for_inventario', 11);