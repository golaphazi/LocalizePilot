<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Router {
	private string $current_language = 'en';
	private string $original_request_uri = '/';
	private string $base_path = '/';
	private array $settings = array();
	private bool $detected = false;

	public function detect(): void {
		if ( $this->detected ) {
			return;
		}

		$this->detected = true;
		$this->settings             = Plugin::instance()->get_settings();
		$this->current_language     = (string) ( $this->settings['source_language'] ?? 'en' );
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$this->base_path            = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$this->base_path            = '/' . trim( $this->base_path, '/' );

		if ( '/' !== $this->base_path ) {
			$this->base_path .= '/';
		}

		if ( empty( $this->settings['enabled'] ) || $this->is_system_request( $this->original_request_uri ) ) {
			return;
		}

		$path = (string) wp_parse_url( $this->original_request_uri, PHP_URL_PATH );
		$query = (string) wp_parse_url( $this->original_request_uri, PHP_URL_QUERY );
		$relative = $this->relative_path( $path );
		$had_trailing_slash = '/' === substr( $relative, -1 );
		$segments = array_values( array_filter( explode( '/', trim( $relative, '/' ) ), 'strlen' ) );
		$enabled = $this->enabled_languages();

		if ( isset( $segments[0] ) && in_array( strtolower( $segments[0] ), $enabled, true ) ) {
			$requested = strtolower( array_shift( $segments ) );
			$source    = (string) $this->settings['source_language'];

			if ( $requested !== $source ) {
				$this->current_language = $requested;
			}

			$new_relative = implode( '/', $segments );
			if ( $had_trailing_slash && '' !== $new_relative ) {
				$new_relative .= '/';
			}

			$new_path = $this->join_base_path( $new_relative );
			$_SERVER['REQUEST_URI'] = $new_path . ( '' !== $query ? '?' . $query : '' );

			if ( isset( $_SERVER['PATH_INFO'] ) ) {
				$_SERVER['PATH_INFO'] = $new_path;
			}
		}
	}

	/**
	 * Detect the language prefix immediately before WordPress parses rewrites.
	 *
	 * @param bool  $do_parse Whether WordPress should parse the request.
	 * @param \WP   $wp WordPress environment instance.
	 * @param array $extra_query_vars Additional query variables.
	 * @return bool
	 */
	public function before_parse_request( $do_parse, $wp, $extra_query_vars ) {
		$this->detect();
		return $do_parse;
	}

	public function current_language(): string {
		return $this->current_language;
	}

	public function source_language(): string {
		return (string) ( $this->settings['source_language'] ?? 'en' );
	}

	public function is_translated_request(): bool {
		return $this->current_language() !== $this->source_language();
	}

	/**
	 * @return string[]
	 */
	public function enabled_languages(): array {
		$source   = (string) ( $this->settings['source_language'] ?? 'en' );
		$selected = (array) ( $this->settings['enabled_languages'] ?? array() );
		$languages = array_values( array_unique( array_merge( array( $source ), $selected ) ) );
		return array_values( array_filter( array_map( 'strtolower', $languages ), array( Language_Catalog::class, 'exists' ) ) );
	}

	public function language_url( string $language ): string {
		$this->detect();
		$language = strtolower( $language );
		$path     = (string) wp_parse_url( $this->original_request_uri, PHP_URL_PATH );
		$query    = (string) wp_parse_url( $this->original_request_uri, PHP_URL_QUERY );
		$relative = $this->relative_path( $path );
		$had_trailing_slash = '/' === substr( $relative, -1 );
		$segments = array_values( array_filter( explode( '/', trim( $relative, '/' ) ), 'strlen' ) );
		$enabled  = $this->enabled_languages();

		if ( isset( $segments[0] ) && in_array( strtolower( $segments[0] ), $enabled, true ) ) {
			array_shift( $segments );
		}

		if ( $language !== $this->source_language() ) {
			array_unshift( $segments, $language );
		}

		$relative = implode( '/', $segments );
		if ( $had_trailing_slash && '' !== $relative ) {
			$relative .= '/';
		}

		$url = home_url( '' === $relative ? '/' : '/' . ltrim( $relative, '/' ) );
		if ( '' !== $query ) {
			$url .= '?' . $query;
		}
		return $url;
	}

	public function localize_url( string $url, string $language ): string {
		$this->detect();
		$url = trim( $url );
		if ( '' === $url || '#' === $url[0] || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'javascript:' ) || 0 === strpos( $url, 'data:' ) ) {
			return $url;
		}

		$home       = wp_parse_url( home_url( '/' ) );
		$parsed     = wp_parse_url( $url );
		$is_absolute = isset( $parsed['host'] );

		if ( $is_absolute && isset( $home['host'] ) && strtolower( (string) $parsed['host'] ) !== strtolower( (string) $home['host'] ) ) {
			return $url;
		}

		$path = (string) ( $parsed['path'] ?? '' );
		if ( '' === $path ) {
			return $url;
		}

		if ( $this->is_asset_or_system_path( $path ) ) {
			return $url;
		}

		$relative = $this->relative_path( $path );
		$had_trailing_slash = '/' === substr( $relative, -1 );
		$segments = array_values( array_filter( explode( '/', trim( $relative, '/' ) ), 'strlen' ) );
		$enabled  = $this->enabled_languages();

		if ( isset( $segments[0] ) && in_array( strtolower( $segments[0] ), $enabled, true ) ) {
			array_shift( $segments );
		}

		if ( $language !== $this->source_language() ) {
			array_unshift( $segments, $language );
		}

		$new_relative = implode( '/', $segments );
		if ( $had_trailing_slash && '' !== $new_relative ) {
			$new_relative .= '/';
		}

		$new_path = $this->join_base_path( $new_relative );
		$query    = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		if ( $is_absolute ) {
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
			$host   = $parsed['host'] ?? '';
			$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
			return $scheme . $host . $port . $new_path . $query . $fragment;
		}

		return $new_path . $query . $fragment;
	}

	private function relative_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $this->base_path && 0 === strpos( $path, $this->base_path ) ) {
			return substr( $path, strlen( $this->base_path ) );
		}
		return ltrim( $path, '/' );
	}

	private function join_base_path( string $relative ): string {
		$base = '/' === $this->base_path ? '/' : $this->base_path;
		return $base . ltrim( $relative, '/' );
	}

	private function is_system_request( string $uri ): bool {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return $this->is_asset_or_system_path( $path );
	}

	private function is_asset_or_system_path( string $path ): bool {
		$relative = '/' . ltrim( $this->relative_path( $path ), '/' );
		if ( preg_match( '#^/(wp-admin|wp-login\.php|wp-json|wp-content|wp-includes|xmlrpc\.php)(/|$)#i', $relative ) ) {
			return true;
		}
		return (bool) preg_match( '/\.(?:css|js|mjs|map|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|eot|pdf|zip|xml|json|txt)(?:$|\?)/i', $path );
	}
}
