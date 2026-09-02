<?php
/**
 * Read model for the Translations screen.
 *
 * The only place that queries the next_translation post type for the console.
 * Returns view-ready arrays; templates never see a WP_Post.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Translation_Manager;

defined( 'ABSPATH' ) || exit;

final class Translation_Repository {
	public const PER_PAGE = 20;

	/**
	 * Query translations for the list table.
	 *
	 * @param array<string,mixed> $filters {language, status, type, search, order, paged}.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function query( array $filters = array() ): array {
		$language = sanitize_key( (string) ( $filters['language'] ?? '' ) );
		$status   = sanitize_key( (string) ( $filters['status'] ?? '' ) );
		$search   = sanitize_text_field( (string) ( $filters['search'] ?? '' ) );
		$order    = 'oldest' === ( $filters['order'] ?? '' ) ? 'ASC' : 'DESC';
		$page     = max( 1, (int) ( $filters['paged'] ?? 1 ) );

		$meta_query = array();

		if ( '' !== $language && Language_Catalog::exists( $language ) ) {
			$meta_query[] = array(
				'key'   => Translation_Manager::META_LANGUAGE,
				'value' => $language,
			);
		}

		if ( '' !== $status && array_key_exists( $status, Translation_Manager::statuses() ) ) {
			$meta_query[] = array(
				'key'   => Translation_Manager::META_STATUS,
				'value' => $status,
			);
		}

		$args = array(
			'post_type'      => Translation_Manager::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => $order,
		);

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded, paginated admin list query.
		}

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = $this->to_row( $post );
		}

		return array(
			'items'    => $items,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => self::PER_PAGE,
		);
	}

	/**
	 * Shape one translation for the table.
	 *
	 * @param \WP_Post $post Translation post.
	 * @return array<string,mixed>
	 */
	private function to_row( \WP_Post $post ): array {
		$language  = sanitize_key( (string) get_post_meta( $post->ID, Translation_Manager::META_LANGUAGE, true ) );
		$status    = sanitize_key( (string) get_post_meta( $post->ID, Translation_Manager::META_STATUS, true ) );
		$source_id = (int) get_post_meta( $post->ID, Translation_Manager::META_SOURCE_ID, true );
		$source    = $source_id ? get_post( $source_id ) : null;
		$statuses  = Translation_Manager::statuses();

		return array(
			'id'           => $post->ID,
			'title'        => $source instanceof \WP_Post ? get_the_title( $source ) : get_the_title( $post ),
			'type'         => $source instanceof \WP_Post ? get_post_type_object( $source->post_type )->labels->singular_name : __( 'Translation', 'localizepilot' ),
			'source_code'  => strtoupper( (string) ( Plugin::instance()->get_settings()['source_language'] ?? 'en' ) ),
			'language'     => $language,
			'language_name' => Language_Catalog::label( $language, 'english' ),
			'status'       => '' !== $status ? $status : 'automatic',
			'status_label' => $statuses[ $status ] ?? $statuses['automatic'],
			'progress'     => $this->completeness( $post ),
			'stale'        => 'needs_update' === $status,
			'updated'      => (string) get_post_modified_time( 'U', true, $post ),
			'edit_url'     => (string) get_edit_post_link( $post->ID, 'raw' ),
			'view_url'     => (string) get_permalink( $post->ID ),
		);
	}

	/**
	 * How complete a translation is, as a percentage.
	 *
	 * The plugin stores a title, content, and excerpt per translation, and a
	 * source hash that says whether those are still current. Completeness is
	 * therefore the share of populated fields, dropped to the stale ceiling
	 * when the source has moved on. This is measured, not estimated — there is
	 * no per-field progress tracking to read.
	 *
	 * @param \WP_Post $post Translation post.
	 */
	private function completeness( \WP_Post $post ): int {
		$source_id = (int) get_post_meta( $post->ID, Translation_Manager::META_SOURCE_ID, true );
		$source    = $source_id ? get_post( $source_id ) : null;

		$fields = array(
			'title'   => '' !== trim( $post->post_title ),
			'content' => '' !== trim( $post->post_content ),
		);

		// Only count the excerpt when the source actually has one to translate.
		if ( $source instanceof \WP_Post && '' !== trim( $source->post_excerpt ) ) {
			$fields['excerpt'] = '' !== trim( $post->post_excerpt );
		}

		$filled = count( array_filter( $fields ) );
		$total  = max( 1, count( $fields ) );

		return (int) round( 100 * $filled / $total );
	}

	/**
	 * Counts for the summary cards.
	 *
	 * @return array<string,int>
	 */
	public function counts(): array {
		$counts = array(
			'total'        => 0,
			'automatic'    => 0,
			'edited'       => 0,
			'reviewed'     => 0,
			'needs_update' => 0,
		);

		$cached = get_transient( 'localizepilot_translation_counts' );

		if ( is_array( $cached ) ) {
			return wp_parse_args( $cached, $counts );
		}

		global $wpdb;

		$counts['total'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','private')",
				Translation_Manager::POST_TYPE
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta.meta_value AS status, COUNT(*) AS total
				 FROM {$wpdb->postmeta} AS meta
				 INNER JOIN {$wpdb->posts} AS posts ON posts.ID = meta.post_id
				 WHERE meta.meta_key = %s
				   AND posts.post_type = %s
				   AND posts.post_status IN ('publish','draft','pending','private')
				 GROUP BY meta.meta_value",
				Translation_Manager::META_STATUS,
				Translation_Manager::POST_TYPE
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$status = sanitize_key( (string) ( $row['status'] ?? '' ) );

			if ( array_key_exists( $status, $counts ) ) {
				$counts[ $status ] = (int) $row['total'];
			}
		}

		set_transient( 'localizepilot_translation_counts', $counts, 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Share of translatable content that has a translation, as a percentage.
	 *
	 * Denominator is published posts and pages multiplied by the enabled
	 * languages, which is what "fully localized" would mean.
	 */
	public function localized_share(): int {
		$settings  = Plugin::instance()->get_settings();
		$languages = count( (array) ( $settings['enabled_languages'] ?? array() ) );

		if ( $languages < 1 ) {
			return 0;
		}

		$sources = 0;
		foreach ( array( 'post', 'page' ) as $type ) {
			$counts   = wp_count_posts( $type );
			$sources += (int) ( $counts->publish ?? 0 );
		}

		$possible = $sources * $languages;

		if ( $possible < 1 ) {
			return 0;
		}

		return (int) min( 100, round( 100 * $this->counts()['total'] / $possible ) );
	}

	/**
	 * The most recently touched translations, for the Overview table.
	 *
	 * @param int $limit Rows to return.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( int $limit = 5 ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => Translation_Manager::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => max( 1, $limit ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		return array_map( array( $this, 'to_row' ), $query->posts );
	}

	/**
	 * Drop the cached counts. Called whenever a translation is saved.
	 */
	public static function flush(): void {
		delete_transient( 'localizepilot_translation_counts' );
		delete_transient( 'localizepilot_language_coverage' );
		Url_Repository::flush();
	}
}
