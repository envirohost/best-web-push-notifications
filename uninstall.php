<?php
/**
 * Uninstall: removes data only when "Delete all data" is enabled.
 *
 * @package BWPN
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bwpn_settings = get_option( 'bwpn_settings', array() );

wp_clear_scheduled_hook( 'bwpn_watchdog' );
wp_clear_scheduled_hook( 'bwpn_daily' );
wp_unschedule_hook( 'bwpn_process_job' );
wp_unschedule_hook( 'bwpn_migrate_perfecty' );

if ( ! is_array( $bwpn_settings ) || empty( $bwpn_settings['delete_data_on_uninstall'] ) ) {
	return; // Keep subscribers and keys so a reinstall keeps working.
}

global $wpdb;
foreach ( array( 'bwpn_subscribers', 'bwpn_notifications', 'bwpn_logs' ) as $bwpn_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $bwpn_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
foreach ( array( 'bwpn_settings', 'bwpn_vapid_public_key', 'bwpn_vapid_private_key', 'bwpn_version', 'bwpn_db_version', 'bwpn_watchdog_last_run', 'bwpn_perfecty_migration' ) as $bwpn_option ) {
	delete_option( $bwpn_option );
}
delete_transient( 'bwpn_admin_notices' );
delete_post_meta_by_key( '_bwpn_send_on_publish' );
delete_post_meta_by_key( '_bwpn_custom_title' );
