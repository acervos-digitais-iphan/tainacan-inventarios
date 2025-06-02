<?php
/*
Plugin Name: Tainacan Inventários
Description: Plugin para gerenciar inventários no Tainacan.
Version: 0.0.1
Author: mateuswetah
Text Domain: tainacan-inventarios
Requires Plugins: tainacan
*/

const TAINACAN_INVENTARIOS_VERSION = '0.0.1';

// Evita acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

function tainacan_inventarios_show_only_not_control_collections($query) {
    if ( !is_admin() && $query->is_archive() && is_post_type_archive( 'tainacan-collection' ) ) {
        $tax_query = array(
            'taxonomy' => 'category',
            'field' => 'slug',
            'terms' => 'control',
            'operator'=> 'NOT IN' 
        );
        $query->tax_query->queries[] = $tax_query; 
   		$query->query_vars['tax_query'] = $query->tax_query->queries;
    }
}
add_action( 'pre_get_posts', 'tainacan_inventarios_show_only_not_control_collections' );

function tainacan_inventarios_customize_control_collection_css() {
	$control_collection_ids = [];
	$control_collections = \Tainacan_Inventarios\CategoryCollection::get_instance()->get_tainacan_inventarios_control_collections();

	if ( empty($control_collections) )
		return;

	$control_collection_ids = array_map(function($collection) {
		return $collection->get_ID();
	}, $control_collections);
	
	$css = '';

	foreach( $control_collection_ids as $control_collection_id ) {

		$control_collection_metadatum_ids = [];
		$control_collection_metadata = \Tainacan\Repositories\Metadata::get_instance()->fetch([
			'meta_query' => [
				[
					'key'   => 'metadata_type',
					'value' => 'Tainacan\Metadata_Types\Relationship'
				],
				[
					'key' => '_option_collection_id',
					'value' => $control_collection_id
				]
			],
			'perpage' => -1
		], 'OBJECT');

		if ( empty($control_collection_metadata) )
			continue;
		
		$control_collection_metadatum_ids = array_map(function($metadatum) {
			return $metadatum->get_ID();
		}, $control_collection_metadata);

		foreach( $control_collection_metadatum_ids as $control_collection_metadatum_id ) {

            $control_collection_item_edition_page_selector = '.columns.is-fullheight.tainacan-admin-collection-item-edition-mode>.column>#collection-page-container[collection-id="' . $control_collection_id . '"]';
            $control_collection_relationship_metadatatum_selector = '.columns.is-fullheight:not(.tainacan-admin-collection-item-edition-mode)>.column>#collection-page-container .tainacan-metadatum-component--tainacan-relationship.tainacan-metadatum-id--' . $control_collection_metadatum_id;

			$css .= '
				/* Tweaks the relationship input on the collection that has relation to the control collection, so that it only allows creation or edition of existing items */
				' . $control_collection_relationship_metadatatum_selector . ' .tabs,
				' . $control_collection_relationship_metadatatum_selector . ' .tab-content>.tab-item:first-of-type {
					display: none;
					visibility: hidden;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .tab-content>.tab-item:last-of-type {
					display: block !important;
					visibility: visible;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .tainacan-modal .modal-content {
					max-width: 640px !important;
					max-height: 60vh !important;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .tainacan-modal .modal-content iframe {
					height: 59vh !important;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .tainacan-relationship-results-container {
					border: none;
					padding-left: 0;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .tainacan-relationship-results-container  .tainacan-relationship-group > div > .multivalue-separator {
					margin-left: 0;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .tainacan-relationship-results-container .tainacan-metadatum {
					margin-left: 0px;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .add-link {
					content: "";
					color: transparent !important;
					font-size: 0 !important;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .add-link>.icon {
					font-size: 0.875rem;
				}
				' . $control_collection_relationship_metadatatum_selector . ' .add-link::after {
					content: "Adicionar valor";
					color: var(--tainacan-secondary);
					font-size: 0.75rem;
					margin-top: 6px;
				}
			
				/* Hides elements not necessary for control collections inside the item edition modal */
				' . $control_collection_item_edition_page_selector . '>.tainacan-form>.columns>.column:first-of-type {
					width: 100%%;
					padding: 0 1rem;
				}
				' . $control_collection_item_edition_page_selector . '>.tainacan-form>.columns>.column:last-of-type,
				' . $control_collection_item_edition_page_selector . '>.tainacan-form>.columns>.column:first-of-type>.columns,
				' . $control_collection_item_edition_page_selector . '>.tainacan-form>.columns>.column:first-of-type>.b-tabs>.tabs,
				' . $control_collection_item_edition_page_selector . '>.tainacan-form>.columns>.column:first-of-type>.b-tabs .sub-header {
					display: none;
					visibility: hidden;
				}
				' . $control_collection_item_edition_page_selector . '>.tainacan-form>.columns>.column:first-of-type>.b-tabs>.tab-content {
					border-top: none;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .tainacan-page-title {
					margin-bottom: 12px;
					padding: 0 1.5rem;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .tainacan-page-title h1 {
					content: "";
					color: transparent !important;
					font-size: 0 !important;
				}
				' . $control_collection_item_edition_page_selector . '.page-container.item-creation-container .tainacan-page-title h1::after {
					content: "Adicionar valor";
					color: var(--tainacan-gray5);
					font-size: 1.25rem;
				}
				' . $control_collection_item_edition_page_selector . '.page-container.item-edition-container .tainacan-page-title h1::after {
					content: "Editar valor";
					color: var(--tainacan-gray5);
					font-size: 1.25rem;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .column.is-main-column .tab-item > .field:last-child {
					margin-bottom: 0 !important;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .column.is-main-column .tainacan-finder-columns-container {
					max-height: 50vh
				}
				' . $control_collection_item_edition_page_selector . '.page-container .form-submission-footer .item-edition-footer-dropdown {
					display: none !important;
					visibility: hidden;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .footer {
					position: fixed;
					padding: 16px 1em;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .update-info-section {
					margin-bottom: -2.5rem;
					margin-left: 0;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .form-submission-footer .button.is-success {
					content: "";
					color: transparent !important;
					font-size: 0 !important;
					margin-left: auto;
				}
				' . $control_collection_item_edition_page_selector . '.page-container .form-submission-footer .button.is-outlined {
					display: none;
					visibility: hidden;
				}
				' . $control_collection_item_edition_page_selector . '.page-container.item-creation-container .form-submission-footer .button.is-success::after {
					content: "Adicionar";
					color: white;
					font-size: 0.875rem;
				}
				' . $control_collection_item_edition_page_selector . '.page-container.item-edition-container .form-submission-footer .button.is-success::after {
					content: "Concluir";
					color: white;
					font-size: 0.875rem;
				}
				' . $control_collection_item_edition_page_selector . ' .status-tag {
					display: none;
					visibility: hidden;
				}
				' . $control_collection_item_edition_page_selector . ' .field {
					padding-left: 0;
				}
				' . $control_collection_item_edition_page_selector . ' .field .collapse-handle,
				' . $control_collection_item_edition_page_selector . ' .field .collapse-handle .label {
					margin-left: 0;
				}
				' . $control_collection_item_edition_page_selector . ' .field .collapse-handle .icon {
					display: none;
					visibility: hidden;
				}
			';
		}
	}
	
	echo '<style type="text/css" id="tainacan-control-collections-style">' . sprintf( $css ) . '</style>';
}
add_action('admin_head', 'tainacan_inventarios_customize_control_collection_css');

function tainacan_inventarios_customize_form_hooks_css() {

	$css = '
		/* Tainacan Inventários Form Hooks */
		.tainacan-category-taxonomy-collection .control {
			column-count: 2;
		}

		.tainacan-category-taxonomy-collection .control .checkbox {
			break-inside: avoid;
		}

		.tainacan-metadatum-edition-form--type-tainacan-relationship .form-hook-region,
		#collection-page-container .form-hook-region {
			display: block;
			visibility: visible;
		}

		/* Hides disabled inputs on the media frame when user does not have permission to edit them. Avoid frustration...*/
		.tainacan-document-modal input:disabled,
		.tainacan-document-modal input[readonly],
		.tainacan-document-modal textarea:disabled,
		.tainacan-document-modal textarea[readonly],
		.tainacan-item-attachments-modal input:disabled,
		.tainacan-item-attachments-modal input[readonly],
		.tainacan-item-attachments-modal textarea:disabled,
		.tainacan-item-attachments-modal textarea[readonly] {
			display: none !important;
		}
	';
	
	echo '<style type="text/css" id="tainacan-inventarios-form-hooks-style">' . sprintf( $css ) . '</style>';
}
add_action('admin_head', 'tainacan_inventarios_customize_form_hooks_css');

/*
 * Sends params to the Tainacan Admin Options to hide certain elements according to user caps
 */
function tainacan_inventarios_set_tainacan_admin_options($options) {
	
	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		$roles = ( array ) $user->roles;
		$tainacan_inventarios_tainacan_admin_options = [];
		$admin_options_collections = get_option('tainacan_inventarios_tainacan_admin_options_by_role', []);
		$admin_options_collections = is_array($admin_options_collections) ? $admin_options_collections : [];

		foreach($roles as $role) {
			if ( isset($admin_options_collections[$role])) {
				foreach($admin_options_collections[$role] as $option) {
					
					$tainacan_inventarios_tainacan_admin_options[$option] = true;

					if ($option == 'hideHomeCollectionsButton') {
						$tainacan_inventarios_tainacan_admin_options['homeCollectionsPerPage'] = 18;
					}
				}
				$tainacan_inventarios_tainacan_admin_options['homeCollectionsOrderBy'] = 'title';
				$tainacan_inventarios_tainacan_admin_options['homeCollectionsOrder'] = 'asc';
			}
		}
		$options = array_merge($options, $tainacan_inventarios_tainacan_admin_options);
	}
	return $options;
};
add_filter('tainacan-admin-ui-options', 'tainacan_inventarios_set_tainacan_admin_options');

require_once __DIR__ . '/inc/imports.php';
