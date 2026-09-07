<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class File_Cache {
	private array $settings;
	private string $directory;
	private string $page_directory;
	private string $memory_directory;
	private string $group = 'localizepilot_html';

	/**
	 * WordPress filesystem instance.
	 *
	 * @var \WP_Filesystem_Base|null
	 */
	private $filesystem = null;


	public function __construct( array $settings ) {
		$this->settings = $settings;
		$new_directory  = trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot';

		$this->directory        = $new_directory;
		$this->page_directory   = trailingslashit( $this->directory ) . 'pages';
		$this->memory_directory = trailingslashit( $this->directory ) . 'memory';
	}

	public function directory(): string {
		$this->filesystem();
		return $this->directory;
	}

	public function is_enabled(): bool {
		return ! empty( $this->settings['cache_enabled'] );
	}

	public function is_writable(): bool {
		$filesystem = $this->filesystem();

		return null !== $filesystem
			&& $this->ensure_directory()
			&& $filesystem->is_writable( $this->directory )
			&& $filesystem->is_writable( $this->page_directory );
	}

	public function make_key( string $identity, string $language, string $fingerprint ): string {
		return hash( 'sha256', $identity . '|' . strtolower( $language ) . '|' . $fingerprint );
	}

	public function make_post_key( int $post_id, string $language ): string {
		return absint( $post_id ) . '_' . sanitize_key( $language );
	}

	public function post_page_path( int $post_id, string $language ): string {
		$this->filesystem();
		$paths = $this->paths( $this->make_post_key( $post_id, $language ) );
		return $paths['html'];
	}

	public function translation_snapshot_path( int $post_id, string $language ): string {
		$this->filesystem();
		return trailingslashit( $this->directory ) . $this->make_post_key( $post_id, $language ) . '.html';
	}

	public function write_translation_snapshot( int $post_id, string $language, string $html, array $metadata = array() ): bool {
		if ( ! $post_id || '' === sanitize_key( $language ) || ! $this->ensure_directory() ) {
			return false;
		}

		$key       = $this->make_post_key( $post_id, $language );
		$path      = trailingslashit( $this->directory ) . $key . '.html';
		$meta_path = trailingslashit( $this->directory ) . $key . '.json';
		$metadata  = wp_parse_args(
			$metadata,
			array(
				'post_id'    => $post_id,
				'language'   => sanitize_key( $language ),
				'updated_at' => time(),
				'bytes'      => strlen( $html ),
			)
		);

		if ( ! $this->atomic_write( $path, $html ) ) {
			return false;
		}

		$this->atomic_write(
			$meta_path,
			(string) wp_json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return true;
	}

	public function delete_translation_snapshot( int $post_id, string $language ): bool {
		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return false;
		}

		$key       = $this->make_post_key( $post_id, $language );
		$directory = trailingslashit( $this->directory );
		$deleted   = false;

		foreach ( array( $directory . $key . '.html', $directory . $key . '.json' ) as $file ) {
			if ( $filesystem->is_file( $file ) ) {
				$deleted = $filesystem->delete( $file, false, 'f' ) || $deleted;
			}
		}

		return $deleted;
	}

	public function delete_post_cache( int $post_id, string $language = '' ): int {
		if ( ! $post_id ) {
			return 0;
		}

		$keys = array();
		if ( '' !== sanitize_key( $language ) ) {
			$keys[] = $this->make_post_key( $post_id, $language );
		} else {
			$prefix = absint( $post_id ) . '_';
			foreach ( $this->list_files( $this->page_directory, 'html' ) as $file ) {
				if ( 0 === strpos( $file['name'], $prefix ) ) {
					$keys[] = substr( $file['name'], 0, -5 );
				}
			}
		}

		$count = 0;
		foreach ( array_unique( $keys ) as $key ) {
			if ( $this->delete( $key ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Read one rendered page from cache.
	 *
	 * The optional context marks a visitor-facing lookup that should publish a
	 * hit or miss measurement. Internal existence checks omit it, so a cache
	 * warm-up probe cannot quietly lower the reported hit rate.
	 *
	 * @param array<string,string> $context Optional language and request URL.
	 */
	public function get( string $key, string $source_hash = '', array $context = array() ): ?string {
		if ( ! $this->is_enabled() ) {
			return $this->finish_lookup( null, $key, $context, 'disabled' );
		}

		$ttl = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );

		if ( ! empty( $this->settings['object_cache_enabled'] ) ) {
			$found  = false;
			$cached = wp_cache_get( $key, $this->group, false, $found );
			if ( $found && is_array( $cached ) && ! empty( $cached['html'] ) ) {
				$cached_hash = (string) ( $cached['source_hash'] ?? '' );
				if ( empty( $this->settings['refresh_on_source_change'] ) || '' === $source_hash || '' === $cached_hash || hash_equals( $cached_hash, $source_hash ) ) {
					return $this->finish_lookup( (string) $cached['html'], $key, $context, 'object-cache' );
				}
			}
		}

		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return $this->finish_lookup( null, $key, $context, 'filesystem-unavailable' );
		}

		$paths = $this->paths( $key );
		if ( ! $filesystem->is_file( $paths['html'] ) ) {
			return $this->finish_lookup( null, $key, $context, 'missing' );
		}

		$modified = $filesystem->mtime( $paths['html'] );
		if ( false === $modified || ( time() - (int) $modified ) >= $ttl ) {
			return $this->finish_lookup( null, $key, $context, 'expired' );
		}

		if ( ! empty( $this->settings['refresh_on_source_change'] ) && '' !== $source_hash ) {
			$meta = $this->read_metadata( $paths['meta'] );
			if ( ! empty( $meta['source_hash'] ) && ! hash_equals( (string) $meta['source_hash'], $source_hash ) ) {
				return $this->finish_lookup( null, $key, $context, 'source-changed' );
			}
		}

		$html = $this->read_file( $paths['html'] );
		if ( null === $html || '' === $html ) {
			return $this->finish_lookup( null, $key, $context, 'unreadable' );
		}

		if ( ! empty( $this->settings['object_cache_enabled'] ) ) {
			wp_cache_set( $key, array( 'html' => $html, 'source_hash' => $source_hash ), $this->group, $ttl );
		}

		return $this->finish_lookup( $html, $key, $context, 'file-cache' );
	}

	/**
	 * Publish exactly one result for a measured page-cache lookup.
	 *
	 * LocalizePilot does not retain this event. The Pro add-on may aggregate it,
	 * while sites running only the free plugin pay only for the action dispatch.
	 *
	 * @param array<string,string> $context Lookup context supplied by the caller.
	 */
	private function finish_lookup( ?string $html, string $key, array $context, string $reason ): ?string {
		if ( ! empty( $context ) ) {
			/**
			 * Fires after a visitor-facing rendered-page cache lookup.
			 *
			 * @param array<string,mixed> $measurement {
			 *     @type bool   $hit      Whether usable cached HTML was returned.
			 *     @type string $key      The sanitized cache key.
			 *     @type string $language Language requested.
			 *     @type string $url      Request path, without its query string.
			 *     @type string $reason   Where the lookup ended.
			 * }
			 */
			do_action(
				'localizepilot_cache_lookup',
				array(
					'hit'      => is_string( $html ) && '' !== $html,
					'key'      => substr( preg_replace( '/[^a-z0-9_-]/i', '', $key ) ?: hash( 'sha256', $key ), 0, 128 ),
					'language' => sanitize_key( (string) ( $context['language'] ?? '' ) ),
					'url'      => (string) ( $context['url'] ?? '' ),
					'reason'   => sanitize_key( $reason ),
				)
			);
		}

		return $html;
	}

	public function get_stale( string $key ): ?string {
		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return null;
		}

		$paths = $this->paths( $key );
		if ( ! $filesystem->is_file( $paths['html'] ) ) {
			return null;
		}

		$html = $this->read_file( $paths['html'] );
		return is_string( $html ) && '' !== $html ? $html : null;
	}

	public function set( string $key, string $html, array $metadata = array() ): bool {
		if ( ! $this->is_enabled() || '' === trim( $html ) || ! $this->ensure_directory() ) {
			return false;
		}

		$paths = $this->paths( $key );
		$now   = time();
		$ttl   = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );
		$meta  = wp_parse_args(
			$metadata,
			array(
				'key'        => $key,
				'created_at' => $now,
				'expires_at' => $now + $ttl,
				'bytes'      => strlen( $html ),
			)
		);

		$meta['key']        = $key;
		$meta['created_at'] = $now;
		$meta['expires_at'] = $now + $ttl;
		$meta['bytes']      = strlen( $html );

		if ( ! $this->atomic_write( $paths['html'], $html ) ) {
			return false;
		}

		$this->atomic_write(
			$paths['meta'],
			(string) wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		if ( ! empty( $this->settings['object_cache_enabled'] ) ) {
			wp_cache_set( $key, array( 'html' => $html, 'source_hash' => (string) ( $meta['source_hash'] ?? '' ) ), $this->group, $ttl );
		}

		return true;
	}

	/**
	 * Read translated string values cached for one rendered language page.
	 *
	 * Source strings are represented only by SHA-256 hashes in the cache file.
	 *
	 * @return array{provider:string,translations:array<string,string>}
	 */
	public function get_translation_memory( string $key, string $fingerprint, string $language ): array {
		$empty = array(
			'provider'     => '',
			'translations' => array(),
		);

		if ( ! $this->is_enabled() ) {
			return $empty;
		}

		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return $empty;
		}

		$path = $this->translation_memory_path( $key, $fingerprint );
		if ( ! $filesystem->is_file( $path ) ) {
			return $empty;
		}

		$ttl      = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );
		$modified = $filesystem->mtime( $path );
		if ( false === $modified || ( time() - (int) $modified ) >= $ttl ) {
			return $empty;
		}

		$data = $this->read_metadata( $path );
		if ( sanitize_key( (string) ( $data['language'] ?? '' ) ) !== sanitize_key( $language ) ) {
			return $empty;
		}

		$translations = array();
		foreach ( (array) ( $data['translations'] ?? array() ) as $source_hash => $translation ) {
			if ( is_string( $source_hash ) && preg_match( '/^[a-f0-9]{64}$/', $source_hash ) && is_string( $translation ) ) {
				$translations[ $source_hash ] = $translation;
			}
		}

		return array(
			'provider'     => sanitize_key( (string) ( $data['provider'] ?? '' ) ),
			'translations' => $translations,
		);
	}

	/**
	 * Persist translated string values for one rendered language page.
	 *
	 * @param array<string,string> $translations Source-hash to translation map.
	 */
	public function set_translation_memory(
		string $key,
		string $fingerprint,
		string $language,
		array $translations,
		string $provider
	): bool {
		if ( ! $this->is_enabled() || ! $this->ensure_memory_directory() ) {
			return false;
		}

		$clean = array();
		foreach ( $translations as $source_hash => $translation ) {
			if ( is_string( $source_hash ) && preg_match( '/^[a-f0-9]{64}$/', $source_hash ) && is_string( $translation ) ) {
				$clean[ $source_hash ] = $translation;
			}
		}

		if ( count( $clean ) > 2000 ) {
			$clean = array_slice( $clean, -2000, null, true );
		}

		return $this->atomic_write(
			$this->translation_memory_path( $key, $fingerprint ),
			(string) wp_json_encode(
				array(
					'language'     => sanitize_key( $language ),
					'provider'     => sanitize_key( $provider ),
					'updated_at'   => time(),
					'translations' => $clean,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}

	public function delete( string $key ): bool {
		$filesystem = $this->filesystem();
		$paths      = $this->paths( $key );
		$deleted    = false;

		if ( null !== $filesystem ) {
			foreach ( $paths as $path ) {
				if ( $filesystem->is_file( $path ) ) {
					$deleted = $filesystem->delete( $path, false, 'f' ) || $deleted;
				}
			}
		}

		wp_cache_delete( $key, $this->group );
		$deleted = $this->delete_translation_memory( $key ) || $deleted;

		if ( $deleted ) {
			self::purge_external_caches();
		}

		return $deleted;
	}

	/**
	 * Name the host-level or plugin page cache LocalizePilot can currently
	 * detect. Kept beside the purge integrations so detection and support do
	 * not drift apart between admin screens.
	 */
	public static function detected_external_cache_label(): string {
		if ( class_exists( '\\SiteGround_Optimizer\\Supercacher\\Supercacher' ) ) {
			return __( 'SiteGround Dynamic Cache (SG Optimizer)', 'localizepilot' );
		}
		if ( defined( 'LSCWP_V' ) || class_exists( '\\LiteSpeed\\Core' ) ) {
			return __( 'LiteSpeed Cache', 'localizepilot' );
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			return __( 'WP Rocket', 'localizepilot' );
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			return __( 'W3 Total Cache', 'localizepilot' );
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			return __( 'WP Super Cache', 'localizepilot' );
		}
		if ( class_exists( '\\WpeCommon' ) ) {
			return __( 'WP Engine page cache', 'localizepilot' );
		}

		return '';
	}

	/**
	 * Purge known host-level and third-party page caches whenever LocalizePilot
	 * invalidates its own translated-HTML cache.
	 *
	 * LocalizePilot's file and object cache are entirely internal to the
	 * plugin. On managed hosts such as SiteGround, a separate server-level
	 * page cache (SG Optimizer's Dynamic Cache / SuperCacher) also stores a
	 * full copy of the rendered HTML and serves it directly, without PHP
	 * running again, until it is told to purge. Without this integration a
	 * cleared or regenerated translation keeps being served stale from that
	 * host cache, which looks like "the cache system is not working" even
	 * though LocalizePilot's own cache was cleared correctly.
	 */
	public static function purge_external_caches(): void {
		// SiteGround SG Optimizer Dynamic Cache (Supercacher).
		if ( class_exists( '\SiteGround_Optimizer\Supercacher\Supercacher' ) && method_exists( '\SiteGround_Optimizer\Supercacher\Supercacher', 'purge_cache' ) ) {
			try {
				\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
			} catch ( \Throwable $exception ) {
				// Ignore; the action below is a second, independent path to the same purge.
			}
		}
		do_action( 'sg_cachepress_purge_cache' );

		// LiteSpeed Cache.
		do_action( 'litespeed_purge_all' );

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// WP Engine.
		if ( class_exists( '\WpeCommon' ) ) {
			if ( method_exists( '\WpeCommon', 'purge_memcached' ) ) {
				\WpeCommon::purge_memcached();
			}
			if ( method_exists( '\WpeCommon', 'purge_varnish_cache' ) ) {
				\WpeCommon::purge_varnish_cache();
			}
		}

		/**
		 * Fires after LocalizePilot purges the host-level and third-party
		 * page caches it knows how to reach, so other integrations can hook
		 * in their own purge call.
		 */
		do_action( 'localizepilot_purge_external_caches' );
	}

	public function clear_all(): int {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_dir( $this->directory ) ) {
			return 0;
		}

		$count = 0;
		$keys  = array();

		foreach ( $this->list_files( $this->page_directory ) as $file ) {
			if ( 'html' === $file['extension'] ) {
				$keys[] = substr( $file['name'], 0, -5 );
			}

			if ( $filesystem->delete( $file['path'], false, 'f' ) ) {
				$count++;
			}
		}

		foreach ( array_unique( $keys ) as $key ) {
			wp_cache_delete( $key, $this->group );
		}

		foreach ( $this->list_files( $this->memory_directory, 'json' ) as $file ) {
			$filesystem->delete( $file['path'], false, 'f' );
		}

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $this->group );
		}

		self::purge_external_caches();

		return $count;
	}

	public function clear_expired(): int {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_dir( $this->directory ) ) {
			return 0;
		}

		$ttl   = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );
		$count = 0;

		foreach ( $this->list_files( $this->page_directory, 'html' ) as $file ) {
			$modified = $this->file_modified_time( $file );
			if ( 0 < $modified && ( time() - $modified ) < $ttl ) {
				continue;
			}

			$key = substr( $file['name'], 0, -5 );
			// Use the filesystem delete directly; $this->delete() already purges
			// external caches per-key, and clear_expired can run frequently on
			// its own schedule, so avoid piling up redundant host cache purges.
			$paths   = $this->paths( $key );
			$removed = false;
			foreach ( $paths as $path ) {
				if ( $filesystem->is_file( $path ) ) {
					$removed = $filesystem->delete( $path, false, 'f' ) || $removed;
				}
			}
			wp_cache_delete( $key, $this->group );
			if ( $removed ) {
				$count++;
			}
		}

		foreach ( $this->list_files( $this->memory_directory, 'json' ) as $file ) {
			$modified = $this->file_modified_time( $file );
			if ( 0 === $modified || ( time() - $modified ) >= $ttl ) {
				$filesystem->delete( $file['path'], false, 'f' );
			}
		}

		if ( $count > 0 ) {
			self::purge_external_caches();
		}

		return $count;
	}

	public function clear_snapshots(): int {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_dir( $this->directory ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $this->list_files( $this->directory ) as $file ) {
			if ( ! preg_match( '/^\d+_[a-z0-9-]+\.(html|json)$/i', $file['name'] ) ) {
				continue;
			}

			if ( $filesystem->delete( $file['path'], false, 'f' ) ) {
				$count++;
			}
		}

		return $count;
	}

	public function delete_history_item( string $type, string $key ): bool {
		$type = sanitize_key( $type );
		$key  = preg_replace( '/[^a-z0-9_-]/i', '', $key ) ?: '';
		if ( '' === $key ) {
			return false;
		}

		if ( 'snapshot' !== $type ) {
			return $this->delete( $key );
		}

		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return false;
		}

		$deleted = false;
		foreach ( array( '.html', '.json' ) as $extension ) {
			$path = trailingslashit( $this->directory ) . $key . $extension;
			if ( $filesystem->is_file( $path ) ) {
				$deleted = $filesystem->delete( $path, false, 'f' ) || $deleted;
			}
		}

		return $deleted;
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int,per_page:int}
	 */
	public function history( int $page = 1, int $per_page = 15, string $type = 'all' ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$type     = in_array( $type, array( 'all', 'page', 'snapshot' ), true ) ? $type : 'all';
		$items    = array();
		$ttl      = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );

		if ( 'snapshot' !== $type ) {
			foreach ( $this->list_files( $this->page_directory, 'html' ) as $html_file ) {
				$modified = $this->file_modified_time( $html_file );
				$size     = $this->file_size( $html_file );
				$key      = substr( $html_file['name'], 0, -5 );
				$meta     = $this->read_metadata( trailingslashit( $this->page_directory ) . $key . '.json' );
				$items[]  = array(
					'key'      => $key,
					'type'     => 'page',
					'url'      => (string) ( $meta['url'] ?? '' ),
					'language' => (string) ( $meta['language'] ?? $this->language_from_key( $key ) ),
					'provider' => (string) ( $meta['provider'] ?? '' ),
					'status'   => '',
					'modified' => $modified,
					'bytes'    => $size,
					'expired'  => 0 === $modified || ( time() - $modified ) >= $ttl,
				);
			}
		}

		if ( 'page' !== $type ) {
			foreach ( $this->list_files( $this->directory, 'html' ) as $html_file ) {
				$key = substr( $html_file['name'], 0, -5 );
				if ( ! preg_match( '/^\d+_[a-z0-9-]+$/i', $key ) ) {
					continue;
				}

				$modified = $this->file_modified_time( $html_file );
				$size     = $this->file_size( $html_file );
				$meta     = $this->read_metadata( trailingslashit( $this->directory ) . $key . '.json' );
				$post_id  = absint( $meta['post_id'] ?? strtok( $key, '_' ) );
				$items[]  = array(
					'key'      => $key,
					'type'     => 'snapshot',
					'url'      => $post_id ? get_permalink( $post_id ) : '',
					'language' => (string) ( $meta['language'] ?? $this->language_from_key( $key ) ),
					'provider' => '',
					'status'   => (string) ( $meta['status'] ?? '' ),
					'modified' => $modified,
					'bytes'    => $size,
					'expired'  => false,
				);
			}
		}

		usort( $items, static fn( array $a, array $b ): int => (int) $b['modified'] <=> (int) $a['modified'] );
		$total = count( $items );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page  = min( $page, $pages );

		return array(
			'items'    => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ),
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	private function language_from_key( string $key ): string {
		$parts = explode( '_', $key );
		return count( $parts ) > 1 ? sanitize_key( (string) end( $parts ) ) : '';
	}

	/**
	 * @return array{count:int,expired:int,snapshots:int,size:int,path:string,writable:bool,recent:array<int,array<string,mixed>>}
	 */
	public function stats(): array {
		$stats = array(
			'count'     => 0,
			'expired'   => 0,
			'snapshots' => 0,
			'size'      => 0,
			'path'      => $this->directory,
			'writable'  => $this->is_writable(),
			'recent'    => array(),
		);

		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_dir( $this->directory ) ) {
			return $stats;
		}

		$ttl   = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );
		$items = array();

		foreach ( $this->list_files( $this->page_directory, 'html' ) as $html_file ) {
			$modified = $this->file_modified_time( $html_file );
			$size     = $this->file_size( $html_file );
			$key      = substr( $html_file['name'], 0, -5 );
			$meta     = $this->read_metadata( trailingslashit( $this->page_directory ) . $key . '.json' );
			$expired  = 0 === $modified || ( time() - $modified ) >= $ttl;

			$stats['count']++;
			$stats['size'] += $size;
			if ( $expired ) {
				$stats['expired']++;
			}

			$items[] = array(
				'key'      => $key,
				'url'      => (string) ( $meta['url'] ?? '' ),
				'language' => (string) ( $meta['language'] ?? '' ),
				'provider' => (string) ( $meta['provider'] ?? '' ),
				'modified' => $modified,
				'bytes'    => $size,
				'expired'  => $expired,
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return (int) $b['modified'] <=> (int) $a['modified'];
			}
		);

		foreach ( $this->list_files( $this->directory, 'html' ) as $snapshot_file ) {
			if ( ! preg_match( '/^\d+_[a-z0-9-]+\.html$/i', $snapshot_file['name'] ) ) {
				continue;
			}

			$stats['snapshots']++;
			$stats['size'] += $this->file_size( $snapshot_file );
		}

		$stats['recent'] = array_slice( $items, 0, 20 );
		return $stats;
	}

	private function paths( string $key ): array {
		$key  = preg_replace( '/[^a-z0-9_-]/i', '', $key ) ?: hash( 'sha256', $key );
		$base = trailingslashit( $this->page_directory ) . $key;

		return array(
			'html' => $base . '.html',
			'meta' => $base . '.json',
		);
	}

	private function translation_memory_path( string $key, string $fingerprint ): string {
		$key = preg_replace( '/[^a-z0-9_-]/i', '', $key ) ?: hash( 'sha256', $key );
		return trailingslashit( $this->memory_directory ) . $key . '_' . substr( hash( 'sha256', $fingerprint ), 0, 16 ) . '.json';
	}

	private function delete_translation_memory( string $key ): bool {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_dir( $this->memory_directory ) ) {
			return false;
		}

		$key     = preg_replace( '/[^a-z0-9_-]/i', '', $key ) ?: hash( 'sha256', $key );
		$prefix  = $key . '_';
		$deleted = false;

		foreach ( $this->list_files( $this->memory_directory, 'json' ) as $file ) {
			if ( 0 === strpos( $file['name'], $prefix ) ) {
				$deleted = $filesystem->delete( $file['path'], false, 'f' ) || $deleted;
			}
		}

		return $deleted;
	}

	private function ensure_directory(): bool {
		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return false;
		}

		if ( ! $filesystem->is_dir( $this->directory ) && ! $this->make_directory( $this->directory ) ) {
			return false;
		}

		if ( ! $filesystem->is_dir( $this->page_directory ) && ! $this->make_directory( $this->page_directory ) ) {
			return false;
		}

		$this->ensure_protection_files();

		return $filesystem->is_dir( $this->directory ) && $filesystem->is_dir( $this->page_directory );
	}

	private function ensure_memory_directory(): bool {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $this->ensure_directory() ) {
			return false;
		}

		if ( ! $filesystem->is_dir( $this->memory_directory ) && ! $this->make_directory( $this->memory_directory ) ) {
			return false;
		}

		return $filesystem->is_dir( $this->memory_directory ) && $filesystem->is_writable( $this->memory_directory );
	}

	private function ensure_protection_files(): void {
		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return;
		}

		$index = trailingslashit( $this->directory ) . 'index.php';
		if ( ! $filesystem->is_file( $index ) ) {
			$this->atomic_write( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = trailingslashit( $this->directory ) . '.htaccess';
		if ( ! $filesystem->is_file( $htaccess ) ) {
			$this->atomic_write( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}

		$web_config = trailingslashit( $this->directory ) . 'web.config';
		if ( ! $filesystem->is_file( $web_config ) ) {
			$this->atomic_write( $web_config, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n" );
		}
	}

	private function read_metadata( string $path ): array {
		$raw = $this->read_file( $path );
		if ( null === $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	private function read_file( string $path ): ?string {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_file( $path ) || ! $filesystem->is_readable( $path ) ) {
			return null;
		}

		$contents = $filesystem->get_contents( $path );
		return false === $contents ? null : (string) $contents;
	}

	private function atomic_write( string $path, string $content ): bool {
		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return false;
		}

		$temporary = $path . '.' . wp_generate_password( 8, false, false ) . '.tmp';
		$chmod     = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

		if ( ! $filesystem->put_contents( $temporary, $content, $chmod ) ) {
			return false;
		}

		if ( $filesystem->move( $temporary, $path, true ) ) {
			return true;
		}

		// Some transports cannot atomically move across locations. Fall back to
		// a direct Filesystem API write while still avoiding native PHP functions.
		$written = $filesystem->put_contents( $path, $content, $chmod );
		$filesystem->delete( $temporary, false, 'f' );

		return $written;
	}

	/**
	 * Initialize and return the WordPress Filesystem API instance.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private function filesystem() {
		if ( is_object( $this->filesystem ) ) {
			return $this->filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) && ! \WP_Filesystem() ) {
			return null;
		}

		if ( ! is_object( $wp_filesystem ) ) {
			return null;
		}

		$this->filesystem = $wp_filesystem;

		$content_directory = $this->filesystem->wp_content_dir();
		if ( is_string( $content_directory ) && '' !== $content_directory ) {
			$this->directory        = trailingslashit( $content_directory ) . 'cache/localizepilot';
			$this->page_directory   = trailingslashit( $this->directory ) . 'pages';
			$this->memory_directory = trailingslashit( $this->directory ) . 'memory';
		}

		return $this->filesystem;
	}

	private function make_directory( string $directory ): bool {
		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return false;
		}

		if ( $filesystem->is_dir( $directory ) ) {
			return true;
		}

		$parent = dirname( untrailingslashit( $directory ) );
		if ( $parent !== $directory && ! $filesystem->is_dir( $parent ) && ! $this->make_directory( $parent ) ) {
			return false;
		}

		$chmod = defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755;
		return $filesystem->mkdir( $directory, $chmod ) || $filesystem->is_dir( $directory );
	}

	/**
	 * Return regular files from a directory using WP_Filesystem::dirlist().
	 *
	 * @return array<int,array{name:string,path:string,extension:string,data:array<string,mixed>}>
	 */
	private function list_files( string $directory, string $extension = '' ): array {
		$filesystem = $this->filesystem();
		if ( null === $filesystem || ! $filesystem->is_dir( $directory ) ) {
			return array();
		}

		$directory_list = $filesystem->dirlist( $directory, true, false );
		if ( ! is_array( $directory_list ) ) {
			return array();
		}

		$extension = strtolower( ltrim( $extension, '.' ) );
		$files     = array();

		foreach ( $directory_list as $name => $data ) {
			$name = (string) $name;
			$data = is_array( $data ) ? $data : array();
			$path = trailingslashit( $directory ) . $name;
			$type = (string) ( $data['type'] ?? '' );

			if ( 'f' !== $type && ! $filesystem->is_file( $path ) ) {
				continue;
			}

			$file_extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( '' !== $extension && $file_extension !== $extension ) {
				continue;
			}

			$files[] = array(
				'name'      => $name,
				'path'      => $path,
				'extension' => $file_extension,
				'data'      => $data,
			);
		}

		return $files;
	}

	/**
	 * @param array{name:string,path:string,extension:string,data:array<string,mixed>} $file File data.
	 */
	private function file_modified_time( array $file ): int {
		if ( isset( $file['data']['lastmodunix'] ) && is_numeric( $file['data']['lastmodunix'] ) ) {
			return (int) $file['data']['lastmodunix'];
		}

		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return 0;
		}

		$modified = $filesystem->mtime( $file['path'] );
		return false === $modified ? 0 : (int) $modified;
	}

	/**
	 * @param array{name:string,path:string,extension:string,data:array<string,mixed>} $file File data.
	 */
	private function file_size( array $file ): int {
		if ( isset( $file['data']['size'] ) && is_numeric( $file['data']['size'] ) ) {
			return max( 0, (int) $file['data']['size'] );
		}

		$filesystem = $this->filesystem();
		if ( null === $filesystem ) {
			return 0;
		}

		$size = $filesystem->size( $file['path'] );
		return false === $size ? 0 : max( 0, (int) $size );
	}
}
