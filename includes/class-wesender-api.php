<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wesender_API {

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Send an email via the WeSender REST API.
	 *
	 * @param array{
	 *   from:         string,
	 *   to:           string|string[],
	 *   subject:      string,
	 *   html?:        string,
	 *   text?:        string,
	 *   cc?:          string[],
	 *   bcc?:         string[],
	 *   reply_to?:    string[],
	 *   attachments?: array<array{filename:string,content:string,contentType:string}>
	 * } $message
	 * @return true|WP_Error
	 */
	public function send( array $message ) {
		$response = wp_remote_post(
			WESENDER_API_BASE . '/emails',
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				],
				'body'    => wp_json_encode( $message ),
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error(
				'wesender_send_failed',
				$body['error'] ?? "WeSender API fout (HTTP {$code})",
				[ 'status' => $code ]
			);
		}

		return true;
	}

	/**
	 * Verify the API key is valid by calling /auth/me.
	 *
	 * @return bool|WP_Error
	 */
	public function verify() {
		$response = wp_remote_get(
			WESENDER_API_BASE . '/auth/me',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
				],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) wp_remote_retrieve_response_code( $response ) === 200;
	}
}
