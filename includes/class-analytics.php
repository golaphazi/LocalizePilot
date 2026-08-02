<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

/**
 * First-party language-page analytics.
 *
 * Every eligible page view is stored as an event. Reports count a visitor only
 * once for each language page by using COUNT(DISTINCT visitor_hash), while the
 * complete event history remains available for total page-view reporting.
 */
final class Analytics {
	private const DB_VERSION_OPTION = 'localizepilot_analytics_db_version';
	private const DB_VERSION        = '1.0.1';
	private const COOKIE_NAME       = 'localizepilot_visitor_id';
	private const CLEANUP_HOOK      = 'localizepilot_daily_analytics_cleanup';

	private Router $router;

	public function __construct( Router $router ) {
		$this->router = $router;
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'maybe_install' ), 2 );
		add_action( 'init', array( $this, 'ensure_cleanup_schedule' ), 20 );
		add_action( 'template_redirect', array( $this, 'track_current_request' ), 1 );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_events' ) );
	}

	public static function activate(): void {
		self::install_table();
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	public function maybe_install(): void {
		if ( self::DB_VERSION !== (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			self::install_table();
		}
	}

	private static function install_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_hash char(64) NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			language varchar(12) NOT NULL,
			page_url text NOT NULL,
			visitor_hash char(64) NOT NULL,
			visited_at datetime NOT NULL,
			visit_date date NOT NULL,
			PRIMARY KEY  (id),
			KEY page_hash (page_hash),
			KEY language_date (language, visit_date),
			KEY page_visitor (page_hash, visitor_hash),
			KEY post_language (post_id, language),
			KEY visited_at (visited_at)
		) {$charset_collate};";

		\dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public function ensure_cleanup_schedule(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public function cleanup_old_events(): int {
		global $wpdb;

		$settings       = Plugin::instance()->get_settings();
		$retention_days = min( 3650, max( 7, absint( $settings['analytics_retention_days'] ?? 365 ) ) );
		$cutoff         = wp_date( 'Y-m-d', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$table_name     = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from $wpdb->prefix.
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE visit_date < %s", $cutoff ) );
		return false === $result ? 0 : (int) $result;
	}

	public function clear_all(): int {
		global $wpdb;
		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from $wpdb->prefix.
		$result = $wpdb->query( "DELETE FROM {$table_name}" );
		return false === $result ? 0 : (int) $result;
	}

	public function track_current_request(): void {
		$settings = Plugin::instance()->get_settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['analytics_enabled'] ) || ! $this->is_trackable_request() ) {
			return;
		}

		$language = sanitize_key( $this->router->current_language() );
		if ( ! in_array( $language, $this->router->enabled_languages(), true ) ) {
			return;
		}

		$page_url = self::normalize_url( $this->router->language_url( $this->router->source_language() ) );
		if ( '' === $page_url ) {
			return;
		}

		$visitor_hash = $this->visitor_hash();
		if ( '' === $visitor_hash ) {
			return;
		}

		global $wpdb;
		$now = current_time( 'mysql' );

		$wpdb->insert(
			self::table_name(),
			array(
				'page_hash'    => self::page_hash( $page_url, $language ),
				'post_id'      => is_singular() ? absint( get_queried_object_id() ) : 0,
				'language'     => $language,
				'page_url'     => $page_url,
				'visitor_hash' => $visitor_hash,
				'visited_at'   => $now,
				'visit_date'   => substr( $now, 0, 10 ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private function is_trackable_request(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_trackback() || is_preview() || is_404() ) {
			return false;
		}

		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		if ( 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			return false;
		}

		if ( is_singular() && post_password_required() ) {
			return false;
		}

		$user_agent = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
		if ( '' !== $user_agent && preg_match( '/(?:bot|crawler|spider|slurp|bingpreview|facebookexternalhit|headlesschrome|uptimerobot|pingdom)/i', $user_agent ) ) {
			return false;
		}

		return true;
	}

	private function visitor_hash(): string {
		if ( is_user_logged_in() ) {
			return hash( 'sha256', 'user|' . get_current_user_id() . '|' . wp_salt( 'auth' ) );
		}

		$visitor_id = isset( $_COOKIE[ self::COOKIE_NAME ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
			: '';

		if ( ! preg_match( '/^[a-f0-9-]{32,64}$/i', $visitor_id ) ) {
			$visitor_id = wp_generate_uuid4();
			$this->set_visitor_cookie( $visitor_id );
		}

		return hash( 'sha256', 'anonymous|' . $visitor_id . '|' . wp_salt( 'auth' ) );
	}

	private function set_visitor_cookie( string $visitor_id ): void {
		if ( headers_sent() ) {
			return;
		}

		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '';

		setcookie(
			self::COOKIE_NAME,
			$visitor_id,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE_NAME ] = $visitor_id;
	}

	/**
	 * Return dashboard data for the requested filters.
	 *
	 * @param array<string,mixed> $filters Report filters.
	 * @return array<string,mixed>
	 */
	public function report( array $filters ): array {
		global $wpdb;

		$normalized = $this->normalize_filters( $filters );
		$where      = $this->where_sql( $normalized );
		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE clause are generated internally.
		$summary = $wpdb->get_row(
			"SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, COUNT(DISTINCT page_hash) AS pages, COUNT(DISTINCT language) AS languages FROM {$table_name} {$where['sql']}",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE clause are generated internally.
		$daily_rows = $wpdb->get_results(
			"SELECT visit_date, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table_name} {$where['sql']} GROUP BY visit_date ORDER BY visit_date ASC",
			ARRAY_A
		);

		$per_page = 20;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE clause are generated internally.
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM (SELECT page_hash FROM {$table_name} {$where['sql']} GROUP BY page_hash) AS localizepilot_pages"
		);

		$pages  = max( 1, (int) ceil( $total / $per_page ) );
		$page   = min( max( 1, absint( $normalized['page'] ) ), $pages );
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE clause are generated internally; limit values are integers.
		$page_rows = $wpdb->get_results(
			"SELECT page_hash, page_url, language, post_id, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, MAX(visited_at) AS last_visit FROM {$table_name} {$where['sql']} GROUP BY page_hash, page_url, language, post_id ORDER BY visitors DESC, views DESC, last_visit DESC LIMIT {$per_page} OFFSET {$offset}",
			ARRAY_A
		);

		return array(
			'filters' => $normalized,
			'summary' => array(
				'views'     => absint( $summary['views'] ?? 0 ),
				'visitors'  => absint( $summary['visitors'] ?? 0 ),
				'pages'     => absint( $summary['pages'] ?? 0 ),
				'languages' => absint( $summary['languages'] ?? 0 ),
			),
			'daily'   => $this->fill_daily_rows( $daily_rows, $normalized['date_from'], $normalized['date_to'] ),
			'items'   => is_array( $page_rows ) ? $page_rows : array(),
			'total'   => $total,
			'pages'   => $pages,
			'page'    => $page,
			'per_page'=> $per_page,
		);
	}

	/**
	 * Add unique visitor and total view counts to cache-history items.
	 *
	 * @param array<int,array<string,mixed>> $items Cache items.
	 * @return array<int,array<string,mixed>>
	 */
	public function attach_counts( array $items ): array {
		global $wpdb;

		$hashes = array();
		foreach ( $items as $item ) {
			$url      = (string) ( $item['url'] ?? '' );
			$language = sanitize_key( (string) ( $item['language'] ?? '' ) );
			if ( '' !== $url && '' !== $language ) {
				$hashes[] = self::page_hash( $url, $language );
			}
		}

		$hashes = array_values( array_unique( $hashes ) );
		$counts = array();
		if ( ! empty( $hashes ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
			$table_name   = self::table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and placeholders are generated from a bounded item list.
			$sql  = $wpdb->prepare( "SELECT page_hash, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table_name} WHERE page_hash IN ({$placeholders}) GROUP BY page_hash", $hashes );
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$counts[ (string) $row['page_hash'] ] = array(
					'views'    => absint( $row['views'] ?? 0 ),
					'visitors' => absint( $row['visitors'] ?? 0 ),
				);
			}
		}

		foreach ( $items as &$item ) {
			$url      = (string) ( $item['url'] ?? '' );
			$language = sanitize_key( (string) ( $item['language'] ?? '' ) );
			$hash     = '' !== $url && '' !== $language ? self::page_hash( $url, $language ) : '';
			$item['views']    = absint( $counts[ $hash ]['views'] ?? 0 );
			$item['visitors'] = absint( $counts[ $hash ]['visitors'] ?? 0 );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Normalize a source-language URL for report identity.
	 */
	public static function normalize_url( string $url ): string {
		$url    = trim( $url );
		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) ) {
			return '';
		}

		$scheme = strtolower( (string) ( $parsed['scheme'] ?? wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) ?? 'https' ) );
		$host   = strtolower( (string) ( $parsed['host'] ?? wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ?? '' ) );
		$port   = isset( $parsed['port'] ) ? ':' . absint( $parsed['port'] ) : '';
		$path   = '/' . ltrim( (string) ( $parsed['path'] ?? '/' ), '/' );
		$path   = preg_replace( '#/+#', '/', $path ) ?: '/';

		if ( '/' !== $path ) {
			$path = trailingslashit( $path );
		}

		return '' !== $host ? $scheme . '://' . $host . $port . $path : $path;
	}

	/**
	 * Remove WordPress's trailing /embed/ endpoint from URLs shown in reports.
	 */
	public static function display_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$clean = preg_replace( '~/embed/?(?=([?#].*)?$)~i', '/', $url );
		return is_string( $clean ) && '' !== $clean ? $clean : $url;
	}

	public static function page_hash( string $url, string $language ): string {
		return hash( 'sha256', self::normalize_url( $url ) . '|' . sanitize_key( $language ) );
	}

	private function normalize_filters( array $filters ): array {
		$today     = wp_date( 'Y-m-d' );
		$date_from = $this->valid_date( (string) ( $filters['date_from'] ?? '' ) ) ?: wp_date( 'Y-m-d', time() - ( 29 * DAY_IN_SECONDS ) );
		$date_to   = $this->valid_date( (string) ( $filters['date_to'] ?? '' ) ) ?: $today;

		if ( $date_from > $date_to ) {
			$temp      = $date_from;
			$date_from = $date_to;
			$date_to   = $temp;
		}

		$from_object = new \DateTimeImmutable( $date_from );
		$to_object   = new \DateTimeImmutable( $date_to );
		if ( (int) $from_object->diff( $to_object )->days > 366 ) {
			$date_from = $to_object->modify( '-365 days' )->format( 'Y-m-d' );
		}

		$language = sanitize_key( (string) ( $filters['language'] ?? 'all' ) );
		if ( 'all' !== $language && ! Language_Catalog::exists( $language ) ) {
			$language = 'all';
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'language'  => $language,
			'url'       => sanitize_text_field( (string) ( $filters['url'] ?? '' ) ),
			'page'      => max( 1, absint( $filters['page'] ?? 1 ) ),
		);
	}

	/**
	 * @return array{sql:string,args:array<int,string>}
	 */
	private function where_sql( array $filters ): array {
		global $wpdb;

		$clauses = array( 'visit_date >= %s', 'visit_date <= %s' );
		$args    = array( $filters['date_from'], $filters['date_to'] );

		if ( 'all' !== $filters['language'] ) {
			$clauses[] = 'language = %s';
			$args[]    = $filters['language'];
		}

		if ( '' !== $filters['url'] ) {
			$clauses[] = 'page_url LIKE %s';
			$args[]    = '%' . $wpdb->esc_like( $filters['url'] ) . '%';
		}

		$sql = 'WHERE ' . implode( ' AND ', $clauses );
		return array(
			'sql'  => (string) $wpdb->prepare( $sql, $args ),
			'args' => $args,
		);
	}

	private function valid_date( string $date ): string {
		$date = sanitize_text_field( $date );
		$dt   = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date ? $date : '';
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Database rows.
	 * @return array<int,array{date:string,views:int,visitors:int}>
	 */
	private function fill_daily_rows( array $rows, string $date_from, string $date_to ): array {
		$indexed = array();
		foreach ( $rows as $row ) {
			$indexed[ (string) $row['visit_date'] ] = array(
				'views'    => absint( $row['views'] ?? 0 ),
				'visitors' => absint( $row['visitors'] ?? 0 ),
			);
		}

		$output = array();
		$start  = new \DateTimeImmutable( $date_from );
		$end    = new \DateTimeImmutable( $date_to );
		for ( $cursor = $start; $cursor <= $end; $cursor = $cursor->modify( '+1 day' ) ) {
			$date = $cursor->format( 'Y-m-d' );
			$output[] = array(
				'date'     => $date,
				'views'    => absint( $indexed[ $date ]['views'] ?? 0 ),
				'visitors' => absint( $indexed[ $date ]['visitors'] ?? 0 ),
			);
		}

		return $output;
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'localizepilot_visits';
	}

	public static function db_version_option(): string {
		return self::DB_VERSION_OPTION;
	}

	public static function cleanup_hook(): string {
		return self::CLEANUP_HOOK;
	}
}
