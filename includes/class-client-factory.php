<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Client_Factory {
	public static function make( array $settings ): Translation_Client_Interface {
		$provider = sanitize_key( (string) ( $settings['translation_provider'] ?? 'translatex' ) );

		if ( 'google' === $provider ) {
			return new Google_Translate_Client( $settings );
		}

		return new TranslateX_Client( $settings );
	}
}
