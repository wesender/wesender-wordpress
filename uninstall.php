<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wesender_api_key' );
delete_transient( 'wesender_update_info' );
delete_option( 'wesender_from_email' );
delete_option( 'wesender_from_name' );
delete_option( 'wesender_blocked_sources' );
delete_option( 'wesender_log_db_version' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wesender_log`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
