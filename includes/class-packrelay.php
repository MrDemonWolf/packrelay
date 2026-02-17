<?php
/**
 * Core plugin orchestrator.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay
 *
 * Singleton that loads dependencies and registers all hooks via the loader.
 */
class PackRelay {

	/**
	 * Singleton instance.
	 *
	 * @var PackRelay|null
	 */
	private static $instance = null;

	/**
	 * Loader instance.
	 *
	 * @var PackRelay_Loader
	 */
	protected $loader;

	/**
	 * Settings instance.
	 *
	 * @var PackRelay_Settings
	 */
	protected $settings;

	/**
	 * REST API instance.
	 *
	 * @var PackRelay_REST_API
	 */
	protected $rest_api;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader   = new PackRelay_Loader();
		$this->settings = new PackRelay_Settings();
		$this->rest_api = new PackRelay_REST_API();

		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return PackRelay
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register admin-side hooks.
	 */
	private function define_admin_hooks() {
		$this->loader->add_action( 'admin_menu', $this->settings, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $this->settings, 'register_settings' );
		$this->loader->add_action( 'admin_notices', $this, 'wpforms_dependency_notice' );
	}

	/**
	 * Register public-facing hooks.
	 */
	private function define_public_hooks() {
		$this->loader->add_action( 'rest_api_init', $this->rest_api, 'register_routes' );
		$this->loader->add_filter( 'rest_pre_serve_request', $this->rest_api, 'add_cors_headers', 10, 4 );
	}

	/**
	 * Show admin notice if WPForms is not active.
	 */
	public function wpforms_dependency_notice() {
		if ( get_transient( 'packrelay_wpforms_notice' ) || ! PackRelay_Activator::is_wpforms_active() ) {
			delete_transient( 'packrelay_wpforms_notice' );
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'PackRelay requires WPForms to be installed and active. Please install WPForms to use PackRelay.', 'packrelay' );
			echo '</p></div>';
		}
	}

	/**
	 * Fire all registered hooks.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the loader instance.
	 *
	 * @return PackRelay_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}
}
