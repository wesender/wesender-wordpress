<?php
/**
 * Plugin Name:       Wesender e-mail
 * Plugin URI:        https://wesender.nl/apps/wordpress/
 * Description:       Stuur alle WordPress e-mails via Wesender. Verbind je account met een klik, geen SMTP-instellingen nodig.
 * Version:           1.3.3
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Wesender
 * Author URI:        https://wesender.nl
 * License:           GPL v2 or later
 * Text Domain:       wesender-wp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WESENDER_VERSION',    '1.3.3' );
define( 'WESENDER_API_BASE',   'https://api.wesender.nl' );
define( 'WESENDER_APP_URL',    'https://app.wesender.nl' );
define( 'WESENDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WESENDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WESENDER_PLUGIN_DIR . 'includes/class-wesender-api.php';
require_once WESENDER_PLUGIN_DIR . 'includes/class-wesender-log.php';
require_once WESENDER_PLUGIN_DIR . 'includes/class-wesender-mailer.php';
require_once WESENDER_PLUGIN_DIR . 'includes/class-wesender-admin.php';

// Auto-update: injects update info so WordPress shows "Update Available" automatically.
// Checks wesender.nl for a newer version; cached 12 hours to avoid excess HTTP calls.
add_filter( 'pre_set_site_transient_update_plugins', 'wesender_inject_update' );

function wesender_inject_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$cached = get_transient( 'wesender_update_info' );
	if ( false === $cached ) {
		$response = wp_remote_get(
			'https://wesender.nl/downloads/wesender-wp-update.json',
			[ 'timeout' => 5, 'sslverify' => true ]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $transient;
		}

		$cached = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $cached || empty( $cached->version ) || empty( $cached->download_url ) ) {
			return $transient;
		}

		set_transient( 'wesender_update_info', $cached, 12 * HOUR_IN_SECONDS );
	}

	if ( version_compare( $cached->version, WESENDER_VERSION, '>' ) ) {
		$transient->response['wesender-wp/wesender-wp.php'] = (object) [
			'id'           => 'wesender-wp/wesender-wp.php',
			'slug'         => 'wesender-wp',
			'plugin'       => 'wesender-wp/wesender-wp.php',
			'new_version'  => $cached->version,
			'url'          => 'https://wesender.nl/apps/wordpress/',
			'package'      => $cached->download_url,
			'icons'        => [],
			'banners'      => [],
			'tested'       => '6.7',
			'requires_php' => '7.4',
		];
	}

	return $transient;
}

function wesender_init(): void {
	$admin = new Wesender_Admin();

	// Run DB upgrade silently on every load if needed.
	if ( (int) get_option( 'wesender_log_db_version', 0 ) < Wesender_Log::DB_VERSION ) {
		Wesender_Log::install();
	}

	if ( get_option( 'wesender_api_key' ) ) {
		new Wesender_Mailer();
	}
}
add_action( 'plugins_loaded', 'wesender_init' );

/**
 * On activation: clean stale plugin entries + directories, install log table.
 */
register_activation_hook( __FILE__, 'wesender_activate' );

function wesender_activate(): void {
	// 1. Clean stale database entries.
	$active = (array) get_option( 'active_plugins', [] );
	$self   = plugin_basename( __FILE__ );

	$cleaned = array_filter( $active, function ( string $slug ) use ( $self ): bool {
		if ( $slug === $self ) {
			return true;
		}
		return strpos( $slug, 'wesender-wp' ) === false;
	} );

	if ( count( $cleaned ) !== count( $active ) ) {
		update_option( 'active_plugins', array_values( $cleaned ) );
	}

	// 2. Delete orphaned wesender-wp* directories.
	$plugins_dir = trailingslashit( WP_PLUGIN_DIR );
	$self_dir    = realpath( plugin_dir_path( __FILE__ ) );

	$entries = @scandir( $plugins_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( $entries ) {
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( 0 !== strpos( $entry, 'wesender-wp' ) ) {
				continue;
			}
			$full_path = realpath( $plugins_dir . $entry );
			if ( ! $full_path || ! is_dir( $full_path ) ) {
				continue;
			}
			if ( $full_path === $self_dir ) {
				continue;
			}
			wesender_rmdir( $full_path );
		}
	}

	// 3. Create / upgrade log table.
	Wesender_Log::install();
}

function wesender_rmdir( string $dir ): void {
	$items = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			wesender_rmdir( $path );
		} else {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
