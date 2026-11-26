<?php
/**
 * Uninstall: remove data only when the owner asked for it.
 *
 * @package StaffWarden
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$staffwarden_settings = get_option( 'staffwarden_settings', array() );
if ( ! is_array( $staffwarden_settings ) || empty( $staffwarden_settings['delete_data'] ) || 'yes' !== $staffwarden_settings['delete_data'] ) {
	return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'staffwarden_log' ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall, own option names.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'staffwarden_' ) . '%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall, own meta keys.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_staffwarden_' ) . '%' ) );
wp_clear_scheduled_hook( 'staffwarden_hourly' );
wp_clear_scheduled_hook( 'staffwarden_daily' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'staffwarden' );
}
