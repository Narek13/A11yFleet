<?php
/**
 * Uninstall — runs only when the plugin is deleted from the WordPress admin.
 * This is where destructive cleanup belongs (not on deactivation).
 *
 * @package A11yFleet
 */

declare(strict_types=1);

// Only run in a genuine uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Table names are fixed strings prefixed by $wpdb->prefix — safe to interpolate after esc_sql().
$a11yfleet_scans  = esc_sql( $wpdb->prefix . 'a11yfleet_scans' );
$a11yfleet_issues = esc_sql( $wpdb->prefix . 'a11yfleet_issues' );

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS {$a11yfleet_issues}" );
$wpdb->query( "DROP TABLE IF EXISTS {$a11yfleet_scans}" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

delete_option( 'a11yfleet_db_version' );
delete_option( 'a11yfleet_installed_at' );
delete_option( 'a11yfleet_settings' );
delete_option( 'a11yfleet_scan_queue' );

wp_clear_scheduled_hook( 'a11yfleet_scheduled_scan' );
wp_clear_scheduled_hook( 'a11yfleet_process_batch' );
