<?php
/**
 * Fired when the plugin is deleted from the Plugins screen.
 *
 * Data is kept unless the delete_data setting is on.
 *
 * @package Hitzordu
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$hitzordu_settings = get_option('hitzordu_settings', array());

if (empty($hitzordu_settings['delete_data'])) {
	return;
}

global $wpdb;

$hitzordu_tables = array( 'meeting_types', 'availability_rules', 'date_overrides', 'bookings' );

foreach ( $hitzordu_tables as $hitzordu_table ) {
	// Table names cannot be prepared; they are fixed strings plus the WP prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hitzordu_{$hitzordu_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'hitzordu_db_version' );
delete_option( 'hitzordu_settings' );
