<?php

/**
 * FILTROS EXPANDIDOS DE RELACIONAMENTO
 * 
 * Com o uso extensivo de relacionamentos em Acervos de Inventários, o metadado de relacionamento
 * vira uma poderosa ferramenta de organização de dados, viabilizando coleções separadas por funções
 * como as coleções de controle. Surge desse uso porém, uma nova demanda: a de se filtrar por
 * diferentes metadados das coleções relacionadas.
 * 
 * Em um metadado tipo relacionamento, o valor guardado no item é o título e ID do item relacionado.
 * Demais metadados do mesmo item podem ser mostrados na página do item em si, mas não são encontrados
 * em uma busca com filtros, por exemplo. Para isso seria preciso fazer uma consulta com "saltos duplos",
 * onde primeiro busca-se nos itens relacionados e depois nos demais metadados dos itens relacionados.
 * 
 * Esta classe implementa uma lógica para se guardar em metadados internos escondidos (metadados de controle
 * do Tainacan) cópias dos dados relacionados na coleção onde um item está relacionado. Com a existência
 * destes dados torna-se possível a criação de filtros a partir dos mesmos, onde tornam-se acessíveis via
 * facetas os valores que estão em coleções relacionadas.
 * 
 * A abordagem adotada aqui não é razoável para cenários de dados de volume muito alto, por isso é 'opt-in':
 * o usuário deve configurar quais relacionamentos devem ser "expandidos" manualmente.
 *
 */

namespace Tainacan_Inventarios;

// Evita acesso direto ao arquivo
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Expanded_Filter_Relationship {

	use Singleton;

	public $has_expanded_filters_field = 'tainacan-inventarios-has-expanded-filters';

	protected function init() {

		// Lógica para adicionar a opção extra no formulário definirá se os filtros do metadado de relacionamento serão expandidos ou não
		add_action( 'tainacan-register-admin-hooks', array( $this, 'register_hook' ) );
		add_action( 'tainacan-insert-tainacan-metadatum', array( $this, 'create_control_metadata' ), 10, 2 );
		add_action( 'tainacan-deleted-tainacan-metadatum', array( $this, 'remove_control_metadata' ), 10, 2 );
		add_filter( 'tainacan-api-response-metadatum-meta', array( $this, 'add_meta_to_response'), 10, 2 );

		add_action( 'tainacan-insert-Item_Metadata_Entity', array( $this, 'update_control_metadata_values' ), 10, 2 );
		add_filter( 'tainacan-fetch-all-metadatum-values', array( $this, 'fetch_all_metadatum_values'), 10, 3 );
		add_filter( 'tainacan-api-prepare-items-args', array( $this, 'replace_prepare_items_args'), 10, 2 );
	}

	/**
     * Usa da action 'tainacan-register-admin-hooks' para registrar uma nova área de formulários
     * extra no modal de edição do metadado Tainacan, onde ficará a opção de expansão dos filtros
     */
	public function register_hook() {
		if ( function_exists( 'tainacan_register_admin_hook' ) ) {

			tainacan_register_admin_hook(
				'metadatum',			// Entity
				array( $this, 'form'),	// Form HTML callback
				'end-left',				// Position
				[ 'attribute' => 'metadata_type', 'value' => 'Tainacan\Metadata_Types\Relationship' ] // Conditional
			);
		}
	}

	/**
     * Callback passada para a função `tainacan_register_admin_hook` com o formulário interno que será
     * passado para o modal de edição do metadado, contendo o campo extra da definição de metadado com 
	 * filtros expandidos
     */
	public function form() {
		if ( ! function_exists( 'tainacan_get_api_postdata' ) ) {
			return '';
		}

		ob_start();
		?>

			<div class="tainacan-expanded-filter-relationship"> 
				<div class="field tainacan-metadatum--section-header">
					<h4><?php _e( 'Opções do Tainacan Inventários', 'tainacan-inventarios' ); ?></h4>
					<hr>
				</div>
				<div 
						class="field"
						style="margin: 0.5em 0;">
					<label class="label">
						<?php _e('Expandir filtros', 'tainacan-inventarios'); ?>
						<span class="help-wrapper">
							<a class="help-button has-text-secondary">
								<span class="icon is-small">
									<i class="tainacan-icon tainacan-icon-help"></i>
								</span>
							</a>
							<div class="help-tooltip">
								<div class="help-tooltip-header">
									<h5 class="has-text-color"><?php _e('Expandir filtros', 'tainacan-inventarios'); ?></h5>
								</div>
								<div class="help-tooltip-body">
									<p><?php _e('Essa opção adiciona à lista de filtros os metadados selecionados para exibição.', 'tainacan-inventarios'); ?></p>
								</div>
							</div>
						</span>
					</label>
					<div class="control is-expanded">
						<span class="select is-fullwidth">
							<select name="<?php echo $this->has_expanded_filters_field; ?>" id="expanded-filter-select">
								<option value="yes"><?php _e('Sim', 'tainacan-inventarios'); ?></option>
								<option value="no"><?php _e('Não', 'tainacan-inventarios'); ?></option>
							</select>
						</span>
					</div>
				</div>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
     * Usa do filtro 'tainacan-api-response-item-meta' para fazer com que o campo que diz se o
	 * metadado deve ter filtros expandidos ('tainacan-inventarios-has-expanded-filters') apareça no
     * retorno da API quando usamos o endpoint de itens.
     */
	public function add_meta_to_response( $extra_meta, $request ) {
		$extra_meta = array_merge( $extra_meta, array($this->has_expanded_filters_field) );
		return $extra_meta;
	}

	/**
     * Usa da action 'tainacan-insert-tainacan-metadatum' para de fato atualizar a entidade do metadado
     * com o post meta 'tainacan-inventarios-has-expanded-filters', que guarda a informação se o metadado
	 * possui ou não filtros expandidos.
	 * 
	 * Além disso, aproveita usa deste momento para criar os metadados internos escondidos (no Tainacan 
	 * são chamados metadados de controle) que guardarão as cópias dos valores dos metadados originais do 
	 * relacionamento.
     */
	public function create_control_metadata($metadatum) {

		// Relacionamentos ou compostos dentro de relacionamentos não devem ser expandidos
		if (
			!$metadatum instanceof \Tainacan\Entities\Metadatum ||
			$metadatum->get_metadata_type() !== 'Tainacan\Metadata_Types\Relationship' ||
			$metadatum->get_metadata_type() !== 'Tainacan\Metadata_Types\Compound' ||
			!$metadatum->can_edit()
		) {
			return;
		}

		$post = tainacan_get_api_postdata();
		$has_expanded_filters = isset($post[$this->has_expanded_filters_field]) ? 'yes' == $post[$this->has_expanded_filters_field] : false;
		update_post_meta( $metadatum->get_id(), $this->has_expanded_filters_field, $has_expanded_filters ? 'yes' : 'no');

		if ( $has_expanded_filters ) {
			$options = $metadatum->get_metadata_type_options();
			$relationship_name = $metadatum->get_name();
			$colection_id = $metadatum->get_collection_id();
			$collection = \tainacan_collections()->fetch($colection_id);
			$relationship_collection_id = $options['collection_id'];
			$relationship_collection = \tainacan_collections()->fetch($relationship_collection_id);
			$relationship_collection_metadata = \tainacan_metadata()->fetch_by_collection($relationship_collection, ['posts_per_page' => -1]);

			$args = [
				'include_control_metadata_types' => true,
				'meta_query' => [
					[
						'key'     => 'metadata_type',
						'value'   => 'Tainacan\Metadata_Types\Control',
					],
					[
						'key'     => '_option_control_metadatum',
						'value'   => $this->has_expanded_filters_field,
					],
					[
						'key'     => '_option_meta_relationship_id',
						'value'   => $metadatum->get_id(),
					]
				]
			];
			$data_control_metadata_already_existing = \tainacan_metadata()->fetch_by_collection( $collection, $args );
			$data_control_metadata_already_existing = array_map(function($mtd) {
				$opts = $mtd->get_metadata_type_options();
				return $opts['meta_id'];
			}, $data_control_metadata_already_existing);

			$data_control_metadata = array();

			foreach ($relationship_collection_metadata as $relationship_meta) {
				$id = $relationship_meta->get_id();
				
				if ( in_array($id, $data_control_metadata_already_existing) ) {
					continue;
				}
				$name = $relationship_meta->get_name();
				$type = $relationship_meta->get_metadata_type_object();
				$relationship_options = $relationship_meta->get_metadata_type_options();
				$data_control_metadata["relationship_metadata_$id"] = [
					'name'            => "$relationship_name/$name",
					'description'     => "$relationship_name/$name",
					'collection_id'   => $colection_id,
					'metadata_type'   => 'Tainacan\Metadata_Types\Control',
					'status'          => 'publish',
					'display'         => 'never',
					'multiple'        => 'yes',
					'metadata_type_options' => [
						'control_metadatum' => $this->has_expanded_filters_field,
						'meta_relationship_id' => $metadatum->get_id(),
						'meta_id' => $id,
					]
				];
				if ( $type->get_primitive_type() == 'term') {
					$data_control_metadata["relationship_metadata_$id"]['metadata_type_options']['type'] = 'term';
					$data_control_metadata["relationship_metadata_$id"]['metadata_type_options']['taxonomy_id'] = $relationship_options['taxonomy_id'];
				}
			}

			foreach ( $data_control_metadata as $index => $data_control_metadatum ) {
				$metadatum = new \Tainacan\Entities\Metadatum();
				
				foreach ( $data_control_metadatum as $attribute => $value ) {
					$set_ = 'set_' . $attribute;
					$metadatum->$set_( $value );
				}
				if ( $metadatum->validate() ) {
					$metadatum = \tainacan_metadata()->insert( $metadatum );
					if ( isset($data_control_metadatum['metadata_type_options']['type']) && $data_control_metadatum['metadata_type_options']['type'] == 'term') {
						$taxonomy_id = $data_control_metadatum['metadata_type_options']['taxonomy_id'];
						do_action( 'tainacan-taxonomy-added-to-collection', $taxonomy_id, $colection_id );
					}
				} else {
					throw new \ErrorException( 'The entity wasn\'t validated.' . print_r( $metadatum->get_errors(), true ) );
				}
			}
		} else {
			$this->remove_control_metadata($metadatum);
		}
	}

	/**
	 * Usa da action 'tainacan-deleted-tainacan-metadatum' para remover os metadados de controle
	 * criados para a expansão dos filtros buscando não poluir a coleção com lixo nem deixar 
	 * brecha para consultas com filtros quebrados.
	 */
	public function remove_control_metadata($metadatum) {
		if ( !$metadatum instanceof \Tainacan\Entities\Metadatum || $metadatum->get_metadata_type() !== 'Tainacan\Metadata_Types\Relationship') { 
			return;
		}
		if ( !$metadatum->can_edit() ) {
			return;
		}

		$args = [
			'include_control_metadata_types' => true,
			'meta_query' => [
				[
					'key'     => 'metadata_type',
					'value'   => 'Tainacan\Metadata_Types\Control',
				],
				[
					'key'     => '_option_control_metadatum',
					'value'   => $this->has_expanded_filters_field,
				],
				[
					'key'     => '_option_meta_relationship_id',
					'value'   => $metadatum->get_id(),
				]
			]
		];

		$colection_id = $metadatum->get_collection_id();
		$collection = \tainacan_collections()->fetch($colection_id);
		$metadatum_list = \tainacan_metadata()->fetch_by_collection( $collection, $args );

		foreach ($metadatum_list as $metadata) {
			\tainacan_metadata()->delete( $metadata );
		}
	}

	/**
	 * Usa da action 'tainacan-insert-Item_Metadata_Entity' para salvar a cópia atualizada do
	 * metadado de controle com base no novo valor do metadado relacionado.
	 */
	public function update_control_metadata_values($item_metadata) {
		if (! $item_metadata instanceof \Tainacan\Entities\Item_Metadata_Entity) {
			return false;
		}

		$item = $item_metadata->get_item();
		$metadatum = $item_metadata->get_metadatum();

		if (!$item instanceof \Tainacan\Entities\Item || !$metadatum instanceof \Tainacan\Entities\Metadatum || $metadatum->get_metadata_type() !== 'Tainacan\Metadata_Types\Relationship') { 
			return;
		}

		$colection_id = $metadatum->get_collection_id();
		$collection = \tainacan_collections()->fetch($colection_id);
		$options = $metadatum->get_metadata_type_options();

		$args = [
			'include_control_metadata_types' => true,
			'meta_query' => [
				[
					'key'     => 'metadata_type',
					'value'   => 'Tainacan\Metadata_Types\Control',
				],
				[
					'key'     => '_option_control_metadatum',
					'value'   => $this->has_expanded_filters_field,
				],
				[
					'key'     => '_option_meta_relationship_id',
					'value'   => $metadatum->get_id(),
				]

			]
		];
		$metadata = \tainacan_metadata()->fetch_by_collection( $collection, $args );

		$values = array();
		$relationship_items = $item_metadata->get_value();
		$relationship_items = is_array($relationship_items) ? $relationship_items : [$relationship_items];

		foreach ($relationship_items as $relationship_item_id) {
			if ( empty($relationship_item_id) ) {
				continue;
			}

			$relationship_item = \tainacan_items()->fetch($relationship_item_id);

			foreach ($metadata as $item_metadatum) {
				$options = $item_metadatum->get_metadata_type_options();
				$meta_id = $options['meta_id'];
				$relationship_metadata = \tainacan_metadata()->fetch($meta_id, 'OBJECT');

				if ($relationship_metadata instanceof \Tainacan\Entities\Metadatum && $relationship_metadata->get_metadata_type() != 'Tainacan\Metadata_Types\Compound') {
					$item_metadata = new \Tainacan\Entities\Item_Metadata_Entity( $relationship_item, $relationship_metadata );
					$value = $relationship_metadata->is_multiple() ? $item_metadata->get_value() : [$item_metadata->get_value()];

					if (!isset($values[$meta_id])) {
						$values[$meta_id] = [];
					}
					$values[$meta_id] = array_merge($values[$meta_id], $value);
				}
			}
		}

		foreach ( $metadata as $item_metadatum ) {
			if ( $item_metadatum->get_metadata_type_object() instanceof \Tainacan\Metadata_Types\Control ) {
				$options = $item_metadatum->get_metadata_type_options();
				$meta_id = $options['meta_id'];
				$type = isset($options['type']) ? $options['type'] : false;
				$value = !isset( $values[$meta_id] ) ? [] : $values[$meta_id];

				if ( $type && $type == 'term' ) {
					$taxonomy_id = $options['taxonomy_id'];
					$taxonomy = \tainacan_taxonomies()->fetch( (int) $taxonomy_id );

					if ( $taxonomy instanceof Entities\Taxonomy ) {
						\wp_set_object_terms( $item->get_id(), $value, $taxonomy->get_db_identifier() );
					} else {
						error_log( "Taxonomy not found!" );
					}
				} else {
					$update_item_metadatum = new \Tainacan\Entities\Item_Metadata_Entity( $item, $item_metadatum );
					$update_item_metadatum->set_value( $value );

					if ( $update_item_metadatum->validate() ) {
						\tainacan_item_metadata()->insert( $update_item_metadatum );
					} else {
						error_log( json_encode($update_item_metadatum->get_errors()) );
					}
				}
			}
		}
	}

	/**
	 * Usa do filtro `tainacan-fetch-all-metadatum-values` para modificar a saída da função do repositório de 
	 * metadados do Tainacan fetch_all_metadatum_values. Esta é a função que calcula a quantidade de itens que
	 * uma faceta (um valor de metadado) tem dado uma certa consulta e é portanto essencial para a construção 
	 * dos filtros com os somatórios de quantos itens tem por valor.
	 */
	public function fetch_all_metadatum_values($return, $metadatum, $args) {
		$options = $metadatum->get_metadata_type_options();
		$type = isset($options['type']) ? $options['type'] : false;

		if ( !$type || $type != 'term') {
			return null;
		}

		global $wpdb;
		$itemsRepo = \tainacan_items();
		$taxonomy_id = $options['taxonomy_id'];
		$taxonomy_slug = \tainacan_taxonomies()->get_db_identifier_by_id($taxonomy_id);

		if ( false !== $args['items_filter'] && is_array($args['items_filter']) ) {
			add_filter('posts_pre_query', '__return_empty_array');
			$items_query = $itemsRepo->fetch($args['items_filter'], $args['collection_id']);
			$items_query = $items_query->request;
			remove_filter('posts_pre_query', '__return_empty_array');
		}

		$pagination = '';
		if ( $args['offset'] >= 0 && $args['number'] >= 1 ) {
			$pagination = $wpdb->prepare( "LIMIT %d,%d", (int) $args['offset'], (int) $args['number'] );
		}

		$search_q = '';
		$search = trim($args['search']);
		if (!empty($search)) {
			$search_q = $wpdb->prepare("AND meta_value IN ( SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s )", '%' . $search . '%');
		}

		if ($items_query) {
			$check_hierarchy_q = $wpdb->prepare("SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s AND parent > 0 LIMIT 1", $taxonomy_slug);
			$has_hierarchy = ! is_null($wpdb->get_var($check_hierarchy_q));

			if ( ! $has_hierarchy ) {
				$base_query = $wpdb->prepare("FROM $wpdb->term_relationships tr
					INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					INNER JOIN $wpdb->terms t ON tt.term_id = t.term_id
					WHERE
					tt.parent = %d AND
					tr.object_id IN ($items_query) AND
					tt.taxonomy = %s
					$search_q
					ORDER BY t.name ASC
					",
					$args['parent_id'],
					$taxonomy_slug
				);

				$query = "SELECT DISTINCT t.name, t.term_id, tt.term_taxonomy_id, tt.parent $base_query $pagination";

				$total_query = "SELECT COUNT(DISTINCT tt.term_taxonomy_id) $base_query";
				$total = $wpdb->get_var($total_query);

				$results = $wpdb->get_results($query);

			} else {
				$base_query = $wpdb->prepare("
					SELECT DISTINCT t.term_id, t.name, tt.parent, coalesce(tr.term_taxonomy_id, 0) as have_items
					FROM
					$wpdb->terms t INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
					LEFT JOIN (
						SELECT DISTINCT term_taxonomy_id FROM $wpdb->term_relationships
							INNER JOIN ($items_query) as posts ON $wpdb->term_relationships.object_id = posts.ID
					) as tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tt.taxonomy = %s ORDER BY t.name ASC", $taxonomy_slug
				);

				$all_hierarchy = $wpdb->get_results($base_query);

				if (empty($search)) {
					$results = \tainacan_metadata()->_process_terms_tree($all_hierarchy, $args['parent_id'], 'parent');
				} else  {
					$results = \tainacan_metadata()->_process_terms_tree($all_hierarchy, $search, 'name');
				}

				$total = count($results);

				if ( $args['offset'] >= 0 && $args['number'] >= 1 ) {
					$results = array_slice($results, (int) $args['offset'], (int) $args['number']);
				}
			}
		} else {

			$parent_q = $wpdb->prepare("AND tt.parent = %d", $args['parent_id']);
			if ($search_q) {
				$parent_q = '';
			}

			$base_query = $wpdb->prepare("FROM $wpdb->term_taxonomy tt
				INNER JOIN $wpdb->terms t ON tt.term_id = t.term_id
				WHERE 1=1
				$parent_q
				AND tt.taxonomy = %s
				$search_q
				ORDER BY t.name ASC
				",
				$taxonomy_slug
			);

			$query = "SELECT DISTINCT t.name, t.term_id, tt.term_taxonomy_id, tt.parent $base_query $pagination";

			$total_query = "SELECT COUNT(DISTINCT tt.term_taxonomy_id) $base_query";
			$total = $wpdb->get_var($total_query);

			$results = $wpdb->get_results($query);
		}

		if ( !empty($args['include']) ) {
			if ( is_array($args['include']) && !empty($args['include']) ) {

				// protect sql
				$args['include'] = array_map(function($t) { return (int) $t; }, $args['include']);

				$include_ids = implode(',', $args['include']);
				$query_to_include = "SELECT DISTINCT t.name, t.term_id, tt.term_taxonomy_id, tt.parent FROM $wpdb->term_taxonomy tt
					INNER JOIN $wpdb->terms t ON tt.term_id = t.term_id
					WHERE
					t.term_id IN ($include_ids)";

				$to_include = $wpdb->get_results($query_to_include);

				// remove terms that will be included at the begining
				$results = array_filter($results, function($t) use($args) { return !in_array($t->term_id, $args['include']); });

				$results = array_merge($to_include, $results);

			}
		}

		$number = ctype_digit($args['number']) && $args['number'] >=1 ? $args['number'] : $total;

		if (  $number < 1){
			$pages = 1;
		} else {
			$pages = ceil( $total / $number );
		}
		$separator = strip_tags(apply_filters('tainacan-terms-hierarchy-html-separator', '>'));
		$values = [];

		foreach ($results as $r) {

			$count_query = $wpdb->prepare("SELECT COUNT(term_id) FROM $wpdb->term_taxonomy WHERE parent = %d", $r->term_id);
			$total_children = $wpdb->get_var($count_query);

			$label = wp_specialchars_decode($r->name);
			$total_items = null;

			if ( $args['count_items'] ) {
				$count_items_query = $args['items_filter'];
				$count_items_query['posts_per_page'] = 1;
				
				if ( !isset($count_items_query['tax_query']) ) {
					$count_items_query['tax_query'] = [];
				}
				$count_items_query['tax_query'][] = [
					'taxonomy' => $taxonomy_slug,
					'terms' => $r->term_id
				];
				$count_items_results = $itemsRepo->fetch($count_items_query, $args['collection_id']);
				$total_items = $count_items_results->found_posts;

				//$label .= " ($total_items)";

			}

			$values[] = [
				'value' => $r->term_id,
				'label' => $label,
				'total_children' => $total_children,
				'taxonomy' => $taxonomy_slug,
				'taxonomy_id' => $taxonomy_id,
				'parent' => $r->parent,
				'total_items' => $total_items,
				'type' => 'Taxonomy',
				'hierarchy_path' => get_term_parents_list($r->term_id, $taxonomy_slug, ['format'=>'name', 'separator'=>$separator, 'link'=>false, 'inclusive'=>false])
			];

		}

		return [
			'total' => $total,
			'pages' => $pages,
			'values' => $values,
			'last_term' => $args['last_term']
		];

	}

	public function replace_prepare_items_args($args, $request) {

		if ( isset($args['meta_query']) ) {

			foreach($args['meta_query'] as $key => $meta_query) {
				$meta_id = $meta_query['key'];
				$metadatum = \tainacan_metadata()->fetch($meta_id);

				if ( !$metadatum) return $args;

				$options = $metadatum->get_metadata_type_options();
				$type = isset($options['type']) ? $options['type'] : false;

				if ( !$type || $type != 'term') {
					continue;
				}

				$taxonomy_id = $options['taxonomy_id'];
				$taxonomy_slug = \tainacan_taxonomies()->get_db_identifier_by_id($taxonomy_id);

				if (  !isset($args['tax_query']) ) {
					$args['tax_query'] = [];
				}

				$args['tax_query'][] = ['taxonomy'=>$taxonomy_slug, 'terms' => $meta_query['value']];
				unset($args['meta_query'][$key]);
			}
		}
		return $args;
	}

}
