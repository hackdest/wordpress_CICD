<?php

namespace Blocksy;

class Blocks {
	private $blocks = [];

	public function __construct() {
		// Mount at `after_setup_theme` to make sure the theme is loaded
		add_action(
			'after_setup_theme',
			[$this, 'mount'],

			// Companion will mount with priority 50.
			// need to skip if the blocks are already mounted
			100
		);
	}

	public function mount() {
		$mount_allowed = apply_filters(
			'blocksy:general:blocks:mount-allowed',
			true
		);

		if (! $mount_allowed) {
			return;
		}

		register_block_pattern_category(
			'blocksy',
			[
				'label' => _x(
					'Blocksy',
					'Block pattern category',
					'blocksy'
				),
				'description' => __(
					'Patterns that contain buttons and call to actions.',
					'blocksy'
				),
			]
		);

		add_action('enqueue_block_editor_assets', function () {
			$deps = [
				'wp-blocks',
				'wp-core-data',
				'wp-element',
				'wp-block-editor',
				'wp-server-side-render',
			];

			global $wp_customize;

			if ($wp_customize) {
				$deps[] = 'ct-customizer-controls';
			} else {
				$deps[] = 'ct-options-scripts';
			}

			$theme = blocksy_get_wp_parent_theme();

			wp_register_style(
				'blocksy-theme-blocks-editor-styles',
				get_template_directory_uri() . '/static/bundle/theme-blocks-editor-styles.min.css',
				[],
				$theme->get('Version')
			);

			wp_enqueue_script(
				'blocksy/gutenberg-blocks',
				get_template_directory_uri() . '/static/bundle/blocks/blocks.js',
				$deps,
				$theme->get('Version')
			);

			$data = [
				'breadcrumb_home_item' => blocksy_get_theme_mod('breadcrumb_home_item', 'text'),
				'breadcrumb_home_text' => blocksy_get_theme_mod('breadcrumb_home_text', __( 'Home Page Text', 'blocksy' )),
				'breadcrumb_separator' => blocksy_get_theme_mod('breadcrumb_separator', 'type-1'),
				'breadcrumb_page_title' => blocksy_get_theme_mod('breadcrumb_page_title', 'yes') === 'yes',
			];

			wp_localize_script(
				'blocksy/gutenberg-blocks',
				'blc_blocks_data',
				$data
			);
		});

		$blocks = [
			'about-me',
			'contact-info',
			'quote',
			'socials',
			'search',
			'share-box'
		];

		foreach ($blocks as $block) {
			$this->blocks[$block] = new \Blocksy\GutenbergBlock($block, [
				'static' => false,
			]);
		}

		add_action('wp_ajax_blocksy_get_dynamic_block_view', function () {
			if (
				! current_user_can('edit_posts')
				||
				! isset($this->blocks[$_POST['block']])
			) {
				wp_send_json_error();
			}

			$gutenberg_block = $this->blocks[$_POST['block']];

			wp_send_json_success([
				'content' => $gutenberg_block->render(
					json_decode(wp_unslash($_POST['attributes']), true)
				),
			]);
		});

		$this->init_blocks();
	}

	public function init_blocks() {
		// Root Block
		new \Blocksy\Blocks\BlockWrapper();

		new \Blocksy\Blocks\BreadCrumbs();
		new \Blocksy\Blocks\Query();
		new \Blocksy\Blocks\DynamicData();
	}
}
