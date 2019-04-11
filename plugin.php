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
	public static $instance = null;

	/**
	 * Blocksy extensions manager.
	 *
	 * @var ExtensionsManager
	 */
	public $extensions = null;
	public $extensions_api = null;

	public $dashboard = null;

	public $theme_integration = null;

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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init() {
		if (! $this->check_if_blocksy_is_activated()) {
			return;
		}

		$this->extensions_api = new ExtensionsManagerApi();
		$this->theme_integration = new ThemeIntegration();
	}

	/**
	 * Init components that need early access to the system.
	 *
	 * @access private
	 */
	public function early_init() {
		if (! $this->check_if_blocksy_is_activated()) {
			return;
		}

		$this->extensions = new ExtensionsManager();
		$this->dashboard = new Dashboard();
	}

	/**
	 * Register autoloader.
	 *
	 * Blocksy autoloader loads all the classes needed to run the plugin.
	 *
	 * @access private
	 */
	private function register_autoloader() {
		require BLOCKSY_PATH . '/framework/autoload.php';

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
		$this->register_autoloader();
		$this->early_init();

		add_action( 'init', [ $this, 'init' ], 0 );
	}

	private function check_if_blocksy_is_activated() {
		return wp_get_theme()->get('Name') === 'Blocksy';
	}
}

Plugin::instance();

