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

global $wpdb;

// Drop the custom entries table.
$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%s`', esc_sql( $wpdb->prefix . 'packrelay_entries' ) ) );

// Delete plugin settings.
delete_option( 'packrelay_settings' );

// Delete any known transients.
delete_transient( 'packrelay_provider_notice' );

// Clean up any remaining packrelay_ transients from the database.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_packrelay_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_packrelay_' ) . '%'
	)
);
