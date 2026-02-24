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
	 * Entries page instance.
	 *
	 * @var PackRelay_Entries_Page
	 */
	protected $entries_page;

	/**
	 * Divi submissions instance.
	 *
	 * @var PackRelay_Divi_Submissions
	 */
	protected $divi_submissions;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader           = new PackRelay_Loader();
		$this->settings         = new PackRelay_Settings();
		$this->rest_api         = new PackRelay_REST_API();
		$this->entries_page     = new PackRelay_Entries_Page();
		$this->divi_submissions = new PackRelay_Divi_Submissions();

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
		$this->loader->add_action( 'admin_menu', $this->entries_page, 'add_menu_pages' );
		$this->loader->add_action( 'admin_menu', $this->settings, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $this->settings, 'register_settings' );
		$this->loader->add_action( 'admin_notices', $this, 'provider_dependency_notice' );

		// Unified admin styles and scripts for all PackRelay pages.
		$this->loader->add_action( 'admin_enqueue_scripts', $this->entries_page, 'enqueue_styles' );

		// CSV export handler.
		$this->loader->add_action( 'admin_init', $this->entries_page, 'handle_export' );
	}

	/**
	 * Register public-facing hooks.
	 */
	private function define_public_hooks() {
		$this->loader->add_action( 'rest_api_init', $this->rest_api, 'register_routes' );
		$this->loader->add_filter( 'rest_pre_serve_request', $this->rest_api, 'add_cors_headers', 10, 4 );

		// Capture Divi front-end form submissions.
		$this->loader->add_action( 'et_pb_contact_form_submit', $this->divi_submissions, 'save_submission', 10, 3 );
	}

	/**
	 * Show admin notice if the configured provider is not active.
	 */
	public function provider_dependency_notice() {
		if ( get_transient( 'packrelay_provider_notice' ) || ! PackRelay_Activator::is_provider_available() ) {
			delete_transient( 'packrelay_provider_notice' );

			$provider = PackRelay_Provider_Factory::create();
			$label    = $provider->get_label();

			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: form builder name */
				esc_html__( 'PackRelay requires %s to be installed and active. Please install it or change the form provider in PackRelay settings.', 'packrelay' ),
				esc_html( $label )
			);
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
