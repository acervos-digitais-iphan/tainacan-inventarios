<?php

/**
 * Funções do Menu Personalizar inseridas pelo Tainacan Inventários
 *
 * @package Tainacan Inventários
 */

/**
 * Add postMessage support for site title for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function tainacan_inventarios_customize_register($wp_customize) {


    /* TEMPLATE DO INVENTÁRIO */
    $wp_customize->add_section('template_tainacan_inventario', array(
        'title'       => __('Template dos Inventários', 'tainacan-inventarios'),
    ));


    /* Escolher qual template usar para o Tema */

/*     $wp_customize->add_setting('template_inventario', array(
        'default' => '',
        'type' => 'theme_mod',
        'transport'  => 'refresh',
        'sanitize_callback' => 'sanitize_text_field'
    ));
    $wp_customize->add_control('escolhas_template_inventario', array(
        'label' => 'Template do Inventário', 'tainacan-inventarios',
        'type' => 'radio',
        'section' => 'template_tainacan_inventario',
        'settings' => 'template_inventario',
        'priority' => 3,
        'choices' => array(
            'default' => __('Padrão'),
            'custom' => __('Personalizado'),
        )
    )); */
    $wp_customize->add_setting('tainacan_inventarios_collection_id', array(
        'default' => '',
        'type' => 'theme_mod',
        'transport'  => 'refresh',
        'sanitize_callback' => 'sanitize_text_field'
    ));

    $repository = \Tainacan\Repositories\Collections::get_instance();
    $collections_options = [];
    $collections = $repository->fetch()->posts;

    foreach($collections as $collection) {
        $collections_options[$collection->ID] = $collection->post_title;
    }
    
    $wp_customize->add_control('escolhas_inventario', array(
        'label' => 'Inventário', 'tainacan-inventarios',
        'type' => 'select',
        'section' => 'template_tainacan_inventario',
        'settings' => 'tainacan_inventarios_collection_id',
        'priority' => 2,
        'choices' => $collections_options
    ));
}
add_action('customize_register', 'tainacan_inventarios_customize_register');
