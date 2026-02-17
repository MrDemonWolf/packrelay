<?php
/**
 * Plugin deactivation handler.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Deactivator
 */
class PackRelay_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		delete_transient( 'packrelay_wpforms_notice' );
	}
}
