<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package           Giga_APW
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop custom table
$table_name = $wpdb->prefix . 'giga_apw_generations';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// 2. Delete plugin options
delete_option( 'giga_apw_settings' );
delete_option( 'giga_apw_api_key' );
delete_option( 'giga_apw_license_key' );
delete_option( 'giga_apw_license_status' );
delete_option( 'giga_apw_brand_voice_profile' );
delete_option( 'giga_apw_bulk_progress' );

// 3. Clear usage transients/options
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'giga_apw_usage_%'" );

// 4. Delete all product meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_giga_apw_%'" );

// 5. Clear scheduled cron events
wp_clear_scheduled_hook( 'giga_apw_process_bulk' );
