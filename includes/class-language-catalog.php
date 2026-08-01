<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Language_Catalog {
	/**
	 * @return array<string, array{name:string,native:string,rtl:bool}>
	 */
	public static function all(): array {
		return array(
		'en' => array( 'name' => 'English', 'native' => 'English', 'rtl' => false ),
		'ar' => array( 'name' => 'Arabic', 'native' => 'العربية', 'rtl' => true ),
		'bn' => array( 'name' => 'Bengali', 'native' => 'বাংলা', 'rtl' => false ),
		'da' => array( 'name' => 'Danish', 'native' => 'Dansk', 'rtl' => false ),
		'de' => array( 'name' => 'German', 'native' => 'Deutsch', 'rtl' => false ),
		'es' => array( 'name' => 'Spanish', 'native' => 'Español', 'rtl' => false ),
		'fr' => array( 'name' => 'French', 'native' => 'Français', 'rtl' => false ),
		'hi' => array( 'name' => 'Hindi', 'native' => 'हिन्दी', 'rtl' => false ),
		'id' => array( 'name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'rtl' => false ),
		'it' => array( 'name' => 'Italian', 'native' => 'Italiano', 'rtl' => false ),
		'ja' => array( 'name' => 'Japanese', 'native' => '日本語', 'rtl' => false ),
		'ko' => array( 'name' => 'Korean', 'native' => '한국어', 'rtl' => false ),
		'nl' => array( 'name' => 'Dutch', 'native' => 'Nederlands', 'rtl' => false ),
		'no' => array( 'name' => 'Norwegian', 'native' => 'Norsk', 'rtl' => false ),
		'pl' => array( 'name' => 'Polish', 'native' => 'Polski', 'rtl' => false ),
		'pt' => array( 'name' => 'Portuguese', 'native' => 'Português', 'rtl' => false ),
		'ru' => array( 'name' => 'Russian', 'native' => 'Русский', 'rtl' => false ),
		'sv' => array( 'name' => 'Swedish', 'native' => 'Svenska', 'rtl' => false ),
		'tr' => array( 'name' => 'Turkish', 'native' => 'Türkçe', 'rtl' => false ),
		'uk' => array( 'name' => 'Ukrainian', 'native' => 'Українська', 'rtl' => false ),
		'vi' => array( 'name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'rtl' => false ),
		'zh' => array( 'name' => 'Chinese', 'native' => '中文', 'rtl' => false ),
		);
	}

	public static function exists( string $code ): bool {
		return isset( self::all()[ strtolower( $code ) ] );
	}

	public static function is_rtl( string $code ): bool {
		$languages = self::all();
		$code      = strtolower( $code );
		return ! empty( $languages[ $code ]['rtl'] );
	}

	public static function label( string $code, string $format = 'native' ): string {
		$languages = self::all();
		$code      = strtolower( $code );

		if ( ! isset( $languages[ $code ] ) ) {
			return strtoupper( $code );
		}

		if ( 'code' === $format ) {
			return strtoupper( $code );
		}

		if ( 'english' === $format ) {
			return $languages[ $code ]['name'];
		}

		return $languages[ $code ]['native'];
	}
}
