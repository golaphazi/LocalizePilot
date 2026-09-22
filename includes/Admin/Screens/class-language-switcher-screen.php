<?php
/**
 * Language Switcher console screen.
 *
 * Four of the design's controls map onto settings that already exist and are
 * already sanitised: whether the switcher shows in the header, its layout, its
 * label format, and its alignment. Everything else the design shows — flags,
 * a Button layout, drag-to-reorder, floating and footer placement, the four
 * Advanced Behavior switches — has no backend, so it goes through Preview.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Settings_Model;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Language_Switcher;
use LocalizePilot\Plugin;
use LocalizePilot\Router;

defined( 'ABSPATH' ) || exit;

final class Language_Switcher_Screen extends Abstract_Screen {
	/**
	 * Flag emoji for display only.
	 *
	 * A language is not a country, so these are a presentational convention
	 * rather than a fact about the language. Codes with no obvious single flag
	 * are deliberately absent and fall back to no flag at all.
	 *
	 * @var array<string,string>
	 */
	private const FLAGS = array(
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
		'th' => '🇹🇭',
		'tr' => '🇹🇷',
		'vi' => '🇻🇳',
		'zh' => '🇨🇳',
	);

	public function slug(): string {
		return 'language-switcher';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array( $this->view_site_action() );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$model     = new Settings_Model();
		$settings  = $model->settings();
		$languages = $this->with_flags( $model->languages() );
		$enabled   = array_values( array_filter( $languages, static fn( array $l ): bool => empty( $l['is_source'] ) ) );
		$in_header = ! empty( $settings['header_switcher'] );

		/*
		 * Stored and effective can differ: a placement the add-on provided
		 * stays stored after the add-on goes, and the switcher falls back to
		 * the header meanwhile. The screen shows where it actually is.
		 */
		$stored    = sanitize_key( (string) ( $settings['switcher_placement'] ?? 'header' ) );
		$effective = in_array( $stored, Language_Switcher::placements(), true ) ? $stored : 'header';

		$labels = array(
			'header'   => array( __( 'Header', 'localizepilot' ), __( 'Top navigation area', 'localizepilot' ) ),
			'floating' => array( __( 'Floating', 'localizepilot' ), __( 'Pinned to the corner while scrolling', 'localizepilot' ) ),
			'footer'   => array( __( 'Footer', 'localizepilot' ), __( 'At the bottom of every page', 'localizepilot' ) ),
		);

		return array(
			'settings'   => $settings,
			'languages'  => $languages,
			'visitor_languages' => count( $languages ),
			'enabled_count'     => count( $enabled ),
			'in_header'  => $in_header,
			'placement_stored'    => $stored,
			'placement_effective' => $effective,
			'placement'  => $in_header
				? array(
					'label' => $labels[ $effective ][0] ?? $labels['header'][0],
					'note'  => $labels[ $effective ][1] ?? $labels['header'][1],
				)
				: array(
					'label' => __( 'Shortcode', 'localizepilot' ),
					'note'  => __( 'Placed manually on your site', 'localizepilot' ),
				),
			'preview'    => $this->preview(),
			'shortcode'  => '[localizepilot_switcher]',
			'advanced_shortcode' => '[localizepilot_switcher style="inline" labels="native" alignment="center"]',
			'languages_url'      => Screen_Registry::url( 'languages' ),
			'base_url'   => Screen_Registry::url( $this->slug() ),
		);
	}

	/**
	 * The real switcher markup, so the preview shows what visitors will see
	 * rather than a drawing of it.
	 */
	private function preview(): string {
		$settings = wp_parse_args( Plugin::instance()->get_settings(), Plugin::defaults() );
		$router   = new Router();

		/*
		 * Two things have to be arranged before this renders usefully.
		 *
		 * A fresh Router holds no settings until it detects, and without them
		 * it reports a single language and the switcher renders nothing.
		 *
		 * And the Router builds its links from the current REQUEST_URI, which
		 * in the admin is an admin URL — different again when the SPA fetches
		 * this screen as a fragment, which would make the same screen render
		 * two different previews. Pinning it to the site root gives the visitor
		 * view the preview is meant to show, identically every time.
		 */
		$original = $_SERVER['REQUEST_URI'] ?? null;
		$home     = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		$_SERVER['REQUEST_URI'] = '' !== $home ? $home : '/';

		try {
			$router->detect();

			return ( new Language_Switcher( $router, $settings ) )->render(
				array( 'id' => 'localizepilot-switcher-preview' )
			);
		} finally {
			if ( null === $original ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original;
			}
		}
	}

	/**
	 * @param array<int,array<string,string>> $languages Rows from the model.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function with_flags( array $languages ): array {
		foreach ( $languages as $index => $language ) {
			$code = sanitize_key( (string) ( $language['code'] ?? '' ) );

			$languages[ $index ]['flag'] = self::FLAGS[ $code ] ?? '';
			$languages[ $index ]['rtl']  = Language_Catalog::is_rtl( $code ) ? '1' : '';
		}

		return $languages;
	}
}
