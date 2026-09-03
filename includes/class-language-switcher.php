<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Language_Switcher {
	private Router $router;
	private array $settings;
	private static int $instance = 0;

	public function __construct( Router $router, array $settings ) {
		$this->router   = $router;
		$this->settings = $settings;
	}

	/**
	 * Render the language switcher.
	 *
	 * Supported overrides:
	 * - style: inherit, dropdown, inline
	 * - labels: inherit, native, english, code
	 * - alignment: inherit, start, center, end
	 * - class: additional CSS classes
	 */
	public function render( array $overrides = array() ): string {
		$languages = $this->router->enabled_languages();
		$current   = $this->router->current_language();

		if ( count( $languages ) < 2 ) {
			return '';
		}

		$style = sanitize_key( (string) ( $overrides['style'] ?? 'inherit' ) );
		if ( 'inherit' === $style || ! in_array( $style, array( 'dropdown', 'inline' ), true ) ) {
			$style = (string) ( $this->settings['menu_style'] ?? 'dropdown' );
		}

		$format = sanitize_key( (string) ( $overrides['labels'] ?? 'inherit' ) );
		if ( 'inherit' === $format || ! in_array( $format, array( 'native', 'english', 'code' ), true ) ) {
			$format = (string) ( $this->settings['language_label'] ?? 'native' );
		}

		$position = sanitize_key( (string) ( $overrides['alignment'] ?? 'inherit' ) );
		if ( 'inherit' === $position || ! in_array( $position, array( 'start', 'center', 'end' ), true ) ) {
			$position = (string) ( $this->settings['menu_position'] ?? 'end' );
		}

		$classes = array(
			'next-translate-switcher',
			'localizepilot-language-switcher',
			'is-' . $style,
			'is-' . $position,
		);

		$custom_class = trim( (string) ( $overrides['class'] ?? '' ) );
		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) ?: array() as $class_name ) {
				$class_name = sanitize_html_class( $class_name );
				if ( '' !== $class_name ) {
					$classes[] = $class_name;
				}
			}
		}

		/*
		 * The counter keeps ids unique when a page renders more than one
		 * switcher. A caller that renders exactly one — the console's live
		 * preview — can pin the id instead, so the same screen produces the
		 * same markup on a full load and on a client-side fragment fetch.
		 */
		$switcher_id = sanitize_html_class( (string) ( $overrides['id'] ?? '' ) );

		if ( '' === $switcher_id ) {
			self::$instance++;
			$switcher_id = 'localizepilot-switcher-' . self::$instance;
		}
		$attributes  = sprintf(
			'id="%1$s" class="%2$s" aria-label="%3$s" data-localizepilot-switcher="1" translate="no"',
			esc_attr( $switcher_id ),
			esc_attr( implode( ' ', array_unique( $classes ) ) ),
			esc_attr__( 'Language switcher', 'localizepilot' )
		);

		if ( 'inline' === $style ) {
			$items = '';
			foreach ( $languages as $language ) {
				$items .= sprintf(
					'<a class="next-translate-link%1$s" href="%2$s" hreflang="%3$s" lang="%3$s"%4$s>%5$s</a>',
					$language === $current ? ' is-active' : '',
					esc_url( $this->router->language_url( $language ) ),
					esc_attr( $language ),
					$language === $current ? ' aria-current="page"' : '',
					esc_html( Language_Catalog::label( $language, $format ) )
				);
			}

			return '<nav ' . $attributes . '>' . $items . '</nav>';
		}

		$items = '';
		foreach ( $languages as $language ) {
			$items .= sprintf(
				'<li><a class="next-translate-link%1$s" href="%2$s" hreflang="%3$s" lang="%3$s"%4$s>%5$s</a></li>',
				$language === $current ? ' is-active' : '',
				esc_url( $this->router->language_url( $language ) ),
				esc_attr( $language ),
				$language === $current ? ' aria-current="page"' : '',
				esc_html( Language_Catalog::label( $language, $format ) )
			);
		}

		return sprintf(
			'<nav %1$s><details><summary>%2$s</summary><ul>%3$s</ul></details></nav>',
			$attributes,
			esc_html( Language_Catalog::label( $current, $format ) ),
			$items
		);
	}

	public function inject( string $html ): string {
		if (
			empty( $this->settings['header_switcher'] ) ||
			false !== strpos( $html, 'data-localizepilot-switcher="1"' ) ||
			false !== strpos( $html, "data-localizepilot-switcher='1'" ) ||
			false !== strpos( $html, 'id="next-translate-switcher"' ) ||
			false !== strpos( $html, "id='next-translate-switcher'" )
		) {
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
