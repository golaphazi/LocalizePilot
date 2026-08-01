<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class File_Cache {
	private array $settings;
	private string $directory;
	private string $page_directory;
	private string $group = 'localizepilot_html';

	public function __construct( array $settings ) {
		$this->settings = $settings;
		$new_directory = trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot';

		$this->directory      = $new_directory;
		$this->page_directory = trailingslashit( $this->directory ) . 'pages';
	}

	public function directory(): string {
		return $this->directory;
	}

	public function is_enabled(): bool {
		return ! empty( $this->settings['cache_enabled'] );
	}

	public function is_writable(): bool {
		return $this->ensure_directory() && is_writable( $this->directory ) && is_writable( $this->page_directory );
	}

	public function make_key( string $identity, string $language, string $fingerprint ): string {
		return hash( 'sha256', $identity . '|' . strtolower( $language ) . '|' . $fingerprint );
	}

	public function make_post_key( int $post_id, string $language ): string {
		return absint( $post_id ) . '_' . sanitize_key( $language );
	}

	public function post_page_path( int $post_id, string $language ): string {
		$paths = $this->paths( $this->make_post_key( $post_id, $language ) );
		return $paths['html'];
	}

	public function translation_snapshot_path( int $post_id, string $language ): string {
		return trailingslashit( $this->directory ) . $this->make_post_key( $post_id, $language ) . '.html';
	}

	public function write_translation_snapshot( int $post_id, string $language, string $html, array $metadata = array() ): bool {
		if ( ! $post_id || '' === sanitize_key( $language ) || ! $this->ensure_directory() ) {
			return false;
		}

		$key = $this->make_post_key( $post_id, $language );
		$path = trailingslashit( $this->directory ) . $key . '.html';
		$meta_path = trailingslashit( $this->directory ) . $key . '.json';
		$metadata = wp_parse_args(
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
		$this->atomic_write( $meta_path, (string) wp_json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		return true;
	}

	public function delete_translation_snapshot( int $post_id, string $language ): bool {
		$key = $this->make_post_key( $post_id, $language );
		$directory = trailingslashit( $this->directory );
		$deleted = false;
		foreach ( array( $directory . $key . '.html', $directory . $key . '.json' ) as $file ) {
			if ( is_file( $file ) ) {
				$deleted = unlink( $file ) || $deleted;
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
			foreach ( glob( trailingslashit( $this->page_directory ) . absint( $post_id ) . '_*.html' ) ?: array() as $file ) {
				$keys[] = basename( $file, '.html' );
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

	public function get( string $key, string $source_hash = '' ): ?string {
		if ( ! $this->is_enabled() ) {
			return null;
		}

		$ttl = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );

		if ( ! empty( $this->settings['object_cache_enabled'] ) ) {
			$found  = false;
			$cached = wp_cache_get( $key, $this->group, false, $found );
			if ( $found && is_array( $cached ) && ! empty( $cached['html'] ) ) {
				$cached_hash = (string) ( $cached['source_hash'] ?? '' );
				if ( empty( $this->settings['refresh_on_source_change'] ) || '' === $source_hash || '' === $cached_hash || hash_equals( $cached_hash, $source_hash ) ) {
					return (string) $cached['html'];
				}
			}
		}

		$paths = $this->paths( $key );
		if ( ! is_file( $paths['html'] ) ) {
			return null;
		}

		$modified = filemtime( $paths['html'] );
		if ( false === $modified || ( time() - $modified ) >= $ttl ) {
			return null;
		}

		if ( ! empty( $this->settings['refresh_on_source_change'] ) && '' !== $source_hash ) {
			$meta = $this->read_metadata( $paths['meta'] );
			if ( ! empty( $meta['source_hash'] ) && ! hash_equals( (string) $meta['source_hash'], $source_hash ) ) {
				return null;
			}
		}

		$html = $this->read_file( $paths['html'] );
		if ( null === $html || '' === $html ) {
			return null;
		}

		if ( ! empty( $this->settings['object_cache_enabled'] ) ) {
			wp_cache_set( $key, array( 'html' => $html, 'source_hash' => $source_hash ), $this->group, $ttl );
		}

		return $html;
	}

	public function get_stale( string $key ): ?string {
		$paths = $this->paths( $key );
		if ( ! is_file( $paths['html'] ) ) {
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

		$this->atomic_write( $paths['meta'], (string) wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		if ( ! empty( $this->settings['object_cache_enabled'] ) ) {
			wp_cache_set( $key, array( 'html' => $html, 'source_hash' => (string) ( $meta['source_hash'] ?? '' ) ), $this->group, $ttl );
		}

		return true;
	}

	public function delete( string $key ): bool {
		$paths   = $this->paths( $key );
		$deleted = false;

		foreach ( $paths as $path ) {
			if ( is_file( $path ) ) {
				$deleted = unlink( $path ) || $deleted;
			}
		}

		wp_cache_delete( $key, $this->group );
		return $deleted;
	}

	public function clear_all(): int {
		if ( ! is_dir( $this->directory ) ) {
			return 0;
		}

		$count = 0;
		$keys  = array();
		foreach ( glob( trailingslashit( $this->page_directory ) . '*.html' ) ?: array() as $html_file ) {
			$keys[] = basename( $html_file, '.html' );
		}

		foreach ( array( 'pages/' ) as $relative_directory ) {
			foreach ( array( '*.html', '*.json', '*.tmp', '*.tmp.*' ) as $pattern ) {
				foreach ( glob( trailingslashit( $this->directory ) . $relative_directory . $pattern ) ?: array() as $file ) {
					if ( is_file( $file ) && unlink( $file ) ) {
						$count++;
					}
				}
			}
		}

		foreach ( array_unique( $keys ) as $key ) {
			wp_cache_delete( $key, $this->group );
		}
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $this->group );
		}

		return $count;
	}

	public function clear_expired(): int {
		if ( ! is_dir( $this->directory ) ) {
			return 0;
		}

		$ttl   = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );
		$count = 0;

		foreach ( glob( trailingslashit( $this->page_directory ) . '*.html' ) ?: array() as $html_file ) {
			$modified = filemtime( $html_file );
			if ( false !== $modified && ( time() - $modified ) < $ttl ) {
				continue;
			}
			$key = basename( $html_file, '.html' );
			if ( $this->delete( $key ) ) {
				$count++;
			}
		}

		return $count;
	}


	public function clear_snapshots(): int {
		if ( ! is_dir( $this->directory ) ) {
			return 0;
		}

		$count = 0;
		foreach ( array( '*.html', '*.json' ) as $pattern ) {
			foreach ( glob( trailingslashit( $this->directory ) . $pattern ) ?: array() as $file ) {
				$name = basename( $file );
				if ( ! preg_match( '/^\d+_[a-z0-9-]+\.(html|json)$/i', $name ) ) {
					continue;
				}
				if ( is_file( $file ) && unlink( $file ) ) {
					$count++;
				}
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

		$deleted = false;
		foreach ( array( '.html', '.json' ) as $extension ) {
			$path = trailingslashit( $this->directory ) . $key . $extension;
			if ( is_file( $path ) ) {
				$deleted = unlink( $path ) || $deleted;
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
			foreach ( glob( trailingslashit( $this->page_directory ) . '*.html' ) ?: array() as $html_file ) {
				$modified = filemtime( $html_file );
				$size     = filesize( $html_file );
				$key      = basename( $html_file, '.html' );
				$meta     = $this->read_metadata( trailingslashit( $this->page_directory ) . $key . '.json' );
				$items[]  = array(
					'key'      => $key,
					'type'     => 'page',
					'url'      => (string) ( $meta['url'] ?? '' ),
					'language' => (string) ( $meta['language'] ?? $this->language_from_key( $key ) ),
					'provider' => (string) ( $meta['provider'] ?? '' ),
					'status'   => '',
					'modified' => false === $modified ? 0 : (int) $modified,
					'bytes'    => false === $size ? 0 : (int) $size,
					'expired'  => false === $modified || ( time() - $modified ) >= $ttl,
				);
			}
		}

		if ( 'page' !== $type ) {
			foreach ( glob( trailingslashit( $this->directory ) . '*.html' ) ?: array() as $html_file ) {
				$key = basename( $html_file, '.html' );
				if ( ! preg_match( '/^\d+_[a-z0-9-]+$/i', $key ) ) {
					continue;
				}
				$modified = filemtime( $html_file );
				$size     = filesize( $html_file );
				$meta     = $this->read_metadata( trailingslashit( $this->directory ) . $key . '.json' );
				$post_id  = absint( $meta['post_id'] ?? strtok( $key, '_' ) );
				$items[]  = array(
					'key'      => $key,
					'type'     => 'snapshot',
					'url'      => $post_id ? get_permalink( $post_id ) : '',
					'language' => (string) ( $meta['language'] ?? $this->language_from_key( $key ) ),
					'provider' => '',
					'status'   => (string) ( $meta['status'] ?? '' ),
					'modified' => false === $modified ? 0 : (int) $modified,
					'bytes'    => false === $size ? 0 : (int) $size,
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
			'count'    => 0,
			'expired'  => 0,
			'snapshots'=> 0,
			'size'     => 0,
			'path'     => $this->directory,
			'writable' => $this->is_writable(),
			'recent'   => array(),
		);

		if ( ! is_dir( $this->directory ) ) {
			return $stats;
		}

		$ttl   = max( HOUR_IN_SECONDS, absint( $this->settings['cache_hours'] ?? 24 ) * HOUR_IN_SECONDS );
		$items = array();

		foreach ( glob( trailingslashit( $this->page_directory ) . '*.html' ) ?: array() as $html_file ) {
			$modified = filemtime( $html_file );
			$size     = filesize( $html_file );
			$key      = basename( $html_file, '.html' );
			$meta     = $this->read_metadata( trailingslashit( $this->page_directory ) . $key . '.json' );
			$expired  = false === $modified || ( time() - $modified ) >= $ttl;

			$stats['count']++;
			$stats['size'] += false === $size ? 0 : (int) $size;
			if ( $expired ) {
				$stats['expired']++;
			}

			$items[] = array(
				'key'      => $key,
				'url'      => (string) ( $meta['url'] ?? '' ),
				'language' => (string) ( $meta['language'] ?? '' ),
				'provider' => (string) ( $meta['provider'] ?? '' ),
				'modified' => false === $modified ? 0 : (int) $modified,
				'bytes'    => false === $size ? 0 : (int) $size,
				'expired'  => $expired,
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return (int) $b['modified'] <=> (int) $a['modified'];
			}
		);

		foreach ( glob( trailingslashit( $this->directory ) . '*.html' ) ?: array() as $snapshot_file ) {
			if ( ! preg_match( '/^\d+_[a-z0-9-]+\.html$/i', basename( $snapshot_file ) ) ) {
				continue;
			}
			$stats['snapshots']++;
			$snapshot_size = filesize( $snapshot_file );
			$stats['size'] += false === $snapshot_size ? 0 : (int) $snapshot_size;
		}

		$stats['recent'] = array_slice( $items, 0, 20 );
		return $stats;
	}

	private function paths( string $key ): array {
		$key = preg_replace( '/[^a-z0-9_-]/i', '', $key ) ?: hash( 'sha256', $key );
		$base = trailingslashit( $this->page_directory ) . $key;
		return array(
			'html' => $base . '.html',
			'meta' => $base . '.json',
		);
	}

	private function ensure_directory(): bool {
		if ( ! is_dir( $this->directory ) && ! wp_mkdir_p( $this->directory ) ) {
			return false;
		}
		if ( ! is_dir( $this->page_directory ) && ! wp_mkdir_p( $this->page_directory ) ) {
			return false;
		}

		$this->ensure_protection_files();
		return is_dir( $this->directory ) && is_dir( $this->page_directory );
	}

	private function ensure_protection_files(): void {
		$index = trailingslashit( $this->directory ) . 'index.php';
		if ( ! is_file( $index ) ) {
			$this->atomic_write( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = trailingslashit( $this->directory ) . '.htaccess';
		if ( ! is_file( $htaccess ) ) {
			$this->atomic_write( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}

		$web_config = trailingslashit( $this->directory ) . 'web.config';
		if ( ! is_file( $web_config ) ) {
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
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return null;
		}

		$contents = '';
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 1048576 );
			if ( false === $chunk ) {
				fclose( $handle );
				return null;
			}
			$contents .= $chunk;
		}
		fclose( $handle );
		return $contents;
	}

	private function atomic_write( string $path, string $content ): bool {
		$temporary = $path . '.' . wp_generate_password( 8, false, false ) . '.tmp';
		$handle    = fopen( $temporary, 'wb' );
		if ( false === $handle ) {
			return false;
		}

		$locked  = flock( $handle, LOCK_EX );
		$length  = strlen( $content );
		$written = 0;

		if ( ! $locked ) {
			fclose( $handle );
			unlink( $temporary );
			return false;
		}

		while ( $written < $length ) {
			$bytes = fwrite( $handle, substr( $content, $written ) );
			if ( false === $bytes || 0 === $bytes ) {
				flock( $handle, LOCK_UN );
				fclose( $handle );
				unlink( $temporary );
				return false;
			}
			$written += $bytes;
		}

		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle );

		if ( '\\' === DIRECTORY_SEPARATOR && is_file( $path ) && ! unlink( $path ) ) {
			unlink( $temporary );
			return false;
		}

		if ( ! rename( $temporary, $path ) ) {
			unlink( $temporary );
			return false;
		}

		return true;
	}
}
