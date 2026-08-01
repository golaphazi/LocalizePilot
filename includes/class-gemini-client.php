<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Gemini_Client extends AI_Client_Base {
	/**
	 * @param string[] $texts
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		$texts = array_values( array_map( 'strval', $texts ) );
		if ( empty( $texts ) ) {
			return array();
		}

		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $this->model() ) . ':generateContent';
		$body = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $this->system_prompt( $target_language ) ) ),
			),
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $this->user_prompt( $texts, $target_language ) ) ),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens'  => $this->max_output_tokens(),
				'responseMimeType' => 'application/json',
			),
		);
		$data  = $this->request_json( $endpoint, array( 'x-goog-api-key' => $this->api_key() ), $body );
		$parts = $data['candidates'][0]['content']['parts'] ?? array();
		$text  = '';
		if ( is_array( $parts ) ) {
			foreach ( $parts as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) ) {
					$text .= (string) $part['text'];
				}
			}
		}
		if ( '' === trim( $text ) ) {
			throw new \RuntimeException( esc_html__( 'Google Gemini returned an empty response.', 'localizepilot' ) );
		}
		return $this->parse_translations( $text, count( $texts ) );
	}
}
