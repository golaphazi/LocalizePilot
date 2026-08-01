<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Provider_Catalog {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		return array(
			'translatex' => array(
				'label'         => 'TranslateX',
				'mark'          => 'TX',
				'description'   => 'Dedicated text batch translation API',
				'key_field'     => 'translatex_api_key',
				'model_field'   => '',
				'default_model' => '',
				'kind'          => 'translation',
			),
			'google' => array(
				'label'         => 'Google Translation',
				'mark'          => 'GT',
				'description'   => 'Google Cloud Translation Basic v2',
				'key_field'     => 'google_api_key',
				'model_field'   => '',
				'default_model' => '',
				'kind'          => 'translation',
			),
			'openai' => array(
				'label'         => 'OpenAI',
				'mark'          => 'AI',
				'description'   => 'ChatGPT models through the OpenAI API',
				'key_field'     => 'openai_api_key',
				'model_field'   => 'openai_model',
				'default_model' => 'gpt-4.1-mini',
				'kind'          => 'ai',
				'endpoint'      => 'https://api.openai.com/v1/chat/completions',
			),
			'gemini' => array(
				'label'         => 'Google Gemini',
				'mark'          => 'GM',
				'description'   => 'Gemini generative language models',
				'key_field'     => 'gemini_api_key',
				'model_field'   => 'gemini_model',
				'default_model' => 'gemini-3.6-flash',
				'kind'          => 'ai',
			),
			'anthropic' => array(
				'label'         => 'Anthropic Claude',
				'mark'          => 'CL',
				'description'   => 'Claude multilingual models',
				'key_field'     => 'anthropic_api_key',
				'model_field'   => 'anthropic_model',
				'default_model' => 'claude-haiku-4-5',
				'kind'          => 'ai',
			),
			'kimi' => array(
				'label'         => 'Kimi',
				'mark'          => 'KM',
				'description'   => 'Moonshot AI Kimi models',
				'key_field'     => 'kimi_api_key',
				'model_field'   => 'kimi_model',
				'default_model' => 'kimi-k2.6',
				'kind'          => 'ai',
				'endpoint'      => 'https://api.moonshot.ai/v1/chat/completions',
			),
			'deepseek' => array(
				'label'         => 'DeepSeek',
				'mark'          => 'DS',
				'description'   => 'DeepSeek multilingual language models',
				'key_field'     => 'deepseek_api_key',
				'model_field'   => 'deepseek_model',
				'default_model' => 'deepseek-v4-flash',
				'kind'          => 'ai',
				'endpoint'      => 'https://api.deepseek.com/chat/completions',
			),
			'mistral' => array(
				'label'         => 'Mistral AI',
				'mark'          => 'MI',
				'description'   => 'Mistral multilingual chat models',
				'key_field'     => 'mistral_api_key',
				'model_field'   => 'mistral_model',
				'default_model' => 'mistral-small-latest',
				'kind'          => 'ai',
				'endpoint'      => 'https://api.mistral.ai/v1/chat/completions',
			),
			'groq' => array(
				'label'         => 'Groq',
				'mark'          => 'GQ',
				'description'   => 'Fast OpenAI-compatible model inference',
				'key_field'     => 'groq_api_key',
				'model_field'   => 'groq_model',
				'default_model' => 'llama-3.3-70b-versatile',
				'kind'          => 'ai',
				'endpoint'      => 'https://api.groq.com/openai/v1/chat/completions',
			),
			'openrouter' => array(
				'label'         => 'OpenRouter',
				'mark'          => 'OR',
				'description'   => 'Use many popular AI models through one API',
				'key_field'     => 'openrouter_api_key',
				'model_field'   => 'openrouter_model',
				'default_model' => 'openai/gpt-4.1-mini',
				'kind'          => 'ai',
				'endpoint'      => 'https://openrouter.ai/api/v1/chat/completions',
			),
		);
	}

	public static function exists( string $provider ): bool {
		return isset( self::all()[ sanitize_key( $provider ) ] );
	}

	/** @return array<string,mixed> */
	public static function get( string $provider ): array {
		$providers = self::all();
		$provider  = sanitize_key( $provider );
		return $providers[ $provider ] ?? $providers['translatex'];
	}

	public static function label( string $provider ): string {
		$config = self::get( $provider );
		return (string) $config['label'];
	}

	public static function is_ai( string $provider ): bool {
		return 'ai' === (string) ( self::get( $provider )['kind'] ?? '' );
	}

	public static function key_field( string $provider ): string {
		return (string) ( self::get( $provider )['key_field'] ?? '' );
	}

	public static function model_field( string $provider ): string {
		return (string) ( self::get( $provider )['model_field'] ?? '' );
	}
}
