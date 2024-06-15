<?php

namespace Blocksy;

class ConditionsManagerAPI {
	public function __construct() {
		add_action('wp_ajax_blocksy_conditions_get_all_posts', function () {
			if (! current_user_can('manage_options')) {
				wp_send_json_error();
			}

			$maybe_input = json_decode(file_get_contents('php://input'), true);

			if (! $maybe_input) {
				wp_send_json_error();
			}

			if (! isset($maybe_input['post_type'])) {
				wp_send_json_error();
			}

			$query_args = [
				'posts_per_page' => 10,
				'post_type' => $maybe_input['post_type'],
				'suppress_filters' => true,
				'lang' => ''
			];

			if (
				isset($maybe_input['search_query'])
				&&
				! empty($maybe_input['search_query'])
			) {
				if (intval($maybe_input['search_query'])) {
					$query_args['p'] = intval($maybe_input['search_query']);
				} else {
					$query_args['s'] = $maybe_input['search_query'];
				}
			}

			$initial_query_args_post_type = $query_args['post_type'];

			if (strpos($initial_query_args_post_type, 'ct_cpt') !== false) {
				$query_args['post_type'] = blocksy_manager()->post_types->get_all([
					'exclude_built_in' => true,
					'exclude_woo' => true
				]);
			}

			if (strpos($initial_query_args_post_type, 'ct_all_posts') !== false) {
				$query_args['post_type'] = blocksy_manager()->post_types->get_all();
			}

			$query = new \WP_Query($query_args);

			$posts_result = $query->posts;

			if (isset($maybe_input['alsoInclude'])) {
				$maybe_post = get_post($maybe_input['alsoInclude'], 'display');

				if ($maybe_post) {
					$posts_result[] = $maybe_post;
				}
			}

			wp_send_json_success([
				'posts' => $posts_result
			]);
		});

		add_action('wp_ajax_blc_retrieve_conditions_data', function () {
			if (! current_user_can('manage_options')) {
				wp_send_json_error();
			}

			$filter = 'all';

			$allowed_filters = [
				'archive',
				'singular',
				'product_tabs',
				'maintenance-mode',
				'content_block_hook'
			];

			if (
				$_REQUEST['filter']
				&&
				in_array($_REQUEST['filter'], $allowed_filters)
			) {
				$filter = $_REQUEST['filter'];
			}

			$cpts = blocksy_manager()->post_types->get_supported_post_types();

			$cpts[] = 'post';
			$cpts[] = 'page';
			$cpts[] = 'product';

			$taxonomies = [];

			foreach ($cpts as $cpt) {
				$taxonomies = array_merge($taxonomies, array_values(array_diff(
					get_object_taxonomies($cpt),
					['post_format']
				)));
			}

			$terms = [];

			foreach ($taxonomies as $taxonomy) {
				$taxonomy_object = get_taxonomy($taxonomy);

				if (! $taxonomy_object->public) {
					continue;
				}

				$local_terms = array_map(function ($tax) {
					return [
						'id' => $tax->term_id,
						'name' => $tax->name,
						'group' => get_taxonomy($tax->taxonomy)->label,
						'post_types' => get_taxonomy($tax->taxonomy)->object_type
					];
				}, blc_get_terms([
					'taxonomy' => $taxonomy,
					'hide_empty' => false,
				], [
					'all_languages' => true
				]));

				if (empty($local_terms)) {
					continue;
				}

				$terms = array_merge($terms, $local_terms);
			}

			$languages = [];

			if (function_exists('blocksy_get_current_language')) {
				$languages = blocksy_get_all_i18n_languages();
			}

			$users = [];

			foreach (get_users([
				'number' => 2000
			]) as $user) {
				$users[] = [
					'id' => $user->ID,
					'name' => $user->user_nicename
				];
			}

			$conditions_manager = new ConditionsManager();

			wp_send_json_success([
				'taxonomies' => $terms,
				'languages' => $languages,
				'users' => $users,
				'conditions' => $conditions_manager->get_all_rules([
					'filter' => $filter
				]),
			]);
		});
	}
}

