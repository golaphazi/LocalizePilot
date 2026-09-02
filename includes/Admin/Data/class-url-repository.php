<?php
/**
 * Read model for the SEO & URLs screen.
 *
 * Everything here is measured, not estimated. The plugin already builds
 * language-prefixed URLs (Router) and already prints an hreflang alternate for
 * every enabled language (Plugin::output_hreflang), so both the URL status and
 * the hreflang column describe real behaviour of the running site:
 *
 * - URL status  — whether a page's translations are still current, which the
 *                 needs_update status records.
 * - Hreflang    — whether every alternate this plugin prints actually resolves
 *                 to a translation. It prints one per enabled language whether
 *                 or not a translation exists, so partial coverage is a real
 *                 warning, not a placeholder.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Router;
use LocalizePilot\Translation_Manager;

defined( 'ABSPATH' ) || exit;

final class Url_Repository {
	public const PER_PAGE = 20;

	private const CACHE_KEY = 'localizepilot_url_coverage';

	private Router $router;

	/**
	 * Enabled target languages, source excluded.
	 *
	 * @var array<int,string>
	 */
	private array $targets;

	private string $source;

	public function __construct() {
		$settings      = Plugin::instance()->get_settings();
		$this->router  = new Router();
		$this->source  = strtolower( (string) ( $settings['source_language'] ?? 'en' ) );
		$this->targets = array_values(
			array_filter(
				array_map( 'strtolower', (array) ( $settings['enabled_languages'] ?? array() ) ),
				function ( string $code ): bool {
					return $code !== $this->source && Language_Catalog::exists( $code );
				}
			)
		);
	}

	/**
	 * @return array<int,string>
	 */
	public function targets(): array {
		return $this->targets;
	}

	public function source(): string {
		return $this->source;
	}

	/**
	 * The language URL structure card: the pattern, the source row, and one
	 * row per translated language, all built from the real router.
	 *
	 * @return array{pattern:string,source:array<string,string>,translated:array<int,array<string,string>>}
	 */
	public function structure(): array {
		$example = $this->example_url();

		$source = array(
			'code' => $this->source,
			'name' => Language_Catalog::label( $this->source, 'english' ),
			'url'  => $example,
		);

		$translated = array();

		foreach ( $this->targets as $code ) {
			$translated[] = array(
				'code' => $code,
				'name' => Language_Catalog::label( $code, 'english' ),
				'url'  => $this->router->localize_url( $example, $code ),
			);
		}

		return array(
			'pattern'    => '/{language}/{path}/',
			'source'     => $source,
			'translated' => $translated,
		);
	}

	/**
	 * The Localized Internal Links card: a real source path and the path a
	 * link to it is rewritten to on a translated page.
	 *
	 * Plugin::localize_links() rewrites every in-scope anchor on a translated
	 * page, so this is a description of live behaviour whenever a target
	 * language is enabled.
	 *
	 * @return array{source:string,language:string,translated:string,active:bool}
	 */
	public function internal_links(): array {
		$example  = $this->example_url();
		$language = $this->targets[0] ?? '';

		return array(
			'source'     => $this->path_of( $example ),
			'language'   => '' !== $language ? Language_Catalog::label( $language, 'english' ) : '',
			'translated' => '' !== $language ? $this->path_of( $this->router->localize_url( $example, $language ) ) : '',
			'active'     => '' !== $language,
		);
	}

	/**
	 * Published content with its language relationships, for the URL table.
	 *
	 * @param array<string,mixed> $filters {language, status, type, search, order, issues, paged}.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function query( array $filters = array() ): array {
		$page   = max( 1, (int) ( $filters['paged'] ?? 1 ) );
		$type   = (string) ( $filters['type'] ?? '' );
		$search = sanitize_text_field( (string) ( $filters['search'] ?? '' ) );

		$args = array(
			'post_type'      => in_array( $type, $this->post_types(), true ) ? $type : $this->post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'oldest' === ( $filters['order'] ?? '' ) ? 'modified' : 'modified',
			'order'          => 'oldest' === ( $filters['order'] ?? '' ) ? 'ASC' : 'DESC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query    = new \WP_Query( $args );
		$coverage = $this->coverage_for( wp_list_pluck( $query->posts, 'ID' ) );
		$items    = array();

		foreach ( $query->posts as $post ) {
			$row = $this->to_row( $post, $coverage[ $post->ID ] ?? array() );

			// Post-filters, applied to the shaped row rather than the query,
			// because none of these live in a single meta value.
			if ( ! empty( $filters['issues'] ) && 'reviewed' === $row['url_status'] && 'reviewed' === $row['hreflang_status'] ) {
				continue;
			}

			$language = sanitize_key( (string) ( $filters['language'] ?? '' ) );

			if ( '' !== $language && ! in_array( $language, $row['languages'], true ) ) {
				continue;
			}

			$status = sanitize_key( (string) ( $filters['status'] ?? '' ) );

			if ( '' !== $status && $status !== $row['url_status'] ) {
				continue;
			}

			$items[] = $row;
		}

		return array(
			'items'    => $items,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => self::PER_PAGE,
		);
	}

	/**
	 * One published page, shaped the same way query() shapes a row.
	 *
	 * Used when a drawer is asked for on its own, by deep link or by the
	 * drawer endpoint, without re-running the list query.
	 *
	 * @param int $post_id Source post.
	 * @return array<string,mixed>|null
	 */
	public function row( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return null;
		}

		$coverage = $this->coverage_for( array( $post_id ) );

		return $this->to_row( $post, $coverage[ $post_id ] ?? array() );
	}

	/**
	 * Everything the drawer shows for one row.
	 *
	 * Built from the row the table already has, so opening a drawer costs no
	 * further queries. The same payload serves both designed drawers: a stale
	 * row gets the warning banner and the review action, everything else gets
	 * the plain detail view.
	 *
	 * @param array<string,mixed> $row A row from query().
	 * @return array<string,mixed>
	 */
	public function detail( array $row ): array {
		$states = (array) ( $row['states'] ?? array() );
		$url    = (string) ( $row['url'] ?? '' );

		$languages = array(
			array(
				'code'   => $this->source,
				'name'   => Language_Catalog::label( $this->source, 'english' ),
				'url'    => $url,
				'tone'   => 'reviewed',
				'label'  => __( 'Healthy', 'localizepilot' ),
				'source' => true,
			),
		);

		foreach ( $this->targets as $code ) {
			$state = (string) ( $states[ $code ] ?? '' );

			$languages[] = array(
				'code'   => $code,
				'name'   => Language_Catalog::label( $code, 'english' ),
				'url'    => $this->router->localize_url( $url, $code ),
				'tone'   => $this->language_tone( $state ),
				'label'  => $this->language_label( $state ),
				'source' => false,
			);
		}

		// The alternates Plugin::output_hreflang() prints for this page, in the
		// order it prints them.
		$signals = array();

		foreach ( array_merge( array( $this->source ), $this->targets ) as $code ) {
			$signals[] = array(
				'code' => strtoupper( $code ),
				'url'  => $code === $this->source ? $url : $this->router->localize_url( $url, $code ),
			);
		}

		$signals[] = array(
			'code' => 'x-default',
			'url'  => $url,
		);

		$stale_names = array_map(
			static function ( string $code ): string {
				return Language_Catalog::label( $code, 'english' );
			},
			(array) ( $row['stale_languages'] ?? array() )
		);

		return array(
			'id'          => (int) ( $row['id'] ?? 0 ),
			'title'       => (string) ( $row['title'] ?? '' ),
			'type'        => (string) ( $row['type'] ?? '' ),
			'source_name' => Language_Catalog::label( $this->source, 'english' ),
			'url'         => $url,
			'path'        => (string) ( $row['path'] ?? '' ),
			'languages'   => $languages,
			'canonical'   => $url,
			'signals'     => $signals,
			'signal_tone' => (string) ( $row['hreflang_status'] ?? 'neutral' ),
			'signal_label' => (string) ( $row['hreflang_label'] ?? '' ),
			'stale'       => ! empty( $row['stale'] ),
			'stale_names' => $stale_names,
			'edit_url'    => (string) ( $row['edit_url'] ?? '' ),
		);
	}

	/**
	 * The four summary figures.
	 *
	 * @return array<string,mixed>
	 */
	public function counts(): array {
		$stats      = $this->site_coverage();
		$sources    = $this->translatable_total();
		$languages  = count( $this->targets ) + 1;
		$healthy    = 0;
		$stale      = 0;
		$partial    = 0;
		$translated = 0;

		foreach ( $stats as $row ) {
			$translated += (int) $row['languages'];

			if ( (int) $row['stale'] > 0 ) {
				++$stale;
				continue;
			}

			if ( (int) $row['languages'] >= count( $this->targets ) ) {
				++$healthy;
				continue;
			}

			++$partial;
		}

		// Pages with no translation at all are the rest of the library.
		$untranslated = max( 0, $sources - count( $stats ) );

		return array(
			'urls'         => $sources * $languages,
			'languages'    => $languages,
			'translated'   => $translated,
			'sources'      => $sources,
			'healthy'      => $healthy,
			'stale'        => $stale,
			'partial'      => $partial + $untranslated,
			'issues'       => max( 0, $sources - $healthy ),
			'hreflang_on'  => ! empty( $this->targets ),
		);
	}

	/**
	 * The "Needs Your Attention" rows, each one a real count.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function attention(): array {
		$counts = $this->counts();
		$items  = array();

		if ( $counts['stale'] > 0 ) {
			$items[] = array(
				'icon'   => 'clock',
				'tone'   => 'warning',
				'title'  => sprintf(
					/* translators: %s is a number of URLs. */
					_n( '%s translated URL needs review', '%s translated URLs need review', (int) $counts['stale'], 'localizepilot' ),
					number_format_i18n( (int) $counts['stale'] )
				),
				'meta'   => __( 'Source content changed recently.', 'localizepilot' ),
				'action' => __( 'Review', 'localizepilot' ),
				'url'    => add_query_arg( 'status', 'needs_update', Screen_Registry::url( 'translations' ) ),
			);
		}

		if ( $counts['partial'] > 0 ) {
			$items[] = array(
				'icon'   => 'alert-triangle',
				'tone'   => 'warning',
				'title'  => sprintf(
					/* translators: %s is a number of pages. */
					_n( '%s page is missing a translation', '%s pages are missing translations', (int) $counts['partial'], 'localizepilot' ),
					number_format_i18n( (int) $counts['partial'] )
				),
				'meta'   => __( 'Some translated pages are not connected.', 'localizepilot' ),
				'action' => __( 'Review', 'localizepilot' ),
				'url'    => Screen_Registry::url( 'translations' ),
			);
		}

		if ( empty( $this->targets ) ) {
			$items[] = array(
				'icon'   => 'link',
				'tone'   => 'danger',
				'title'  => __( 'No hreflang alternates are being published', 'localizepilot' ),
				'meta'   => __( 'Enable a language to connect your URLs.', 'localizepilot' ),
				'action' => __( 'Review', 'localizepilot' ),
				'url'    => Screen_Registry::url( 'languages' ),
			);
		}

		if ( empty( $items ) ) {
			$items[] = array(
				'icon'  => 'link',
				'tone'  => 'success',
				'title' => __( 'Every URL is connected', 'localizepilot' ),
				'meta'  => __( 'Nothing needs attention right now.', 'localizepilot' ),
				'url'   => Screen_Registry::url( 'translations' ),
			);
		}

		return $items;
	}

	/**
	 * Content types available as a filter, limited to what the site publishes.
	 *
	 * @return array<string,string>
	 */
	public function type_options(): array {
		$options = array( '' => __( 'All Content Types', 'localizepilot' ) );

		foreach ( $this->post_types() as $type ) {
			$object = get_post_type_object( $type );

			if ( $object instanceof \WP_Post_Type ) {
				$options[ $type ] = $object->labels->name;
			}
		}

		return $options;
	}

	/**
	 * Enabled languages as filter options.
	 *
	 * @return array<string,string>
	 */
	public function language_options(): array {
		$options = array( '' => __( 'All Languages', 'localizepilot' ) );

		foreach ( $this->targets as $code ) {
			$options[ $code ] = Language_Catalog::label( $code, 'english' );
		}

		return $options;
	}

	/**
	 * URL status values as filter options.
	 *
	 * @return array<string,string>
	 */
	public function status_options(): array {
		return array(
			''             => __( 'All Status', 'localizepilot' ),
			'reviewed'     => __( 'Healthy', 'localizepilot' ),
			'needs_update' => __( 'Needs Review', 'localizepilot' ),
			'neutral'      => __( 'Not translated', 'localizepilot' ),
		);
	}

	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<int,string>
	 */
	private function post_types(): array {
		return array( 'page', 'post' );
	}

	private function translatable_total(): int {
		$total = 0;

		foreach ( $this->post_types() as $type ) {
			$counts = wp_count_posts( $type );
			$total += (int) ( $counts->publish ?? 0 );
		}

		return $total;
	}

	/**
	 * A real permalink to shape the example URLs from.
	 *
	 * Prefers a page with a path of its own, because the point of the card is
	 * to show what /{language}/{path}/ looks like and the front page has no
	 * path to prefix. Falls back to the front page, then the site root.
	 */
	private function example_url(): string {
		static $url = null;

		if ( null !== $url ) {
			return $url;
		}

		$pages = get_posts(
			array(
				'post_type'      => $this->post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $pages as $page ) {
			$candidate = (string) get_permalink( $page );

			if ( '/' !== $this->path_of( $candidate ) ) {
				$url = $candidate;

				return $url;
			}
		}

		$front = (int) get_option( 'page_on_front' );
		$url   = $front > 0 && 'publish' === get_post_status( $front )
			? (string) get_permalink( $front )
			: home_url( '/' );

		return $url;
	}

	/**
	 * The path part of a URL, which is what the internal-links card shows.
	 */
	private function path_of( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return '' !== $path ? $path : '/';
	}

	/**
	 * Shape one published page for the table.
	 *
	 * @param \WP_Post             $post   Source post.
	 * @param array<string,string> $states Translation status keyed by language.
	 * @return array<string,mixed>
	 */
	private function to_row( \WP_Post $post, array $states ): array {
		$object    = get_post_type_object( $post->post_type );
		$languages = array_keys( $states );
		$stale     = array_keys( array_filter( $states, static fn( string $state ): bool => 'needs_update' === $state ) );
		$covered   = count( array_intersect( $this->targets, $languages ) );
		$url       = (string) get_permalink( $post );

		if ( ! empty( $stale ) ) {
			$url_status = 'needs_update';
			$url_label  = __( 'Needs Review', 'localizepilot' );
		} elseif ( $covered > 0 ) {
			$url_status = 'reviewed';
			$url_label  = __( 'Healthy', 'localizepilot' );
		} else {
			$url_status = 'neutral';
			$url_label  = __( 'Not translated', 'localizepilot' );
		}

		if ( 0 === count( $this->targets ) ) {
			$hreflang_status = 'neutral';
			$hreflang_label  = __( 'Not published', 'localizepilot' );
		} elseif ( $covered >= count( $this->targets ) ) {
			$hreflang_status = 'reviewed';
			$hreflang_label  = __( 'Connected', 'localizepilot' );
		} elseif ( $covered > 0 ) {
			$hreflang_status = 'needs_update';
			$hreflang_label  = __( 'Warning', 'localizepilot' );
		} else {
			$hreflang_status = 'neutral';
			$hreflang_label  = __( 'Not connected', 'localizepilot' );
		}

		return array(
			'id'              => $post->ID,
			'title'           => get_the_title( $post ),
			'states'          => $states,
			'type'            => $object instanceof \WP_Post_Type ? $object->labels->singular_name : $post->post_type,
			'url'             => $url,
			'path'            => $this->path_of( $url ),
			'languages'       => array_values( array_intersect( $this->targets, $languages ) ),
			'covered'         => $covered,
			'stale'           => ! empty( $stale ),
			'stale_languages' => array_values( $stale ),
			'url_status'      => $url_status,
			'url_label'       => $url_label,
			'hreflang_status' => $hreflang_status,
			'hreflang_label'  => $hreflang_label,
			'updated'         => (int) get_post_modified_time( 'U', true, $post ),
			'edit_url'        => (string) get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	private function language_tone( string $state ): string {
		if ( '' === $state ) {
			return 'neutral';
		}

		return 'needs_update' === $state ? 'needs_update' : 'reviewed';
	}

	private function language_label( string $state ): string {
		if ( '' === $state ) {
			return __( 'Missing', 'localizepilot' );
		}

		return 'needs_update' === $state
			? __( 'Needs Review', 'localizepilot' )
			: __( 'Healthy', 'localizepilot' );
	}

	/**
	 * Translation status per language for a set of source posts.
	 *
	 * One query for the whole page of results, rather than one per row.
	 *
	 * @param array<int,int> $source_ids Source post IDs.
	 * @return array<int,array<string,string>> Source ID => language => status.
	 */
	private function coverage_for( array $source_ids ): array {
		$source_ids = array_values( array_filter( array_map( 'intval', $source_ids ) ) );

		if ( empty( $source_ids ) ) {
			return array();
		}

		global $wpdb;

		$ids = implode( ',', $source_ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids is built from intval()ed values.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT src.meta_value AS source_id,
				        lang.meta_value AS language,
				        status.meta_value AS status
				 FROM {$wpdb->posts} AS posts
				 INNER JOIN {$wpdb->postmeta} AS src
				         ON src.post_id = posts.ID AND src.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} AS lang
				         ON lang.post_id = posts.ID AND lang.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} AS status
				        ON status.post_id = posts.ID AND status.meta_key = %s
				 WHERE posts.post_type = %s
				   AND posts.post_status IN ('publish','draft','pending','private')
				   AND src.meta_value IN ({$ids})",
				Translation_Manager::META_SOURCE_ID,
				Translation_Manager::META_LANGUAGE,
				Translation_Manager::META_STATUS,
				Translation_Manager::POST_TYPE
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$coverage = array();

		foreach ( (array) $rows as $row ) {
			$source   = (int) ( $row['source_id'] ?? 0 );
			$language = sanitize_key( (string) ( $row['language'] ?? '' ) );

			if ( $source < 1 || '' === $language ) {
				continue;
			}

			$coverage[ $source ][ $language ] = sanitize_key( (string) ( $row['status'] ?? '' ) ) ?: 'automatic';
		}

		return $coverage;
	}

	/**
	 * Language count and staleness per translated source, for the whole site.
	 *
	 * Bounded by the number of translated pages and cached for five minutes,
	 * the same shape as the other console aggregates.
	 *
	 * @return array<int,array<string,int>>
	 */
	private function site_coverage(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT src.meta_value AS source_id,
				        COUNT(DISTINCT lang.meta_value) AS languages,
				        SUM(CASE WHEN status.meta_value = 'needs_update' THEN 1 ELSE 0 END) AS stale
				 FROM {$wpdb->posts} AS posts
				 INNER JOIN {$wpdb->postmeta} AS src
				         ON src.post_id = posts.ID AND src.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} AS lang
				         ON lang.post_id = posts.ID AND lang.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} AS status
				        ON status.post_id = posts.ID AND status.meta_key = %s
				 WHERE posts.post_type = %s
				   AND posts.post_status IN ('publish','draft','pending','private')
				 GROUP BY src.meta_value",
				Translation_Manager::META_SOURCE_ID,
				Translation_Manager::META_LANGUAGE,
				Translation_Manager::META_STATUS,
				Translation_Manager::POST_TYPE
			),
			ARRAY_A
		);

		$stats = array();

		foreach ( (array) $rows as $row ) {
			$stats[ (int) $row['source_id'] ] = array(
				'languages' => (int) $row['languages'],
				'stale'     => (int) $row['stale'],
			);
		}

		set_transient( self::CACHE_KEY, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}
}
