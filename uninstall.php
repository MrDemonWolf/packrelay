<?php
/**
 * PackRelay uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin settings.
delete_option( 'packrelay_settings' );

// Delete any transients.
delete_transient( 'packrelay_wpforms_notice' );

// Clean up any packrelay_ transients from the database.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_packrelay_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_packrelay_' ) . '%'
	)
);
