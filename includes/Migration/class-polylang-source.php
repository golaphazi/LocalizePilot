<?php
/**
 * Polylang's translations.
 *
 * Polylang links translations through a "post_translations" term per group,
 * whose description is a serialized map of language slug => post ID, and
 * describes each language in a "language" term whose description carries its
 * locale. Both are read with SQL: once Polylang is deactivated its taxonomies
 * are no longer registered, but the rows are all still there.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Migration;

defined( 'ABSPATH' ) || exit;

final class Polylang_Source extends Source {
	/** @var array<string,array{posts:int,locale:string}>|null */
	private ?array $languages = null;

	public function id(): string {
		return 'polylang';
	}

	public function label(): string {
		return 'Polylang';
	}

	public function detected(): bool {
		return $this->count() > 0;
	}

	public function count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'post_translations'" );
	}

	/**
	 * Every group in the range, single-post ones included.
	 *
	 * Filtering those out here would let a stretch of them fill a whole batch
	 * and come back empty, which reads as "nothing left". The migrator passes
	 * over them instead.
	 */
	public function groups( int $after, int $limit ): array {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_taxonomy_id, description FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'post_translations' AND term_taxonomy_id > %d ORDER BY term_taxonomy_id ASC LIMIT %d",
				$after,
				max( 1, $limit )
			),
			ARRAY_A
		);

		$groups = array();

		foreach ( $rows as $row ) {
			$groups[] = array(
				'key'     => (int) $row['term_taxonomy_id'],
				'members' => self::members( (string) $row['description'] ),
			);
		}

		return $groups;
	}

	public function languages(): array {
		if ( null !== $this->languages ) {
			return $this->languages;
		}

		global $wpdb;

		$this->languages = array();

		$rows = (array) $wpdb->get_results(
			"SELECT terms.slug, tt.count, tt.description FROM {$wpdb->terms} AS terms INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = terms.term_id WHERE tt.taxonomy = 'language' ORDER BY tt.count DESC",
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$details = self::decode( (string) $row['description'] );

			$this->languages[ (string) $row['slug'] ] = array(
				'posts'  => (int) $row['count'],
				'locale' => (string) ( $details['locale'] ?? '' ),
			);
		}

		return $this->languages;
	}

	public function post_types(): array {
		global $wpdb;

		$ids = array();

		foreach ( (array) $wpdb->get_col( "SELECT description FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'post_translations'" ) as $description ) {
			foreach ( self::members( (string) $description ) as $post_id ) {
				$ids[] = $post_id;
			}
		}

		$types = array();

		foreach ( array_chunk( array_unique( $ids ), 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per ID.
					"SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) AND post_type <> 'attachment' GROUP BY post_type",
					$chunk
				),
				ARRAY_A
			);

			foreach ( $rows as $row ) {
				$types[ (string) $row['post_type'] ] = ( $types[ (string) $row['post_type'] ] ?? 0 ) + (int) $row['total'];
			}
		}

		arsort( $types );

		return $types;
	}

	/**
	 * A group's language slug => post ID map.
	 *
	 * @return array<string,int>
	 */
	private static function members( string $description ): array {
		$members = array();

		foreach ( self::decode( $description ) as $slug => $post_id ) {
			if ( is_string( $slug ) && absint( $post_id ) > 0 ) {
				$members[ $slug ] = absint( $post_id );
			}
		}

		return $members;
	}

	/**
	 * Unserialize a term description without letting it build objects.
	 *
	 * @return array<string|int,mixed>
	 */
	private static function decode( string $description ): array {
		if ( '' === $description || ! is_serialized( $description ) ) {
			return array();
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- allowed_classes false: arrays only, never objects.
		$value = @unserialize( $description, array( 'allowed_classes' => false ) );

		return is_array( $value ) ? $value : array();
	}
}
