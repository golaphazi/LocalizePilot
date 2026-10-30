<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class DeepL_Client implements Translation_Client_Interface {
	private array $settings;

	/**
	 * DeepL target codes that differ from a plain uppercased language code.
	 * Source-language codes never need the regional suffix, only targets do.
	 */
	private const TARGET_OVERRIDES = array(
		'en' => 'EN-US',
		'pt' => 'PT-PT',
		'no' => 'NB',
		'zh' => 'ZH-HANS',
	);

	private const SOURCE_OVERRIDES = array(
		'no' => 'NB',
	);

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

		$api_key = trim( (string) ( $this->settings['deepl_api_key'] ?? '' ) );
		if ( '' === $api_key ) {
			throw new \RuntimeException( esc_html__( 'DeepL API key is missing. Add it in LocalizePilot settings.', 'localizepilot' ) );
		}

		$source = strtolower( (string) ( $this->settings['source_language'] ?? 'en' ) );

		$response = wp_remote_post(
			$this->endpoint( $api_key ),
			array(
				'timeout'     => 90,
				'redirection' => 3,
				'headers'     => array(
					'Authorization' => 'DeepL-Auth-Key ' . $api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode(
					array(
						'text'        => $texts,
						'source_lang' => $this->deepl_code( $source, self::SOURCE_OVERRIDES ),
						'target_lang' => $this->deepl_code( strtolower( $target_language ), self::TARGET_OVERRIDES ),
						'tag_handling' => 'html',
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
			$message = is_array( $data ) ? ( $data['message'] ?? $raw ) : $raw;
			if ( is_array( $message ) ) {
				$message = wp_json_encode( $message );
			}
			throw new \RuntimeException(
				esc_html(
					sprintf(
						/* translators: 1: HTTP status code, 2: provider error message. */
						__( 'DeepL HTTP %1$d: %2$s', 'localizepilot' ),
						$status,
						sanitize_text_field( (string) $message )
					)
				)
			);
		}

		$items = $data['translations'] ?? null;
		if ( ! is_array( $items ) || count( $items ) !== count( $texts ) ) {
			throw new \RuntimeException( esc_html__( 'DeepL returned an unexpected response.', 'localizepilot' ) );
		}

		$translations = array();
		foreach ( $items as $item ) {
			$value          = is_array( $item ) ? (string) ( $item['text'] ?? '' ) : '';
			$translations[] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return $translations;
	}

	public function test( string $target_language = 'es' ): string {
		$result = $this->translate_batch( array( 'Hello' ), $target_language );
		return $result[0] ?? '';
	}

	public function provider(): string {
		return 'deepl';
	}

	/**
	 * DeepL Free keys end in ":fx" and must use the free API host; everything
	 * else — Pro keys — uses the standard host.
	 */
	private function endpoint( string $api_key ): string {
		$is_free = ( strlen( $api_key ) > 3 && ':fx' === substr( $api_key, -3 ) );
		return $is_free
			? 'https://api-free.deepl.com/v2/translate'
			: 'https://api.deepl.com/v2/translate';
	}

	private function deepl_code( string $code, array $overrides ): string {
		if ( '' === $code ) {
			return $code;
		}
		return $overrides[ $code ] ?? strtoupper( $code );
	}
}
