<?php
/**
 * Read model for the console command search.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Post_Types;
use LocalizePilot\Router;
use LocalizePilot\Translation_Manager;

defined( 'ABSPATH' ) || exit;

final class Global_Search {
	private const LIMIT = 12;

	/**
	 * Search console routes and the WordPress objects represented by them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function results( string $query ): array {
		$query   = mb_substr( sanitize_text_field( $query ), 0, 80 );
		$results = $this->screens( $query );

		if ( '' === $query ) {
			return array_slice( $results, 0, self::LIMIT );
		}

		$results = array_merge(
			$results,
			$this->content( $query ),
			$this->translations( $query ),
			$this->media( $query )
		);

		return array_slice( $results, 0, self::LIMIT );
	}

	/** @return array<int,array<string,mixed>> */
	private function screens( string $query ): array {
		$rows = array();

		foreach ( Screen_Registry::all() as $screen ) {
			if ( ! current_user_can( (string) $screen['capability'] ) ) {
				continue;
			}

			$haystack = (string) $screen['label'] . ' ' . (string) $screen['description'];

			if ( '' !== $query && false === mb_stripos( $haystack, $query ) ) {
				continue;
			}

			$rows[] = array(
				'title'    => (string) $screen['label'],
				'meta'     => (string) $screen['description'],
				'url'      => (string) $screen['url'],
				'icon'     => (string) $screen['icon'],
				'external' => false,
			);
		}

		return $rows;
	}

	/** @return array<int,array<string,mixed>> */
	private function content( string $query ): array {
		$types = Post_Types::translatable();

		// get_posts() with no post type searches posts only, which would be a
		// wrong answer rather than no answer.
		if ( empty( $types ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				's'              => $query,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return array_map(
			static function ( \WP_Post $post ): array {
				$object = get_post_type_object( $post->post_type );

				return array(
					'title'    => get_the_title( $post ),
					'meta'     => $object ? (string) $object->labels->singular_name : __( 'Content', 'localizepilot' ),
					'url'      => (string) get_permalink( $post ),
					'icon'     => 'nav-seo-urls',
					'external' => true,
				);
			},
			$posts
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function translations( string $query ): array {
		$posts = get_posts(
			array(
				'post_type'      => Translation_Manager::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 5,
				's'              => $query,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$router = new Router();
		$rows   = array();

		foreach ( $posts as $post ) {
			$source_id = absint( get_post_meta( $post->ID, Translation_Manager::META_SOURCE_ID, true ) );
			$language  = sanitize_key( (string) get_post_meta( $post->ID, Translation_Manager::META_LANGUAGE, true ) );
			$source    = $source_id ? get_post( $source_id ) : null;

			if ( ! $source instanceof \WP_Post || '' === $language ) {
				continue;
			}

			$rows[] = array(
				'title'    => get_the_title( $source ),
				'meta'     => sprintf(
					/* translators: %s is a language name. */
					__( '%s translation', 'localizepilot' ),
					Language_Catalog::label( $language, 'english' )
				),
				'url'      => $router->localize_url( (string) get_permalink( $source ), $language ),
				'icon'     => 'nav-translations',
				'external' => true,
			);
		}

		return $rows;
	}

	/** @return array<int,array<string,mixed>> */
	private function media( string $query ): array {
		$posts = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 4,
				's'              => $query,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return array_map(
			static function ( \WP_Post $post ): array {
				return array(
					'title'    => get_the_title( $post ),
					'meta'     => __( 'Media', 'localizepilot' ),
					'url'      => (string) get_edit_post_link( $post->ID, 'raw' ),
					'icon'     => 'nav-media',
					'external' => false,
				);
			},
			$posts
		);
	}
}
