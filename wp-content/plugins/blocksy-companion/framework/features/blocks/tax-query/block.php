<?php

namespace Blocksy\Editor\Blocks;

class TaxQuery {
	public function __construct() {
		add_action('wp_ajax_blocksy_get_tax_block_data', function () {
			if (! current_user_can('edit_posts')) {
				wp_send_json_error();
			}

			$body = json_decode(file_get_contents('php://input'), true);

			if (! isset($body['attributes'])) {
				wp_send_json_error();
			}

			$query = $this->get_query_for($body['attributes']);

			$prefix = $this->get_prefix_for($body['attributes']);

			wp_send_json_success([
				'all_terms' => $query,
				'dynamic_styles' => $this->get_dynamic_styles_for(
					$body['attributes']
				),
			]);
		});

		add_action('wp_ajax_blocksy_get_terms_block_patterns', function () {
			if (! current_user_can('manage_options')) {
				wp_send_json_error();
			}

			$all_patterns = \WP_Block_Patterns_Registry::get_instance()
				->get_all_registered();

			$result = [];

			foreach ($all_patterns as $single_pattern) {
				if (
					isset($single_pattern['blockTypes'])
					&&
					is_array($single_pattern['blockTypes'])
					&&
					in_array(
						'blocksy/tax-query',
						$single_pattern['blockTypes']
					)
				) {
					$result[] = $single_pattern;
				}
			}

			wp_send_json_success([
				'patterns' => $result
			]);
		});

		register_block_type(
			BLOCKSY_PATH . '/static/js/editor/blocks/tax-query/block.json',
			[
				'render_callback' => function ($attributes, $content, $block) {
					$block_reader = new \WP_HTML_Tag_Processor($content);

					if (
						$block_reader->next_tag([
							'class_name' => 'wp-block-blocksy-query'
						])
						&&
						! empty($attributes['uniqueId'])
					) {
						$block_reader->set_attribute(
							'data-id',
							substr($attributes['uniqueId'], 0, 3)
						);
					}

					return $block_reader->get_updated_html();
				}
			]
		);

		add_filter(
			'render_block',
			function ($block_content, $block) {
				if ($block['blockName'] !== 'blocksy/tax-template') {
					return $block_content;
				}

				$processor = new \WP_HTML_Tag_Processor($block_content);
				
				$is_grid_layout = isset($block['attrs']['layout']['type']) && $block['attrs']['layout']['type'] === 'grid';
				$desktopColumns = isset($block['attrs']['layout']['columnCount']) ? $block['attrs']['layout']['columnCount'] : '3';
				$tabletColumns = isset($block['attrs']['tabletColumns']) ? $block['attrs']['tabletColumns'] : '2';
				$mobileColumns = isset($block['attrs']['mobileColumns']) ? $block['attrs']['mobileColumns'] : '1';

				$class = [];

				if ($processor->next_tag('div')) {
					$class = explode(' ', $processor->get_attribute('class'));
				}

				$unique_class = '';

				foreach ($class as $class_name) {
					if (strpos($class_name, 'wp-container-blocksy-tax-template-is-layout-') === 0) {
						$unique_class = $class_name;
					}
				}

				$alignmentStyles = [];

				if (
					isset($block['attrs']['verticalAlignment'])
					&&
					$is_grid_layout
				) {
					if ($block['attrs']['verticalAlignment'] === 'top') {
						$alignmentStyles['align-items'] = 'flex-start;';
					} elseif ($block['attrs']['verticalAlignment'] === 'bottom') {
						$alignmentStyles['align-items'] = 'flex-end;';
					} else {
						$alignmentStyles['align-items'] = 'center';
					}
				}

				wp_style_engine_get_stylesheet_from_css_rules(
					[
						[
							'selector' => '.' . $unique_class . '.' . $unique_class,
							'declarations' => array_merge(
								$alignmentStyles,
								[
									'grid-template-columns' => "repeat(var(--ct-grid-columns, {$desktopColumns}), minmax(0, 1fr));",
									'--ct-grid-columns-tablet' => $tabletColumns,
									'--ct-grid-columns-mobile' => $mobileColumns,
								],
							)
						]
					],
					[
						'context'  => 'block-supports',
						'prettify' => false
					]
				);

				return $block_content;
			},
			11,
			2
		);

		register_block_type(
			BLOCKSY_PATH . '/static/js/editor/blocks/tax-template/block.json',
			[
				'render_callback' => function ($attributes, $content, $block) {
					$query = $this->get_query_for($block->context);

					if (empty($query)) {
						return '';
					}

					$attributes = wp_parse_args(
						$attributes,
						[
							'tabletColumns' => '2',
							'mobileColumns' => '1'
						]
					);

					$content = '';
					$is_grid_layout = isset($attributes['layout']['type']) && $attributes['layout']['type'] === 'grid';

					$classnames = '';

					if ($is_grid_layout) {
						$classnames .= ' ct-query-template-grid';
					} else {
						$classnames .= ' ct-query-template-list';
					}

					if (isset($attributes['style']['elements']['link']['color']['text'])) {
						$classnames .= ' has-link-color';
					}

					foreach ($query as $term) {
						$term_obj = get_term($term['term_id']);
						$block_instance = $block->parsed_block;

						// Set the block name to one that does not correspond to an existing registered block.
						// This ensures that for the inner instances of the Post Template block, we do not render any block supports.
						$block_instance['blockName'] = 'core/null';

						global $blocksy_term_obj;
						$blocksy_term_obj = $term_obj;

						// Render the inner blocks of the Post Template block with `dynamic` set to `false` to prevent calling
						// `render_callback` and ensure that no wrapper markup is included.
						$block_content = (new \WP_Block($block_instance))->render(
							['dynamic' => false]
						);

						$content .= blocksy_html_tag(
							'div',
							[
								'class' => 'wp-block-blocksy-tax',
							],
							$block_content
						);

						$blocksy_term_obj = null;
					}

					$wrapper_attributes = get_block_wrapper_attributes(array_merge(
						[
							'class' => trim($classnames)
						]
					));

					$result = blocksy_safe_sprintf(
						'<div %1$s>%2$s</div>',
						$wrapper_attributes,
						$content
					);

					return $result;
				},
			]
		);

		add_action(
			'init',
			function () {
				$tax_block_patterns = [
					'tax-layout-1',
					'tax-layout-2',
					'tax-layout-3',
					'tax-layout-4',
				];

				foreach ($tax_block_patterns as $tax_block_pattern) {
					$pattern_data = blocksy_get_variables_from_file(
						__DIR__ . '/block-patterns/' . $tax_block_pattern . '.php',
						['pattern' => []]
					);

					register_block_pattern(
						'blocksy/' . $tax_block_pattern,
						$pattern_data['pattern']
					);
				}
			}
		);
	}

	public function get_query_for($attributes) {
		$attributes = wp_parse_args(
			$attributes,
			[
				'taxonomy' => 'category',
				'limit' => 5,
				'offset' => 0,
				'orderby' => 'none',
				'order' =>  'desc',
				'include_term_ids' => [],
				'exclude_term_ids' => [],
				'class' => ''
			]
		);

		$ffiltered_include = [];
		$filtered_exclude = [];

		if (
			! empty($attributes['include_term_ids'])
			&&
			isset($attributes['include_term_ids'][$attributes['taxonomy']])
		) {
			foreach ($attributes['include_term_ids'][$attributes['taxonomy']]['terms'] as $key => $value) {
				$term = get_term_by('slug', $value, $attributes['taxonomy']);

				if ($term) {
					$filtered_include[] = $term->term_id;
				}
			}
		}

		if (
			! empty($attributes['exclude_term_ids'])
			&&
			isset($attributes['exclude_term_ids'][$attributes['taxonomy']])
		) {
			foreach ($attributes['exclude_term_ids'][$attributes['taxonomy']]['terms'] as $key => $value) {
				$term = get_term_by('slug', $value, $attributes['taxonomy']);

				if ($term) {
					$filtered_exclude[] = $term->term_id;
				}
			}
		}

		$terms = get_terms(
			array_merge(
				[
					'taxonomy' => $attributes['taxonomy'],
					'orderby' => $attributes['orderby'],
					'order' => $attributes['order'],
					'offset' => $attributes['offset']
				],
				(
					$attributes['orderby'] !== 'rand' ? [
						'number' => $attributes['limit']
					] : []
				),
				(
					! empty($filtered_exclude) ? [
						'exclude' => $filtered_exclude
					] : []
				),
				(
					! empty($filtered_include) ? [
						'include' => $filtered_include
					] : []
				)
			)
		);

		if ($attributes['orderby'] === 'rand') {
			shuffle($terms);
			$terms = array_slice($terms, 0, $attributes['limit']);
		}

		$terms_descriptors = [];

		if ($terms) {
			foreach ($terms as $term) {
				$attachment = [];

				$id = get_term_meta($term->term_id, 'thumbnail_id');

				if (isset($id[0])) {
					$attachment = [
						'attachment_id' => $id[0],
						'url' => wp_get_attachment_image_url($id[0], 'full')
					];
				}

				$term_atts = get_term_meta(
					$term->term_id,
					'blocksy_taxonomy_meta_options'
				);

				if (empty($term_atts)) {
					$term_atts = [[]];
				}

				$term_atts = $term_atts[0];

				$maybe_icon = blocksy_akg('icon_image', $term_atts, '');
				$maybe_image = blocksy_akg('image', $term_atts, '');

				$terms_descriptors[] = [
					'term_id' => $term->term_id,
					'icon' => $maybe_icon,
					'image' => $maybe_image,
				];
			}
		}

		return $terms_descriptors;
	}

	public function get_dynamic_styles_for($attributes) {
		$prefix = $this->get_prefix_for($attributes);

		$styles = [
			'desktop' => '',
			'tablet' => '',
			'mobile' => ''
		];

		$css = new \Blocksy_Css_Injector();
		$tablet_css = new \Blocksy_Css_Injector();
		$mobile_css = new \Blocksy_Css_Injector();

		blocksy_theme_get_dynamic_styles([
			'name' => 'global/posts-listing',
			'css' => $css,
			'mobile_css' => $mobile_css,
			'tablet_css' => $tablet_css,
			'context' => 'global',
			'chunk' => 'global',
			'prefixes' => [ $prefix ]
		]);

		$styles['desktop'] .= $css->build_css_structure();
		$styles['tablet'] .= $tablet_css->build_css_structure();
		$styles['mobile'] .= $mobile_css->build_css_structure();

		$final_css = '';

		if (! empty($styles['desktop'])) {
			$final_css .= $styles['desktop'];
		}

		if (! empty(trim($styles['tablet']))) {
			$final_css .= '@media (max-width: 999.98px) {' . $styles['tablet'] . '}';
		}

		if (! empty(trim($styles['mobile']))) {
			$final_css .= '@media (max-width: 689.98px) {' . $styles['mobile'] . '}';
		}

		return $final_css;
	}

	public function get_prefix_for($attributes) {
		$attributes = wp_parse_args(
			$attributes,
			[
				'taxonomy' => 'category',
				'limit' => 5,
				'offset' => 0,
				'orderby' => 'none',
				'order' =>  'desc',
				'class' => ''
			]
		);

		$prefix = 'blog';

		$custom_post_types = blocksy_manager()->post_types->get_supported_post_types();

		$preferred_post_type = explode(',', $attributes['post_type'])[0];

		foreach ($custom_post_types as $cpt) {
			if ($cpt === $preferred_post_type) {
				$prefix = $cpt . '_archive';
			}
		}

		return $prefix;
	}
}

