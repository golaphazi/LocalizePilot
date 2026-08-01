<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class TranslateX_Client implements Translation_Client_Interface {
	private array $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param string[] $texts
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		$texts = array_values( array_map( 'strval', $texts ) );
		if ( empty( $texts ) ) {
			return array();
		}

		$api_key = trim( (string) ( $this->settings['translatex_api_key'] ?? '' ) );
		if ( '' === $api_key ) {
			throw new \RuntimeException( __( 'TranslateX API key is missing. Add it in LocalizePilot settings.', 'localizepilot' ) );
		}

		$endpoint = add_query_arg(
			array(
				'sl'  => (string) ( $this->settings['source_language'] ?? 'en' ),
				'tl'  => strtolower( $target_language ),
				'key' => $api_key,
			),
			'https://api.translatex.com/translate'
		);

		$parts = array();
		foreach ( $texts as $text ) {
			$parts[] = 'text=' . rawurlencode( $text );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 90,
				'redirection' => 3,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'        => implode( '&', $parts ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) ? ( $data['err'] ?? $data['error'] ?? $data['message'] ?? $raw ) : $raw;
			if ( is_array( $message ) ) {
				$message = wp_json_encode( $message );
			}
			throw new \RuntimeException( sprintf( 'TranslateX HTTP %d: %s', $status, (string) $message ) );
		}

		if ( ! is_array( $data ) || ! isset( $data['translation'] ) ) {
			throw new \RuntimeException( __( 'TranslateX returned an invalid response.', 'localizepilot' ) );
		}

		$translations = $data['translation'];
		if ( is_string( $translations ) ) {
			$translations = array( $translations );
		}

		if ( ! is_array( $translations ) || count( $translations ) !== count( $texts ) ) {
			throw new \RuntimeException( __( 'TranslateX returned an unexpected number of translations.', 'localizepilot' ) );
		}

		return array_values( array_map( 'strval', $translations ) );
	}

	public function test( string $target_language = 'es' ): string {
		$result = $this->translate_batch( array( 'Hello' ), $target_language );
		return $result[0] ?? '';
	}

	public function provider(): string {
		return 'translatex';
	}
}
