<?php
/**
 * The page builders LocalizePilot knows about.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Builders;

defined( 'ABSPATH' ) || exit;

final class Builders {
	/**
	 * Every adapter, whether or not its builder is installed.
	 *
	 * @return array<string,Builder>
	 */
	public static function all(): array {
		$builders = array(
			'elementor' => new Elementor_Builder(),
			'bricks'    => new Bricks_Builder(),
		);

		/**
		 * Filter the page builders LocalizePilot can translate.
		 *
		 * Part of add-on API 3. Key each Builder by its id().
		 *
		 * @param array<string,Builder> $builders Builders by id.
		 */
		$builders = (array) apply_filters( 'localizepilot_builders', $builders );

		return array_filter(
			$builders,
			static function ( $builder, $id ): bool {
				return $builder instanceof Builder && $builder->id() === $id;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * The adapters whose builder is actually installed here.
	 *
	 * @return array<string,Builder>
	 */
	public static function available(): array {
		return array_filter(
			self::all(),
			static function ( Builder $builder ): bool {
				return $builder->available();
			}
		);
	}

	/**
	 * The builder a post was built with, if any.
	 *
	 * @param \WP_Post|int $post Post or ID.
	 */
	public static function for_post( $post ): ?Builder {
		$post = get_post( $post );

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		foreach ( self::available() as $builder ) {
			if ( $builder->handles( $post ) ) {
				return $builder;
			}
		}

		return null;
	}

	/**
	 * Content meta keys of every installed builder.
	 *
	 * What the front end serves from a translation instead of the source.
	 *
	 * @return array<int,string>
	 */
	public static function content_keys(): array {
		$keys = array();

		foreach ( self::available() as $builder ) {
			$keys[] = $builder->content_key();
		}

		return $keys;
	}

	/**
	 * Rendered-output meta keys of every installed builder.
	 *
	 * @return array<int,string>
	 */
	public static function cache_keys(): array {
		$keys = array();

		foreach ( self::available() as $builder ) {
			foreach ( $builder->cache_keys() as $key ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Copy a builder's content and settings from one post to another, as they
	 * are — for a migration bringing over a translation someone else made.
	 *
	 * @return bool Whether anything was copied.
	 */
	public static function copy( int $from, int $to ): bool {
		$builder = self::for_post( $from );

		if ( ! $builder ) {
			return false;
		}

		$content = get_post_meta( $from, $builder->content_key(), true );

		if ( empty( $content ) ) {
			return false;
		}

		update_post_meta( $to, $builder->content_key(), is_string( $content ) ? wp_slash( $content ) : $content );

		foreach ( $builder->companion_keys() as $key ) {
			$value = get_post_meta( $from, $key, true );

			if ( '' !== $value && null !== $value ) {
				update_post_meta( $to, $key, is_string( $value ) ? wp_slash( $value ) : $value );
			}
		}

		return true;
	}

	/**
	 * Every piece of text stored in a post's builder content, as plain text.
	 *
	 * For the whole-page translation pass, which must not pay a provider to
	 * translate words a stored translation already holds.
	 *
	 * @return array<int,string>
	 */
	public static function texts( int $post_id ): array {
		$builder = self::for_post( $post_id );

		if ( ! $builder ) {
			return array();
		}

		$tree  = $builder->decode( get_post_meta( $post_id, $builder->content_key(), true ) );
		$texts = array();

		foreach ( $builder->strings( $tree ) as $string ) {
			$value = (string) $string['text'];

			if ( false === strpos( $value, '<' ) ) {
				$texts[] = $value;
				continue;
			}

			foreach ( self::text_nodes( $value ) as $node ) {
				$texts[] = $node;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $texts ) ) ) );
	}

	/**
	 * The words inside a fragment of HTML, without its markup.
	 *
	 * @return array<int,string>
	 */
	private static function text_nodes( string $html ): array {
		if ( ! class_exists( '\DOMDocument' ) ) {
			return array();
		}

		$dom      = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array();
		}

		$texts = array();
		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//text()' );

		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				$value = trim( (string) $node->nodeValue );

				if ( '' !== $value && preg_match( '/[\p{L}\p{N}]/u', $value ) ) {
					$texts[] = $value;
				}
			}
		}

		return $texts;
	}
}
