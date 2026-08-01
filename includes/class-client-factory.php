<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Client_Factory {
	public static function make( array $settings, bool $allow_fallback = true ): Translation_Client_Interface {
		$provider = sanitize_key( (string) ( $settings['translation_provider'] ?? 'translatex' ) );
		$provider = Provider_Catalog::exists( $provider ) ? $provider : 'translatex';
		$primary  = self::make_provider( $settings, $provider );

		if ( ! $allow_fallback ) {
			return $primary;
		}

		$fallback = sanitize_key( (string) ( $settings['fallback_provider'] ?? '' ) );
		if ( '' === $fallback || $fallback === $provider || ! Provider_Catalog::exists( $fallback ) ) {
			return $primary;
		}

		return new Fallback_Client( $primary, self::make_provider( $settings, $fallback ) );
	}

	public static function make_provider( array $settings, string $provider ): Translation_Client_Interface {
		$provider = sanitize_key( $provider );
		switch ( $provider ) {
			case 'google':
				return new Google_Translate_Client( $settings );
			case 'gemini':
				return new Gemini_Client( $settings, $provider );
			case 'anthropic':
				return new Anthropic_Client( $settings, $provider );
			case 'openai':
			case 'kimi':
			case 'deepseek':
			case 'mistral':
			case 'groq':
			case 'openrouter':
				return new OpenAI_Compatible_Client( $settings, $provider );
			case 'translatex':
			default:
				return new TranslateX_Client( $settings );
		}
	}
}
