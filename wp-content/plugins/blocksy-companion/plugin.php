<?php

namespace Blocksy;

class Plugin {
	/**
	 * Blocksy instance.
	 *
	 * Holds the blocksy plugin instance.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Blocksy extensions manager.
	 *
	 * @var ExtensionsManager
	 */
	public $extensions = null;
	public $extensions_api = null;
	public $premium = null;

	public $dashboard = null;
	public $theme_integration = null;

	public $cli = null;
	public $cache_manager = null;

	// Features
	public $feat_google_analytics = null;
	public $demo = null;
	public $dynamic_css = null;
	public $header = null;
	public $account_auth = null;

	private $is_blocksy = '__NOT_SET__';
	public $is_blocksy_data = null;
	private $desired_blocksy_version = '2.0.49-beta1';

	/**
	 * Instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @static
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init() {
		add_action(
			'customize_controls_enqueue_scripts',
			function () {
				$this->enqueue_static();
			},
			100
		);

		add_action(
			'admin_enqueue_scripts',
			function () {
				$this->enqueue_static();

			},
			50
		);

		$this->cache_manager = new CacheResetManager();

		$this->extensions_api = new ExtensionsManagerApi();
		$this->theme_integration = new ThemeIntegration();
		$this->demo = new DemoInstall();
		$this->dynamic_css = new DynamicCss();

		new CustomizerOptionsManager();

		new ConditionsManagerAPI();
	}

	public function early_init() {
		if (is_admin()) {
			$this->dashboard = new Dashboard();
		}

		add_action(
			'admin_enqueue_scripts',
			function () {
				if (!function_exists('get_plugin_data')) {
					require_once(ABSPATH . 'wp-admin/includes/plugin.php');
				}

				$data = get_plugin_data(BLOCKSY__FILE__);

				wp_enqueue_style(
					'blocksy-styles',
					BLOCKSY_URL . 'static/bundle/options.min.css',
					[],
					$data['Version']
				);

				$current_screen = get_current_screen();

				// Don't enqueue the script in the root WP dashboard.
				// Sometimes it causes a redirect loop there in some setups.
				if ($current_screen && $current_screen->base === 'dashboard') {
					return;
				}

				$locale_data_ct = blc_get_jed_locale_data('blocksy-companion');

				wp_add_inline_script(
					'wp-i18n',
					'wp.i18n.setLocaleData( ' . wp_json_encode($locale_data_ct) . ', "blocksy-companion" );'
				);
			},
			50
		);
	}

	/**
	 * Init components that need early access to the system.
	 *
	 * @access private
	 */
	public function early_init_with_blocksy_theme() {
		if (
			blc_can_use_premium_code()
			&&
			blc_get_capabilities()->has_feature('base_pro')
		) {
			$this->premium = new Premium();
		}

		$this->extensions = new ExtensionsManager();

		$this->header = new HeaderAdditions();

		new Editor\Blocks();

		$this->feat_google_analytics = new GoogleAnalytics();
		new OpenGraphMetaData();
		new SvgHandling();

		$this->account_auth = new AccountAuth();

		if (defined('WP_CLI') && WP_CLI) {
			$this->cli = new Cli();
		}
	}

	/**
	 * Register autoloader.
	 *
	 * Blocksy autoloader loads all the classes needed to run the plugin.
	 *
	 * @access private
	 */
	private function register_autoloader() {
		require_once BLOCKSY_PATH . '/framework/autoload.php';

		Autoloader::run();
	}

	/**
	 * Plugin constructor.
	 *
	 * Initializing Blocksy plugin.
	 *
	 * @access private
	 */
	private function __construct() {
		require_once BLOCKSY_PATH . '/framework/helpers/request.php';
		require_once BLOCKSY_PATH . '/framework/helpers/helpers.php';
		require_once BLOCKSY_PATH . '/framework/helpers/exts.php';

		$this->register_autoloader();

		$this->early_init();

		if (! $this->check_if_blocksy_is_activated()) {
			return;
		}

		$this->early_init_with_blocksy_theme();

		add_action('init', [$this, 'init'], 0);
	}

	public function check_if_blocksy_is_activated() {
		$is_cli = defined('WP_CLI') && WP_CLI;

		if ($this->is_blocksy === '__NOT_SET__') {
			$theme = wp_get_theme(get_template());

			if ($theme->parent() && $theme->parent()->exists()) {
				$theme = $theme->parent();
			}

			if (! $is_cli) {
				$keys_to_check = [
					'wp_theme_preview',
					'theme',
					'customize_theme'
				];

				foreach ($keys_to_check as $key) {
					if (! isset($_GET[$key])) {
						continue;
					}

					$maybe_theme = wp_get_theme($_GET[$key]);

					if (! $maybe_theme->exists()) {
						continue;
					}

					if ($maybe_theme->parent() && $maybe_theme->parent()->exists()) {
						$maybe_theme = $maybe_theme->parent();
					}

					$theme = $maybe_theme;
				}
			}

			$is_correct_theme = strpos(
				$theme->get('Name'), 'Blocksy'
			) !== false;

			$is_correct_version = version_compare(
				$theme->get('Version'),
				$this->desired_blocksy_version
			) > -1;

			$another_theme_in_preview = false;

			if (! $is_cli) {
				$maybe_foreign_theme = '';

				if (
					isset($_REQUEST['customize_theme'])
					&&
					! empty($_REQUEST['customize_theme'])
				) {
					$maybe_foreign_theme = $_REQUEST['customize_theme'];
				}

				if (
					isset($_REQUEST['theme'])
					&&
					! empty($_REQUEST['theme'])
				) {
					$maybe_foreign_theme = $_REQUEST['theme'];
				}

				if ($is_correct_theme && $maybe_foreign_theme) {
					$foreign_theme_obj = wp_get_theme($maybe_foreign_theme);

					if ($foreign_theme_obj) {
						if ($foreign_theme_obj->parent()) {
							$foreign_theme_obj = $foreign_theme_obj->parent();
						}

						if (
							$foreign_theme_obj->get_stylesheet() !== $theme->get_stylesheet()
						) {
							$another_theme_in_preview = true;
						}
					}
				}
			}

			if ($is_cli) {
				$cli_config = \WP_CLI::get_config();

				// Companion plugin can't run if themes are skipped in WP CLI
				// config.
				//
				// This happens in cPanel installations.
				// Globally, they skip both themes and plugins.
				// But, for some commands, they enable plugins back with
				// --skip-plugins=false and keep themes disabled.
				// This causes the theme to be skipped and the companion plugin
				// to run, which causes lots of issues in various environments.
				if (
					isset($cli_config['skip-themes'])
					&&
					$cli_config['skip-themes']
				) {
					$is_correct_theme = false;
					$is_correct_version = false;
				}
			}

			$this->is_blocksy_data = [
				'is_correct_theme' => (
					$is_correct_theme
					&&
					! $another_theme_in_preview
				),
				'another_theme_in_preview' => $another_theme_in_preview
			];

			$this->is_blocksy = (
				$is_correct_theme
				&&
				$is_correct_version
				&&
				! $another_theme_in_preview
			);
		}

		return !!$this->is_blocksy;
	}

	public function enqueue_static() {
		if (! function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}

		global $wp_customize;

		$data = get_plugin_data(BLOCKSY__FILE__);

		$deps = ['ct-options-scripts'];

		$current_screen = get_current_screen();

		if ($current_screen && $current_screen->id === 'customize') {
			$deps = ['ct-customizer-controls'];
		}

		wp_enqueue_script(
			'blocksy-admin-scripts',
			BLOCKSY_URL . 'static/bundle/options.js',
			$deps,
			$data['Version'],
			true
		);

		$localize = array_merge(
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'rest_url' => get_rest_url(),
			]
		);

		wp_localize_script(
			'blocksy-admin-scripts',
			'blocksy_admin',
			$localize
		);
	}
}

Plugin::instance();

