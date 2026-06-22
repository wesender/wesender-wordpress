<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wesender_Mailer {

	public function __construct() {
		add_filter( 'pre_wp_mail', [ $this, 'send' ], 10, 2 );
	}

	/**
	 * Intercept wp_mail() and route through Wesender.
	 *
	 * Returns true  → wp_mail returns true (sent by us).
	 * Returns false → wp_mail returns false (blocked or failed).
	 * Returns null  → wp_mail continues with its own SMTP send.
	 *
	 * @param null|bool $return
	 * @param array     $atts
	 * @return bool|null
	 */
	public function send( $return, array $atts ) {
		$api_key = get_option( 'wesender_api_key' );
		if ( ! $api_key ) {
			return null;
		}

		$to          = $atts['to'];
		$subject     = $atts['subject'];
		$body        = $atts['message'];
		$headers_raw = $atts['headers'];
		$attachments = $atts['attachments'] ?? [];

		if ( ! is_array( $to ) ) {
			$to = array_map( 'trim', explode( ',', $to ) );
		}

		$source  = $this->detect_source();
		$blocked = (array) get_option( 'wesender_blocked_sources', [] );

		if ( in_array( $source, $blocked, true ) ) {
			Wesender_Log::record( $to, $subject, 'blocked', $source );
			return false;
		}

		$parsed = $this->parse_headers( $headers_raw );

		$html = null;
		$text = null;
		if ( isset( $parsed['content-type'] ) && false !== strpos( strtolower( $parsed['content-type'] ), 'text/html' ) ) {
			$html = $body;
		} else {
			$text = $body;
		}

		$from_email = get_option( 'wesender_from_email', $parsed['from'] ?? get_option( 'admin_email' ) );
		$from_name  = get_option( 'wesender_from_name', get_bloginfo( 'name' ) );
		$from       = $from_name ? "{$from_name} <{$from_email}>" : $from_email;

		$message = array_filter( [
			'from'     => $from,
			'to'       => $to,
			'subject'  => $subject,
			'html'     => $html,
			'text'     => $text,
			'cc'       => $parsed['cc']       ?? null,
			'bcc'      => $parsed['bcc']      ?? null,
			'reply_to' => $parsed['reply-to'] ?? null,
		] );

		if ( ! empty( $attachments ) ) {
			$message['attachments'] = $this->encode_attachments( $attachments );
		}

		$api    = new Wesender_API( $api_key );
		$result = $api->send( $message );

		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
			error_log( 'Wesender: verzenden mislukt - ' . $error );
			Wesender_Log::record( $to, $subject, 'failed', $source, $error );
			return false;
		}

		Wesender_Log::record( $to, $subject, 'sent', $source );
		return true;
	}

	/**
	 * Walk the call stack to find the plugin or theme that triggered wp_mail().
	 */
	private function detect_source(): string {
		$trace       = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$themes_dir  = wp_normalize_path( get_theme_root() );
		$self_dir    = wp_normalize_path( WESENDER_PLUGIN_DIR );

		foreach ( $trace as $frame ) {
			if ( ! isset( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );

			if ( 0 === strpos( $file, $self_dir ) ) {
				continue;
			}

			if ( 0 === strpos( $file, $plugins_dir ) ) {
				$rel   = substr( $file, strlen( $plugins_dir ) + 1 );
				$parts = explode( '/', $rel );
				return $parts[0];
			}

			if ( 0 === strpos( $file, $themes_dir ) ) {
				$rel   = substr( $file, strlen( $themes_dir ) + 1 );
				$parts = explode( '/', $rel );
				return 'theme:' . $parts[0];
			}
		}

		return 'wordpress';
	}

	/**
	 * Parse wp_mail headers (string or array) into a normalized associative array.
	 *
	 * @param string|string[] $headers_raw
	 * @return array
	 */
	private function parse_headers( $headers_raw ): array {
		$parsed = [];

		if ( is_string( $headers_raw ) ) {
			$headers_raw = explode( "\n", str_replace( "\r\n", "\n", $headers_raw ) );
		}

		foreach ( $headers_raw as $header ) {
			if ( false === strpos( $header, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $header, 2 );
			$name  = strtolower( trim( $name ) );
			$value = trim( $value );

			if ( in_array( $name, [ 'cc', 'bcc', 'reply-to' ], true ) ) {
				$parsed[ $name ] = array_map( 'trim', explode( ',', $value ) );
			} else {
				$parsed[ $name ] = $value;
			}
		}

		return $parsed;
	}

	/**
	 * Encode file attachments as base64 for the Wesender API.
	 *
	 * @param string[] $paths  File paths from wp_mail()
	 * @return array
	 */
	private function encode_attachments( array $paths ): array {
		$result = [];

		foreach ( $paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $content ) {
				continue;
			}

			$result[] = [
				'filename'    => basename( $path ),
				'content'     => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'contentType' => mime_content_type( $path ) ?: 'application/octet-stream',
			];
		}

		return $result;
	}
}
