<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wesender_Log {

	const DB_VERSION = 1;

	public static function install(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'wesender_log';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sent_at datetime NOT NULL,
			to_address text NOT NULL,
			subject varchar(500) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			source varchar(200) NOT NULL DEFAULT '',
			error_message text,
			PRIMARY KEY  (id),
			KEY sent_at (sent_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'wesender_log_db_version', self::DB_VERSION );
	}

	public static function record( array $to, string $subject, string $status, string $source = '', string $error = '' ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wesender_log',
			[
				'sent_at'       => current_time( 'mysql' ),
				'to_address'    => implode( ', ', $to ),
				'subject'       => wp_strip_all_tags( $subject ),
				'status'        => $status,
				'source'        => $source,
				'error_message' => $error,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	public static function get_entries( int $page = 1, int $per_page = 25 ): array {
		global $wpdb;
		$table  = esc_sql( $wpdb->prefix . 'wesender_log' );
		$offset = ( $page - 1 ) * $per_page;

		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY sent_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	public static function clear(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}wesender_log`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_sources(): array {
		global $wpdb;
		$results = $wpdb->get_col( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT DISTINCT source FROM `{$wpdb->prefix}wesender_log` WHERE source != '' ORDER BY source ASC"
		);
		return $results ?: [];
	}
}
