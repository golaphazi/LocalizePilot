<?php
/**
 * Bring another plugin's translations into LocalizePilot.
 *
 * A run walks the other plugin's translation groups in order. For each group
 * the post in LocalizePilot's default language becomes the source, and every
 * other language's post becomes a LocalizePilot translation record: title,
 * content, excerpt, slug, featured image and status copied across.
 *
 * What it will not do:
 *   - write to, delete from, or depend on the other plugin's data — it reads;
 *   - replace a LocalizePilot translation that already exists — so running it
 *     twice, or after translating some pages by hand, is safe;
 *   - call a translation provider — it copies translations people already
 *     have, and imports skip the cache warm that would render pages;
 *   - carry on by itself. It runs in short batches while its screen is open,
 *     keeps its place on the server, and waits for "Continue" if the screen
 *     is closed.
 *
 * Its own simple batching, deliberately separate from LocalizePilot Pro's
 * background engine.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Migration;

use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Post_Types;
use LocalizePilot\Settings;

defined( 'ABSPATH' ) || exit;

final class Migrator {
	/** The run in progress, or the last one. One at a time. */
	public const RUN_OPTION = 'localizepilot_migration_run';

	/** On a post moved to draft by a migration: the status it had before. */
	public const PREVIOUS_STATUS_META = '_localizepilot_premigration_status';

	private const LOCK = 'localizepilot_migration_lock';

	/** Groups read per query. */
	private const PAGE = 25;

	private const MAX_ERRORS = 20;

	/**
	 * Every source LocalizePilot can read.
	 *
	 * @return array<string,Source>
	 */
	public static function sources(): array {
		$sources = array(
			'wpml'     => new WPML_Source(),
			'polylang' => new Polylang_Source(),
		);

		/**
		 * Filter the plugins LocalizePilot can migrate from.
		 *
		 * @param array<string,Source> $sources Keyed by Source::id().
		 */
		$sources = (array) apply_filters( 'localizepilot_migration_sources', $sources );

		return array_filter(
			$sources,
			static function ( $source, $id ): bool {
				return $source instanceof Source && $source->id() === $id;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	public static function source( string $id ): ?Source {
		return self::sources()[ $id ] ?? null;
	}

	/* ---------------------------------------------------------------------
	 * Before a run: what would happen
	 * ------------------------------------------------------------------ */

	/**
	 * What a migration from this source would do, without doing it.
	 *
	 * @return array<string,mixed>
	 */
	public static function preview( Source $source ): array {
		$settings = Plugin::instance()->get_settings();
		$default  = (string) $settings['source_language'];
		$enabled  = array_map( 'strval', (array) $settings['enabled_languages'] );

		$languages      = array();
		$has_default    = false;
		$to_enable      = array();
		$unsupported    = 0;

		foreach ( $source->languages() as $code => $details ) {
			$mapped = $source->map_language( (string) $code );

			$languages[] = array(
				'code'       => (string) $code,
				'posts'      => (int) $details['posts'],
				'maps_to'    => $mapped,
				'name'       => null !== $mapped ? Language_Catalog::label( $mapped, 'english' ) : '',
				'is_default' => $mapped === $default,
				'enabled'    => null !== $mapped && ( $mapped === $default || in_array( $mapped, $enabled, true ) ),
			);

			if ( $mapped === $default ) {
				$has_default = true;
			} elseif ( null === $mapped ) {
				$unsupported += (int) $details['posts'];
			} elseif ( ! in_array( $mapped, $enabled, true ) ) {
				$to_enable[ $mapped ] = Language_Catalog::label( $mapped, 'english' );
			}
		}

		$types = array();

		foreach ( $source->post_types() as $type => $posts ) {
			$object = get_post_type_object( $type );

			$types[] = array(
				'type'         => (string) $type,
				'label'        => $object ? (string) $object->labels->name : (string) $type,
				'posts'        => (int) $posts,
				'translatable' => Post_Types::is_translatable( (string) $type ),
			);
		}

		return array(
			'groups'         => $source->count(),
			'default'        => $default,
			'default_name'   => Language_Catalog::label( $default, 'english' ),
			'has_default'    => $has_default,
			'languages'      => $languages,
			'to_enable'      => $to_enable,
			'unsupported'    => $unsupported,
			'post_types'     => $types,
		);
	}

	/* ---------------------------------------------------------------------
	 * A run
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string,mixed>|null
	 */
	public static function run(): ?array {
		$run = get_option( self::RUN_OPTION, null );

		return is_array( $run ) ? self::with_percent( $run ) : null;
	}

	/**
	 * Begin a migration.
	 *
	 * @param array<string,mixed> $options {
	 *     @type bool $enable_languages Turn on the languages being imported.
	 *     @type bool $draft_old        Move the other plugin's translated posts to draft.
	 * }
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function start( string $source_id, array $options = array() ) {
		$source = self::source( $source_id );

		if ( ! $source || ! $source->detected() ) {
			return new \WP_Error( 'localizepilot_migration_source', __( 'There is nothing to migrate from that plugin on this site.', 'localizepilot' ) );
		}

		$current = self::run();

		if ( $current && 'running' === $current['status'] ) {
			return new \WP_Error( 'localizepilot_migration_running', __( 'A migration is already under way. Continue it or cancel it first.', 'localizepilot' ) );
		}

		$preview = self::preview( $source );

		if ( ! $preview['has_default'] ) {
			return new \WP_Error(
				'localizepilot_migration_no_default',
				sprintf(
					/* translators: 1: plugin name such as WPML, 2: language name such as English. */
					__( '%1$s has no content in %2$s, LocalizePilot\'s default language, so there is nothing to attach translations to. Change the default language under Settings first.', 'localizepilot' ),
					$source->label(),
					$preview['default_name']
				)
			);
		}

		$enabled = array();

		if ( ! empty( $options['enable_languages'] ) ) {
			$enabled = Settings::enable_languages( array_keys( $preview['to_enable'] ) );
		}

		$run = array(
			'source'           => $source->id(),
			'label'            => $source->label(),
			'status'           => 'running',
			'cursor'           => 0,
			'total'            => (int) $preview['groups'],
			'processed'        => 0,
			'created'          => 0,
			'existing'         => 0,
			'no_default'       => 0,
			'unsupported'      => 0,
			'not_translatable' => 0,
			'failed'           => 0,
			'drafted'          => 0,
			'enabled'          => $enabled,
			'errors'           => array(),
			'draft_old'        => ! empty( $options['draft_old'] ),
			'started_at'       => time(),
			'updated_at'       => time(),
			'finished_at'      => 0,
		);

		update_option( self::RUN_OPTION, $run, false );

		return self::with_percent( $run );
	}

	/**
	 * Migrate for up to $budget seconds, finishing the group in hand.
	 *
	 * @return array<string,mixed>|\WP_Error The run afterwards; 'busy' when another request holds it.
	 */
	public static function step( float $budget = 5.0 ) {
		$run = self::run();

		if ( ! $run ) {
			return new \WP_Error( 'localizepilot_migration_none', __( 'There is no migration to continue.', 'localizepilot' ) );
		}

		if ( 'running' !== $run['status'] ) {
			return $run;
		}

		$source = self::source( (string) $run['source'] );

		if ( ! $source ) {
			return new \WP_Error( 'localizepilot_migration_source', __( 'The plugin this migration reads from is no longer available.', 'localizepilot' ) );
		}

		if ( ! self::lock() ) {
			$run['busy'] = true;

			return $run;
		}

		try {
			// Re-read under the lock: another tab may have moved it on.
			$run      = self::run();
			$deadline = microtime( true ) + max( 0.5, $budget );

			while ( 'running' === $run['status'] && microtime( true ) < $deadline ) {
				$groups = $source->groups( (int) $run['cursor'], self::PAGE );

				if ( empty( $groups ) ) {
					$run['status']      = 'done';
					$run['finished_at'] = time();
					break;
				}

				foreach ( $groups as $group ) {
					self::migrate_group( $source, $group, $run );

					$run['cursor'] = (int) $group['key'];
					++$run['processed'];

					if ( microtime( true ) >= $deadline ) {
						break;
					}
				}

				self::save( $run );
			}

			self::save( $run );
		} finally {
			self::unlock();
		}

		return self::with_percent( $run );
	}

	/**
	 * Stop a run where it is. What it already imported stays.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function cancel(): ?array {
		$run = self::run();

		if ( $run && 'running' === $run['status'] ) {
			$run['status']      = 'cancelled';
			$run['finished_at'] = time();
			self::save( $run );
		}

		return self::run();
	}

	/**
	 * Put back posts a migration moved to draft, up to $limit at a time.
	 *
	 * @return int How many are still waiting to be put back.
	 */
	public static function restore_drafted( int $limit = 100 ): int {
		$ids = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'any',
				'meta_key'         => self::PREVIOUS_STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off restore, bounded by $limit.
				'posts_per_page'   => max( 1, $limit ),
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		foreach ( $ids as $id ) {
			$previous = (string) get_post_meta( $id, self::PREVIOUS_STATUS_META, true );

			if ( in_array( $previous, array( 'publish', 'pending', 'private', 'future', 'draft' ), true ) ) {
				wp_update_post(
					array(
						'ID'          => (int) $id,
						'post_status' => $previous,
					)
				);
			}

			delete_post_meta( $id, self::PREVIOUS_STATUS_META );
		}

		return self::drafted_count();
	}

	/** Posts a migration moved to draft that have not been put back. */
	public static function drafted_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::PREVIOUS_STATUS_META ) );
	}

	/* ---------------------------------------------------------------------
	 * One group
	 * ------------------------------------------------------------------ */

	/**
	 * @param array{key:int,members:array<string,int>} $group Translation group.
	 * @param array<string,mixed>                       $run   Run state, updated in place.
	 */
	private static function migrate_group( Source $source, array $group, array &$run ): void {
		if ( count( $group['members'] ) < 2 ) {
			return;
		}

		$default = (string) Plugin::instance()->get_settings()['source_language'];
		$mapped  = array();

		// The other plugin's codes, in LocalizePilot's terms. Two of its
		// languages can land on one of ours (pt-br and pt-pt); the first wins.
		foreach ( $group['members'] as $code => $post_id ) {
			$language = $source->map_language( (string) $code );

			if ( null === $language ) {
				++$run['unsupported'];
				continue;
			}

			if ( ! isset( $mapped[ $language ] ) ) {
				$mapped[ $language ] = array(
					'code'    => (string) $code,
					'post_id' => (int) $post_id,
				);
			}
		}

		if ( ! isset( $mapped[ $default ] ) ) {
			++$run['no_default'];
			return;
		}

		$original = get_post( $mapped[ $default ]['post_id'] );

		if ( ! $original instanceof \WP_Post || in_array( $original->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			++$run['no_default'];
			return;
		}

		if ( ! Post_Types::is_translatable( $original->post_type ) ) {
			$run['not_translatable'] += count( $mapped ) - 1;
			return;
		}

		$manager = Plugin::instance()->translations();

		foreach ( $mapped as $language => $member ) {
			if ( $language === $default ) {
				continue;
			}

			if ( $manager->find_translation( $original->ID, $language ) ) {
				++$run['existing'];
				continue;
			}

			$translated = get_post( $member['post_id'] );

			if ( ! $translated instanceof \WP_Post || in_array( $translated->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				self::fail( $run, $original, $language, __( 'The translated post no longer exists.', 'localizepilot' ) );
				continue;
			}

			try {
				$manager->import_translation(
					$original->ID,
					$language,
					array(
						'title'        => $translated->post_title,
						'content'      => $translated->post_content,
						'excerpt'      => $translated->post_excerpt,
						'slug'         => $translated->post_name,
						'status'       => $translated->post_status,
						'author'       => (int) $translated->post_author,
						'thumbnail_id' => (int) get_post_thumbnail_id( $translated->ID ),
						'origin'       => $source->id() . ':' . $translated->ID,
					)
				);
			} catch ( \Throwable $exception ) {
				self::fail( $run, $original, $language, wp_strip_all_tags( $exception->getMessage() ) );
				continue;
			}

			++$run['created'];

			if ( ! empty( $run['draft_old'] ) && 'draft' !== $translated->post_status ) {
				update_post_meta( $translated->ID, self::PREVIOUS_STATUS_META, $translated->post_status );
				wp_update_post(
					array(
						'ID'          => $translated->ID,
						'post_status' => 'draft',
					)
				);
				++$run['drafted'];
			}
		}
	}

	/**
	 * @param array<string,mixed> $run Run state, updated in place.
	 */
	private static function fail( array &$run, \WP_Post $original, string $language, string $message ): void {
		++$run['failed'];

		$run['errors'][] = array(
			'item'    => sprintf( '%s (%s)', $original->post_title, strtoupper( $language ) ),
			'message' => $message,
		);

		$run['errors'] = array_slice( $run['errors'], -self::MAX_ERRORS );
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string,mixed> $run Run state.
	 */
	private static function save( array $run ): void {
		unset( $run['busy'], $run['percent'] );
		$run['updated_at'] = time();

		update_option( self::RUN_OPTION, $run, false );
	}

	/**
	 * @param array<string,mixed> $run Run state.
	 * @return array<string,mixed>
	 */
	private static function with_percent( array $run ): array {
		$total = (int) $run['total'];

		$run['percent'] = 'done' === $run['status']
			? 100
			: ( $total > 0 ? min( 99, (int) floor( 100 * (int) $run['processed'] / $total ) ) : 0 );

		return $run;
	}

	/**
	 * One step at a time, across tabs, atomically.
	 *
	 * INSERT IGNORE either inserts the row or does nothing, and says which —
	 * unlike add_option(), whose ON DUPLICATE KEY UPDATE lets two racing
	 * requests both believe they won and import the same page twice. The value
	 * is an expiry, so a lock left by a request that died is taken over.
	 */
	private static function lock(): bool {
		global $wpdb;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$taken = (int) $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
					self::LOCK,
					(string) ( time() + 120 )
				)
			);

			if ( 1 === $taken ) {
				return true;
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
					self::LOCK,
					time()
				)
			);
		}

		return false;
	}

	private static function unlock(): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK ) );
	}
}
