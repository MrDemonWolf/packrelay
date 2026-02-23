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
	 * Sets default options, creates entry table, and checks for provider availability.
	 */
	public static function activate() {
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

		if ( ! self::is_provider_available() ) {
			set_transient( 'packrelay_provider_notice', true, 30 );
		}
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
