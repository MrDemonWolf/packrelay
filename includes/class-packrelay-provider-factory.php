<?php
/**
 * Provider factory.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Provider_Factory
 *
 * Creates provider instances based on settings.
 */
class PackRelay_Provider_Factory {

	/**
	 * Provider class mapping.
	 *
	 * @var array
	 */
	private static $providers = array(
		'divi'         => 'PackRelay_Provider_Divi',
		'wpforms'      => 'PackRelay_Provider_WPForms',
		'gravityforms' => 'PackRelay_Provider_GravityForms',
	);

	/**
	 * Create a provider instance.
	 *
	 * @param string|null $slug Optional provider slug. Reads from settings if null.
	 * @return PackRelay_Provider
	 */
	public static function create( $slug = null ) {
		if ( null === $slug ) {
			$settings = PackRelay_Settings::get_settings();
			$slug     = $settings['form_provider'] ?? 'divi';
		}

		if ( isset( self::$providers[ $slug ] ) ) {
			$class = self::$providers[ $slug ];
			return new $class();
		}

		return new PackRelay_Provider_Divi();
	}

	/**
	 * Get all available providers with their labels.
	 *
	 * @return array Slug => label mapping.
	 */
	public static function get_available_providers() {
		$available = array();

		foreach ( self::$providers as $slug => $class ) {
			$instance              = new $class();
			$available[ $slug ] = array(
				'label'     => $instance->get_label(),
				'available' => $instance->is_available(),
			);
		}

		return $available;
	}
}
