<?php
/**
 * PackRelay uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 * On multisite, cleans up every site's table and options.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove PackRelay data for the current site.
 */
function packrelay_uninstall_site() {
	global $wpdb;

	// Drop the custom entries table.
	$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%s`', esc_sql( $wpdb->prefix . 'packrelay_entries' ) ) );

	// Delete plugin options.
	delete_option( 'packrelay_settings' );
	delete_option( 'packrelay_db_version' );

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
}

if ( is_multisite() ) {
	$packrelay_site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $packrelay_site_ids as $packrelay_site_id ) {
		switch_to_blog( $packrelay_site_id );
		packrelay_uninstall_site();
		restore_current_blog();
	}
} else {
	packrelay_uninstall_site();
}
