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

	/**
	 * Flag emoji for a language, or an empty string when there is no sensible
	 * one.
	 *
	 * A language is not a country: English is not only Britain, Arabic is not
	 * only Saudi Arabia, and Spanish is spoken by far more people outside Spain
	 * than in it. These pairings are a display convention, nothing more, which
	 * is why the switcher only shows them alongside a real language label and
	 * never instead of one.
	 *
	 * Codes with no single defensible flag are deliberately absent and fall
	 * back to no flag rather than a misleading one.
	 */
	public static function flag( string $code ): string {
		$flags = array(
			'ar' => '🇸🇦',
			'bn' => '🇧🇩',
			'da' => '🇩🇰',
			'de' => '🇩🇪',
			'en' => '🇬🇧',
			'es' => '🇪🇸',
			'fr' => '🇫🇷',
			'hi' => '🇮🇳',
			'id' => '🇮🇩',
			'it' => '🇮🇹',
			'ja' => '🇯🇵',
			'ko' => '🇰🇷',
			'nl' => '🇳🇱',
			'no' => '🇳🇴',
			'pl' => '🇵🇱',
			'pt' => '🇵🇹',
			'ru' => '🇷🇺',
			'sv' => '🇸🇪',
			'tr' => '🇹🇷',
			'uk' => '🇺🇦',
			'vi' => '🇻🇳',
			'zh' => '🇨🇳',
		);

		/**
		 * Filter the flag shown for a language.
		 *
		 * The pairings above are one reasonable convention among several. A site
		 * serving American English, Brazilian Portuguese or Latin American
		 * Spanish will want different ones.
		 *
		 * @param string $flag Emoji, or an empty string for no flag.
		 * @param string $code Language code.
		 */
		return (string) apply_filters(
			'localizepilot_language_flag',
			$flags[ strtolower( $code ) ] ?? '',
			strtolower( $code )
		);
	}
}
