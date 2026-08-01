<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Google_Translate_Client implements Translation_Client_Interface {
	private array $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param string[] $texts Text strings to translate.
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		$texts = array_values( array_map( 'strval', $texts ) );
		if ( empty( $texts ) ) {
			return array();
		}

		$api_key = trim( (string) ( $this->settings['google_api_key'] ?? '' ) );
		if ( '' === $api_key ) {
			throw new \RuntimeException( esc_html__( 'Google Translation API key is missing. Add it in LocalizePilot settings.', 'localizepilot' ) );
		}

		$endpoint = add_query_arg(
			array( 'key' => $api_key ),
			'https://translation.googleapis.com/language/translate/v2'
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 90,
				'redirection' => 3,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode(
					array(
						'q'      => $texts,
						'source' => (string) ( $this->settings['source_language'] ?? 'en' ),
						'target' => strtolower( $target_language ),
						'format' => 'text',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( sanitize_text_field( $response->get_error_message() ) ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data )
				? ( $data['error']['message'] ?? $data['error'] ?? $raw )
				: $raw;
			if ( is_array( $message ) ) {
				$message = wp_json_encode( $message );
			}
			throw new \RuntimeException(
				esc_html(
					sprintf(
						/* translators: 1: HTTP status code, 2: provider error message. */
						__( 'Google Translation HTTP %1$d: %2$s', 'localizepilot' ),
						$status,
						sanitize_text_field( (string) $message )
					)
				)
			);
		}

		$items = $data['data']['translations'] ?? null;
		if ( ! is_array( $items ) || count( $items ) !== count( $texts ) ) {
			throw new \RuntimeException( esc_html__( 'Google Translation returned an unexpected response.', 'localizepilot' ) );
		}

		$translations = array();
		foreach ( $items as $item ) {
			$value          = is_array( $item ) ? (string) ( $item['translatedText'] ?? '' ) : '';
			$translations[] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return $translations;
	}

	public function test( string $target_language = 'es' ): string {
		$result = $this->translate_batch( array( 'Hello' ), $target_language );
		return $result[0] ?? '';
	}

	public function provider(): string {
		return 'google';
	}
}
