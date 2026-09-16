<?php
/**
 * Removes everything the plugin created.
 *
 * @package AumCrawl
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'aumcrawl_settings', 'aumcrawl_db_version', 'aumcrawl_deferred' ) as $aumcrawl_option ) {
	delete_option( $aumcrawl_option );
}

foreach ( array( 'daily', 'pages', 'suspect' ) as $aumcrawl_which ) {
	$aumcrawl_table = $wpdb->prefix . 'aumcrawl_' . $aumcrawl_which;
	$wpdb->query( "DROP TABLE IF EXISTS {$aumcrawl_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name built from $wpdb->prefix.
}

wp_clear_scheduled_hook( 'aumcrawl_prune' );
