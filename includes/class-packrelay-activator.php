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
	 * Sets default options and checks for WPForms dependency.
	 */
	public static function activate() {
		$existing = get_option( 'packrelay_settings' );

		if ( false === $existing ) {
			$defaults = array(
				'recaptcha_site_key'    => '',
				'recaptcha_secret_key'  => '',
				'recaptcha_threshold'   => 0.5,
				'notification_email'    => get_option( 'admin_email', '' ),
				'allowed_form_ids'      => '',
				'allowed_origins'       => '',
			);

			update_option( 'packrelay_settings', $defaults );
		}

		if ( ! self::is_wpforms_active() ) {
			set_transient( 'packrelay_wpforms_notice', true, 30 );
		}
	}

	/**
	 * Check if WPForms is active.
	 *
	 * @return bool
	 */
	public static function is_wpforms_active() {
		return class_exists( 'WPForms' ) || function_exists( 'wpforms' );
	}
}
