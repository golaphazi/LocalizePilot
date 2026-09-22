<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Language_Switcher {
	private Router $router;
	private array $settings;
	private static int $instance = 0;

	/** Layouts this class renders itself. */
	private const BUILT_IN_STYLES = array( 'dropdown', 'inline' );

	public function __construct( Router $router, array $settings ) {
		$this->router   = $router;
		$this->settings = $settings;
	}

	/**
	 * Every layout that can be chosen right now.
	 *
	 * Part of add-on API 3: an add-on adds a layout to the
	 * localizepilot_switcher_styles filter and renders it on
	 * localizepilot_switcher_render.
	 *
	 * @return array<int,string>
	 */
	public static function styles(): array {
		return array_values( array_unique( array_merge( self::BUILT_IN_STYLES, array_keys( self::extra_styles() ) ) ) );
	}

	/**
	 * Layouts an add-on provides, with the names to show for them.
	 *
	 * @return array<string,string> Key => label.
	 */
	public static function extra_styles(): array {
		/**
		 * Filter the switcher layouts beyond Dropdown and Inline.
		 *
		 * @param array<string,string> $styles Layout key => label.
		 */
		$extra  = (array) apply_filters( 'localizepilot_switcher_styles', array() );
		$styles = array();

		foreach ( $extra as $key => $label ) {
			$key = sanitize_key( (string) $key );

			if ( '' !== $key && ! in_array( $key, self::BUILT_IN_STYLES, true ) ) {
				$styles[ $key ] = (string) $label;
			}
		}

		return $styles;
	}

	/**
	 * Every place the automatic switcher can go right now.
	 *
	 * Part of add-on API 3: an add-on adds a placement here and puts the
	 * switcher there on localizepilot_switcher_inject.
	 *
	 * @return array<int,string>
	 */
	public static function placements(): array {
		/**
		 * Filter the automatic switcher placements beyond the header.
		 *
		 * @param array<int,string> $placements Extra placement keys.
		 */
		$extra = array_map( 'sanitize_key', (array) apply_filters( 'localizepilot_switcher_placements', array() ) );

		return array_values( array_unique( array_merge( array( 'header' ), array_filter( $extra ) ) ) );
	}

	/**
	 * Render the language switcher.
	 *
	 * Supported overrides:
	 * - style: inherit, dropdown, inline, or a layout an add-on provides
	 * - labels: inherit, native, english, code
	 * - alignment: inherit, start, center, end
	 * - flags: inherit, yes, no
	 * - class: additional CSS classes
	 */
	public function render( array $overrides = array() ): string {
		$languages = $this->router->enabled_languages();
		$current   = $this->router->current_language();

		if ( count( $languages ) < 2 ) {
			return '';
		}

		$styles = self::styles();
		$style  = sanitize_key( (string) ( $overrides['style'] ?? 'inherit' ) );
		if ( 'inherit' === $style || ! in_array( $style, $styles, true ) ) {
			$style = (string) ( $this->settings['menu_style'] ?? 'dropdown' );
		}

		// A stored layout that nothing provides any more — an add-on switched
		// off — falls back to the one layout every site has.
		if ( ! in_array( $style, $styles, true ) ) {
			$style = 'dropdown';
		}

		$format = sanitize_key( (string) ( $overrides['labels'] ?? 'inherit' ) );
		if ( 'inherit' === $format || ! in_array( $format, array( 'native', 'english', 'code' ), true ) ) {
			$format = (string) ( $this->settings['language_label'] ?? 'native' );
		}

		$position = sanitize_key( (string) ( $overrides['alignment'] ?? 'inherit' ) );
		if ( 'inherit' === $position || ! in_array( $position, array( 'start', 'center', 'end' ), true ) ) {
			$position = (string) ( $this->settings['menu_position'] ?? 'end' );
		}

		$flags = sanitize_key( (string) ( $overrides['flags'] ?? 'inherit' ) );
		if ( 'inherit' === $flags || ! in_array( $flags, array( 'yes', 'no' ), true ) ) {
			$show_flags = ! empty( $this->settings['show_flags'] );
		} else {
			$show_flags = 'yes' === $flags;
		}

		/*
		 * A flag decorates the label, it never replaces it: on Windows the flag
		 * glyphs do not exist and fall back to two letters, and a flag alone is
		 * not a language. So this is always emitted alongside the real label and
		 * hidden from assistive tech, which reads the label instead.
		 */
		$flag = static function ( string $code ) use ( $show_flags ): string {
			if ( ! $show_flags ) {
				return '';
			}

			$emoji = Language_Catalog::flag( $code );

			return '' === $emoji
				? ''
				: '<span class="next-translate-flag" aria-hidden="true">' . esc_html( $emoji ) . '</span>';
		};

		$classes = array(
			'next-translate-switcher',
			'localizepilot-language-switcher',
			'is-' . $style,
			'is-' . $position,
		);

		if ( $show_flags ) {
			$classes[] = 'has-flags';
		}

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

		if ( ! in_array( $style, self::BUILT_IN_STYLES, true ) ) {
			$items = array();

			foreach ( $languages as $language ) {
				$items[] = array(
					'code'   => $language,
					'url'    => $this->router->language_url( $language ),
					'label'  => Language_Catalog::label( $language, $format ),
					'flag'   => $flag( $language ),
					'active' => $language === $current,
				);
			}

			/**
			 * Render a layout an add-on provides.
			 *
			 * Everything is already decided — languages, URLs, labels, flags,
			 * and the element's attributes — so the layout only chooses the
			 * markup. Every value in $context is escaped for its purpose
			 * except 'items', whose url and label the renderer must escape.
			 *
			 * @param string              $markup  Empty; return the switcher.
			 * @param string              $style   Layout key.
			 * @param array<string,mixed> $context {attributes, items, current}.
			 */
			$markup = (string) apply_filters(
				'localizepilot_switcher_render',
				'',
				$style,
				array(
					'attributes' => $attributes,
					'items'      => $items,
					'current'    => $current,
				)
			);

			if ( '' !== $markup ) {
				return $markup;
			}

			$style = 'dropdown';
		}

		if ( 'inline' === $style ) {
			$items = '';
			foreach ( $languages as $language ) {
				$items .= sprintf(
					'<a class="next-translate-link%1$s" href="%2$s" hreflang="%3$s" lang="%3$s"%4$s>%5$s<span class="next-translate-name">%6$s</span></a>',
					$language === $current ? ' is-active' : '',
					esc_url( $this->router->language_url( $language ) ),
					esc_attr( $language ),
					$language === $current ? ' aria-current="page"' : '',
					$flag( $language ),
					esc_html( Language_Catalog::label( $language, $format ) )
				);
			}

			return '<nav ' . $attributes . '>' . $items . '</nav>';
		}

		$items = '';
		foreach ( $languages as $language ) {
			$items .= sprintf(
				'<li><a class="next-translate-link%1$s" href="%2$s" hreflang="%3$s" lang="%3$s"%4$s>%5$s<span class="next-translate-name">%6$s</span></a></li>',
				$language === $current ? ' is-active' : '',
				esc_url( $this->router->language_url( $language ) ),
				esc_attr( $language ),
				$language === $current ? ' aria-current="page"' : '',
				$flag( $language ),
				esc_html( Language_Catalog::label( $language, $format ) )
			);
		}

		return sprintf(
			'<nav %1$s><details><summary>%2$s<span class="next-translate-name">%3$s</span></summary><ul>%4$s</ul></details></nav>',
			$attributes,
			$flag( $current ),
			esc_html( Language_Catalog::label( $current, $format ) ),
			$items
		);
	}

	public function inject( string $html ): string {
		if ( empty( $this->settings['header_switcher'] ) ) {
			return $html;
		}

		$placement = sanitize_key( (string) ( $this->settings['switcher_placement'] ?? 'header' ) );

		if ( 'header' !== $placement && in_array( $placement, self::placements(), true ) ) {
			/**
			 * Put the automatic switcher somewhere other than the header.
			 *
			 * Return the page with the switcher added, or null to leave it to
			 * the header — which is also what happens when nothing answers.
			 *
			 * @param string|null       $result    Null.
			 * @param string            $html      The page.
			 * @param string            $placement Placement key.
			 * @param Language_Switcher $switcher  This switcher, to render with.
			 */
			$result = apply_filters( 'localizepilot_switcher_inject', null, $html, $placement, $this );

			if ( is_string( $result ) ) {
				return $result;
			}
		}

		/*
		 * The header only: a theme or page that already placed a switcher —
		 * a shortcode in its header, say — has one there, and a second beside
		 * it would be a duplicate. Floating and footer are somewhere else by
		 * choice, so a manually placed switcher does not cancel them; the
		 * add-on that provides them keeps them from being added twice.
		 */
		if (
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
