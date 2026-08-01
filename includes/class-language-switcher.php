<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Language_Switcher {
	private Router $router;
	private array $settings;

	public function __construct( Router $router, array $settings ) {
		$this->router   = $router;
		$this->settings = $settings;
	}

	public function render(): string {
		$languages = $this->router->enabled_languages();
		$current   = $this->router->current_language();
		$format    = (string) ( $this->settings['language_label'] ?? 'native' );
		$position  = (string) ( $this->settings['menu_position'] ?? 'end' );
		$style     = (string) ( $this->settings['menu_style'] ?? 'dropdown' );

		if ( count( $languages ) < 2 ) {
			return '';
		}

		if ( 'inline' === $style ) {
			$items = '';
			foreach ( $languages as $language ) {
				$items .= sprintf(
					'<a class="next-translate-link%s" href="%s" hreflang="%s" lang="%s">%s</a>',
					$language === $current ? ' is-active' : '',
					esc_url( $this->router->language_url( $language ) ),
					esc_attr( $language ),
					esc_attr( $language ),
					esc_html( Language_Catalog::label( $language, $format ) )
				);
			}
			return '<nav id="next-translate-switcher" class="next-translate-switcher is-inline is-' . esc_attr( $position ) . '" aria-label="' . esc_attr__( 'Language switcher', 'localizepilot' ) . '" translate="no">' . $items . '</nav>';
		}

		$items = '';
		foreach ( $languages as $language ) {
			$items .= sprintf(
				'<li><a class="next-translate-link%s" href="%s" hreflang="%s" lang="%s">%s</a></li>',
				$language === $current ? ' is-active' : '',
				esc_url( $this->router->language_url( $language ) ),
				esc_attr( $language ),
				esc_attr( $language ),
				esc_html( Language_Catalog::label( $language, $format ) )
			);
		}

		return sprintf(
			'<nav id="next-translate-switcher" class="next-translate-switcher is-dropdown is-%1$s" aria-label="%2$s" translate="no"><details><summary>%3$s</summary><ul>%4$s</ul></details></nav>',
			esc_attr( $position ),
			esc_attr__( 'Language switcher', 'localizepilot' ),
			esc_html( Language_Catalog::label( $current, $format ) ),
			$items
		);
	}

	public function inject( string $html ): string {
		if ( empty( $this->settings['header_switcher'] ) || false !== strpos( $html, 'id="next-translate-switcher"' ) || false !== strpos( $html, "id='next-translate-switcher'" ) ) {
			return $html;
		}

		$switcher = $this->render();
		if ( '' === $switcher ) {
			return $html;
		}

		$position = stripos( $html, '</header>' );
		if ( false !== $position ) {
			return substr( $html, 0, $position ) . $switcher . substr( $html, $position );
		}

		return preg_replace( '/(<body\b[^>]*>)/i', '$1' . $switcher, $html, 1 ) ?: $html;
	}
}
