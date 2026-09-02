<?php
/**
 * Template loading and small output helpers for the console.
 *
 * Templates receive one $args array and are responsible for escaping at the
 * point of output. They never query.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Template {
	private const BASE = 'templates/admin/';

	/**
	 * Icons that mean "onward" rather than naming an axis.
	 *
	 * These are the only ones that must point the other way in an RTL admin.
	 * Deciding it here rather than at each call site means a new arrow cannot
	 * be added to a template and quietly stay unmirrored in Arabic.
	 *
	 * @var array<int,string>
	 */
	private const DIRECTIONAL = array( 'arrow-right', 'arrow-right-sm' );

	/**
	 * Absolute path for a template name such as "layout/sidebar".
	 */
	public static function path( string $name ): string {
		$name = ltrim( str_replace( array( '..', "\0" ), '', $name ), '/' );

		return LOCALIZEPILOT_PATH . self::BASE . $name . '.php';
	}

	public static function exists( string $name ): bool {
		return is_readable( self::path( $name ) );
	}

	/**
	 * Render a template.
	 *
	 * @param string              $name Template name relative to templates/admin, without extension.
	 * @param array<string,mixed> $args Values exposed to the template as $args.
	 */
	public static function render( string $name, array $args = array() ): void {
		$file = self::path( $name );

		if ( ! is_readable( $file ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is built from an internal, sanitized template name.
		require $file;
	}

	/**
	 * Render a template to a string, for fragment responses.
	 *
	 * @param array<string,mixed> $args Values exposed to the template as $args.
	 */
	public static function capture( string $name, array $args = array() ): string {
		ob_start();
		self::render( $name, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Inline an icon from assets/console/icons.
	 *
	 * Icons are stroke-only and normalized to currentColor, so one file serves
	 * every state the design uses. Inlining avoids an icon request waterfall
	 * and lets CSS colour them.
	 *
	 * The markup is NOT passed through wp_kses. wp_kses lowercases attribute
	 * names, and inline SVG attributes are case-sensitive — it turns viewBox
	 * into viewbox and preserveAspectRatio into preserveaspectratio, both of
	 * which browsers then ignore, collapsing every icon's coordinate system.
	 * These files are first-party plugin assets loaded from a fixed directory
	 * by a sanitized name, so they are code, not input. sanitize_svg() is
	 * defence in depth against a tampered asset, not user-input escaping.
	 */
	public static function icon( string $name, string $class = 'lp-icon' ): string {
		static $cache = array();

		$name = preg_replace( '/[^a-z0-9-]/', '', strtolower( $name ) ) ?? '';

		if ( '' === $name ) {
			return '';
		}

		if ( ! isset( $cache[ $name ] ) ) {
			$file           = LOCALIZEPILOT_PATH . 'assets/console/icons/' . $name . '.svg';
			$cache[ $name ] = is_readable( $file )
				? self::sanitize_svg( (string) file_get_contents( $file ) ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin asset.
				: '';
		}

		if ( '' === $cache[ $name ] ) {
			return '';
		}

		$svg = str_replace( '<svg', '<svg aria-hidden="true" focusable="false"', $cache[ $name ] );

		if ( in_array( $name, self::DIRECTIONAL, true ) ) {
			$class .= ' lp-icon--directional';
		}

		return '<span class="' . esc_attr( $class ) . '">' . $svg . '</span>';
	}

	/**
	 * Print an inline icon.
	 */
	public static function the_icon( string $name, string $class = 'lp-icon' ): void {
		echo self::icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled first-party SVG; see icon() for why wp_kses cannot be used here.
	}

	/**
	 * Strip anything executable from a bundled SVG while preserving attribute
	 * case, which inline SVG depends on.
	 *
	 * Rejects the file outright if it does not look like an SVG.
	 */
	private static function sanitize_svg( string $svg ): string {
		$svg = trim( $svg );

		if ( '' === $svg || 0 !== strpos( $svg, '<svg' ) ) {
			return '';
		}

		// Executable content has no place in an icon.
		$svg = (string) preg_replace( '#<script\b.*?</script>#is', '', $svg );
		$svg = (string) preg_replace( '#<(script|foreignObject|iframe|embed|object)\b[^>]*/?>#i', '', $svg );
		$svg = (string) preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );
		$svg = (string) preg_replace( '/(href|xlink:href)\s*=\s*("|\')\s*(javascript|data):[^"\']*\2/i', '', $svg );

		return $svg;
	}

}
