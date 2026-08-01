<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

abstract class AI_Client_Base implements Translation_Client_Interface {
	protected array $settings;
	protected string $provider_id;

	public function __construct( array $settings, string $provider_id ) {
		$this->settings    = $settings;
		$this->provider_id = sanitize_key( $provider_id );
	}

	public function test( string $target_language = 'es' ): string {
		$result = $this->translate_batch( array( 'Hello, welcome to our website.' ), $target_language );
		return $result[0] ?? '';
	}

	public function provider(): string {
		return $this->provider_id;
	}

	protected function api_key(): string {
		$field = Provider_Catalog::key_field( $this->provider_id );
		$key   = trim( (string) ( $this->settings[ $field ] ?? '' ) );
		if ( '' === $key ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s is an API provider name. */
					__( '%s API key is missing. Add it in LocalizePilot settings.', 'localizepilot' ),
					Provider_Catalog::label( $this->provider_id )
				)
			);
		}
		return $key;
	}

	protected function model(): string {
		$config = Provider_Catalog::get( $this->provider_id );
		$field  = (string) ( $config['model_field'] ?? '' );
		$model  = '' !== $field ? trim( (string) ( $this->settings[ $field ] ?? '' ) ) : '';
		$model  = '' !== $model ? $model : (string) ( $config['default_model'] ?? '' );
		if ( '' === $model ) {
			throw new \RuntimeException( __( 'An AI model name is required.', 'localizepilot' ) );
		}
		return $model;
	}

	protected function temperature(): float {
		$value = (float) ( $this->settings['ai_temperature'] ?? 0.2 );
		return max( 0.0, min( 1.0, $value ) );
	}

	protected function max_output_tokens(): int {
		return min( 32000, max( 512, absint( $this->settings['ai_max_output_tokens'] ?? 8192 ) ) );
	}

	protected function system_prompt( string $target_language ): string {
		$source_code = strtolower( (string) ( $this->settings['source_language'] ?? 'en' ) );
		$source      = Language_Catalog::label( $source_code, 'english' );
		$target      = Language_Catalog::label( $target_language, 'english' );
		$style       = sanitize_key( (string) ( $this->settings['ai_translation_style'] ?? 'natural' ) );
		$style_map   = array(
			'faithful'  => 'Use a precise and faithful translation. Do not add or remove meaning.',
			'natural'   => 'Use fluent, natural wording suitable for native website visitors.',
			'marketing' => 'Use persuasive but accurate marketing language while preserving claims and intent.',
			'formal'    => 'Use a professional and formal tone.',
		);
		$instructions = trim( (string) ( $this->settings['ai_custom_instructions'] ?? '' ) );

		$prompt = "You are a professional WordPress localization engine. Translate every input item from {$source} ({$source_code}) to {$target} ({$target_language}).\n";
		$prompt .= ( $style_map[ $style ] ?? $style_map['natural'] ) . "\n";
		$prompt .= "Preserve the exact number and order of items. Preserve placeholders, variables, shortcodes, URLs, email addresses, HTML entities, product names, brand names, numbers, punctuation, and formatting tokens. Never translate code. Do not explain the result.\n";
		$prompt .= 'Return valid JSON only, using exactly this structure: {"translations":["translation 1","translation 2"]}.';

		if ( '' !== $instructions ) {
			$prompt .= "\nAdditional localization instructions: " . $instructions;
		}

		return $prompt;
	}

	/**
	 * @param string[] $texts
	 */
	protected function user_prompt( array $texts, string $target_language ): string {
		return (string) wp_json_encode(
			array(
				'source_language' => (string) ( $this->settings['source_language'] ?? 'en' ),
				'target_language' => strtolower( $target_language ),
				'item_count'      => count( $texts ),
				'texts'           => array_values( array_map( 'strval', $texts ) ),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * @return string[]
	 */
	protected function parse_translations( string $raw, int $expected_count ): array {
		$raw = trim( $raw );
		$raw = preg_replace( '/^```(?:json)?\s*|\s*```$/iu', '', $raw ) ?? $raw;
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			$start = strpos( $raw, '{' );
			$end   = strrpos( $raw, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$data = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
			}
		}

		$translations = is_array( $data ) ? ( $data['translations'] ?? null ) : null;
		if ( ! is_array( $translations ) && is_array( $data ) && $this->is_list( $data ) ) {
			$translations = $data;
		}

		if ( ! is_array( $translations ) || count( $translations ) !== $expected_count ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: provider name, 2: expected number, 3: received number. */
					__( '%1$s returned an invalid translation list. Expected %2$d items and received %3$d.', 'localizepilot' ),
					Provider_Catalog::label( $this->provider_id ),
					$expected_count,
					is_array( $translations ) ? count( $translations ) : 0
				)
			);
		}

		$output = array();
		foreach ( $translations as $translation ) {
			if ( is_array( $translation ) ) {
				$translation = $translation['translation'] ?? $translation['translatedText'] ?? '';
			}
			$output[] = (string) $translation;
		}
		return $output;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function request_json( string $endpoint, array $headers, array $body ): array {
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 120,
				'redirection' => 2,
				'headers'     => array_merge(
					array(
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json; charset=utf-8',
					),
					$headers
				),
				'body'        => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$message = $this->error_message( $data, $raw );
			throw new \RuntimeException(
				sprintf( '%s HTTP %d: %s', Provider_Catalog::label( $this->provider_id ), $status, $message )
			);
		}

		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( sprintf( __( '%s returned invalid JSON.', 'localizepilot' ), Provider_Catalog::label( $this->provider_id ) ) );
		}
		return $data;
	}

	private function is_list( array $value ): bool {
		$index = 0;
		foreach ( $value as $key => $unused ) {
			if ( $key !== $index ) {
				return false;
			}
			$index++;
		}
		return true;
	}

	private function error_message( $data, string $raw ): string {
		$message = $raw;
		if ( is_array( $data ) ) {
			$message = $data['error']['message'] ?? $data['error']['type'] ?? $data['message'] ?? $data['detail'] ?? $data['error'] ?? $raw;
		}
		return is_array( $message ) ? (string) wp_json_encode( $message ) : (string) $message;
	}
}
