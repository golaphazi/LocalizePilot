<?php
/**
 * WPML's translations.
 *
 * WPML keeps one row per translated element in {prefix}icl_translations, and
 * rows that are translations of each other share a trid. Posts are the
 * elements whose type starts with "post_" — post_page, post_product — and
 * media (post_attachment) is left out, because LocalizePilot localizes media
 * differently.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Migration;

defined( 'ABSPATH' ) || exit;

final class WPML_Source extends Source {
	/** @var array<string,array{posts:int,locale:string}>|null */
	private ?array $languages = null;

	public function id(): string {
		return 'wpml';
	}

	public function label(): string {
		return 'WPML';
	}

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'icl_translations';
	}

	/**
	 * The rows this adapter reads: posts of every type except media.
	 *
	 * @param string $alias Table alias, when the query uses one.
	 */
	private function where( string $alias = '' ): string {
		$column = ( '' !== $alias ? $alias . '.' : '' ) . 'element_type';

		return "{$column} LIKE 'post\\_%' AND {$column} <> 'post_attachment'";
	}

	public function detected(): bool {
		global $wpdb;

		$table = $this->table();

		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return false;
		}

		return $this->count() > 0;
	}

	public function count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from the prefix; no input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ( SELECT trid FROM {$this->table()} WHERE {$this->where()} GROUP BY trid HAVING COUNT(*) > 1 ) AS groups_found" );
	}

	public function groups( int $after, int $limit ): array {
		global $wpdb;

		$table = $this->table();

		$trids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from the prefix.
					"SELECT trid FROM {$table} WHERE {$this->where()} AND trid > %d GROUP BY trid HAVING COUNT(*) > 1 ORDER BY trid ASC LIMIT %d",
					$after,
					max( 1, $limit )
				)
			)
		);

		if ( empty( $trids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $trids ), '%d' ) );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table from the prefix; one placeholder per ID.
				"SELECT trid, element_id, language_code FROM {$table} WHERE {$this->where()} AND trid IN ({$placeholders})",
				$trids
			),
			ARRAY_A
		);

		$groups = array();

		foreach ( $trids as $trid ) {
			$groups[ $trid ] = array(
				'key'     => $trid,
				'members' => array(),
			);
		}

		foreach ( $rows as $row ) {
			$groups[ (int) $row['trid'] ]['members'][ (string) $row['language_code'] ] = (int) $row['element_id'];
		}

		return array_values( $groups );
	}

	public function languages(): array {
		if ( null !== $this->languages ) {
			return $this->languages;
		}

		global $wpdb;

		$table   = $this->table();
		$locales = array();

		// WPML's language list gives each code its locale. It may be gone
		// with the plugin, in which case the codes have to stand alone.
		$languages_table = $wpdb->prefix . 'icl_languages';

		if ( $languages_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $languages_table ) ) ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from the prefix.
			foreach ( (array) $wpdb->get_results( "SELECT code, default_locale FROM {$languages_table}", ARRAY_A ) as $row ) {
				$locales[ (string) $row['code'] ] = (string) $row['default_locale'];
			}
		}

		$this->languages = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from the prefix.
		foreach ( (array) $wpdb->get_results( "SELECT language_code, COUNT(*) AS posts FROM {$table} WHERE {$this->where()} GROUP BY language_code ORDER BY posts DESC", ARRAY_A ) as $row ) {
			$code = (string) $row['language_code'];

			$this->languages[ $code ] = array(
				'posts'  => (int) $row['posts'],
				'locale' => $locales[ $code ] ?? '',
			);
		}

		return $this->languages;
	}

	public function post_types(): array {
		global $wpdb;

		$types = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from the prefix.
		foreach ( (array) $wpdb->get_results( "SELECT posts.post_type, COUNT(*) AS total FROM {$this->table()} AS t INNER JOIN {$wpdb->posts} AS posts ON posts.ID = t.element_id WHERE {$this->where( 't' )} GROUP BY posts.post_type ORDER BY total DESC", ARRAY_A ) as $row ) {
			$types[ (string) $row['post_type'] ] = (int) $row['total'];
		}

		return $types;
	}
}
