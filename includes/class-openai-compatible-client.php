<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class OpenAI_Compatible_Client extends AI_Client_Base {
	/**
	 * @param string[] $texts
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		$texts = array_values( array_map( 'strval', $texts ) );
		if ( empty( $texts ) ) {
			return array();
		}

		$config   = Provider_Catalog::get( $this->provider_id );
		$endpoint = (string) ( $config['endpoint'] ?? '' );
		if ( '' === $endpoint ) {
			throw new \RuntimeException( __( 'The selected AI provider endpoint is missing.', 'localizepilot' ) );
		}

		$headers = array( 'Authorization' => 'Bearer ' . $this->api_key() );
		if ( 'openrouter' === $this->provider_id ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = get_bloginfo( 'name' ) . ' - LocalizePilot';
		}

		$body = array(
			'model'       => $this->model(),
			'messages'    => array(
				array( 'role' => 'system', 'content' => $this->system_prompt( $target_language ) ),
				array( 'role' => 'user', 'content' => $this->user_prompt( $texts, $target_language ) ),
			),
			'temperature' => $this->temperature(),
			'stream'      => false,
		);

		if ( 'openai' === $this->provider_id ) {
			$body['max_completion_tokens'] = $this->max_output_tokens();
		} else {
			$body['max_tokens'] = $this->max_output_tokens();
		}

		if ( in_array( $this->provider_id, array( 'openai', 'deepseek', 'mistral', 'groq', 'openrouter' ), true ) ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}
		if ( 'kimi' === $this->provider_id ) {
			$body['thinking'] = array( 'type' => 'disabled' );
		}

		$data    = $this->request_json( $endpoint, $headers, $body );
		$content = $data['choices'][0]['message']['content'] ?? '';
		if ( is_array( $content ) ) {
			$parts = array();
			foreach ( $content as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) ) {
					$parts[] = (string) $part['text'];
				}
			}
			$content = implode( '', $parts );
		}
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			/* translators: %s is the selected AI provider name. */
			throw new \RuntimeException( sprintf( __( '%s returned an empty response.', 'localizepilot' ), Provider_Catalog::label( $this->provider_id ) ) );
		}
		return $this->parse_translations( $content, count( $texts ) );
	}
}
