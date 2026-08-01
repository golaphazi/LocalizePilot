<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Anthropic_Client extends AI_Client_Base {
	/**
	 * @param string[] $texts
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		$texts = array_values( array_map( 'strval', $texts ) );
		if ( empty( $texts ) ) {
			return array();
		}

		$data = $this->request_json(
			'https://api.anthropic.com/v1/messages',
			array(
				'x-api-key'         => $this->api_key(),
				'anthropic-version' => '2023-06-01',
			),
			array(
				'model'       => $this->model(),
				'max_tokens'  => $this->max_output_tokens(),
				'temperature' => $this->temperature(),
				'system'      => $this->system_prompt( $target_language ),
				'messages'    => array(
					array( 'role' => 'user', 'content' => $this->user_prompt( $texts, $target_language ) ),
				),
			)
		);

		$content = $data['content'] ?? array();
		$text    = '';
		if ( is_array( $content ) ) {
			foreach ( $content as $part ) {
				if ( is_array( $part ) && 'text' === ( $part['type'] ?? '' ) ) {
					$text .= (string) ( $part['text'] ?? '' );
				}
			}
		}
		if ( '' === trim( $text ) ) {
			throw new \RuntimeException( esc_html__( 'Anthropic Claude returned an empty response.', 'localizepilot' ) );
		}
		return $this->parse_translations( $text, count( $texts ) );
	}
}
