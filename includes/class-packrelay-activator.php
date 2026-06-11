<?php
/**
 * Plugin activation handler.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Activator
 */
class PackRelay_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Sets default options, creates entry table, and checks for provider
	 * availability. On network activation, runs once per site.
	 *
	 * @param bool $network_wide Whether the plugin is network-activated.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids' ) );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::activate_single_site();
				restore_current_blog();
			}

			return;
		}

		self::activate_single_site();
	}

	/**
	 * Activation steps for the current site.
	 */
	public static function activate_single_site() {
		$existing = get_option( 'packrelay_settings' );

		if ( false === $existing ) {
			$defaults = array(
				'form_provider'       => 'divi',
				'firebase_project_id' => 'mrdemonwolf-official-app',
				'notification_email'  => get_option( 'admin_email', '' ),
				'allowed_form_ids'    => '',
				'allowed_origins'     => '',
			);

			update_option( 'packrelay_settings', $defaults );
		}

		PackRelay_Entry_Store::create_table();
		update_option( 'packrelay_db_version', PACKRELAY_VERSION );

		if ( ! self::is_provider_available() ) {
			set_transient( 'packrelay_provider_notice', true, 30 );
		}
	}

	/**
	 * Create the entries table for a newly added multisite site.
	 *
	 * Hooked to wp_initialize_site.
	 *
	 * @param \WP_Site $new_site The new site object.
	 */
	public static function initialize_new_site( $new_site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( PACKRELAY_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		self::activate_single_site();
		restore_current_blog();
	}

	/**
	 * Re-run the table schema when the plugin is updated outside activation.
	 *
	 * Updates delivered by the plugin-update-checker never fire the
	 * activation hook, so dbDelta must run again when the version changes.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'packrelay_db_version' ) === PACKRELAY_VERSION ) {
			return;
		}

		PackRelay_Entry_Store::create_table();
		update_option( 'packrelay_db_version', PACKRELAY_VERSION );
	}

	/**
	 * Check if the configured provider is available.
	 *
	 * @return bool
	 */
	public static function is_provider_available() {
		$provider = PackRelay_Provider_Factory::create();
		return $provider->is_available();
	}
}
