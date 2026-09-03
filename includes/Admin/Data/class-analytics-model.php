<?php
/**
 * Read model for the Analytics screen.
 *
 * Wraps Analytics::report(), which already returns every figure the design
 * needs, and adds the period-over-period deltas the KPI cards show.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Analytics;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Router;

defined( 'ABSPATH' ) || exit;

final class Analytics_Model {
	private Analytics $analytics;

	public function __construct() {
		$this->analytics = new Analytics( new Router() );
	}

	/**
	 * Whether first-party analytics collection is switched on.
	 */
	public function is_enabled(): bool {
		return ! empty( Plugin::instance()->get_settings()['analytics_enabled'] );
	}

	/**
	 * The report for the current filters, plus deltas against the window
	 * immediately before it.
	 *
	 * @param array<string,mixed> $filters Filters accepted by Analytics::report().
	 * @return array<string,mixed>
	 */
	public function report( array $filters = array() ): array {
		$report = $this->analytics->report( $filters );
		$window = $report['filters'] ?? array();

		$report['deltas'] = $this->deltas( $report['summary'] ?? array(), $window );
		$report['chart']  = $this->chart( (array) ( $report['daily'] ?? array() ) );

		return $report;
	}

	/**
	 * Percentage change per summary metric against the preceding window of the
	 * same length. Returns null for a metric with no prior data, so the
	 * template can leave the delta off rather than print a misleading +100%.
	 *
	 * @param array<string,int>   $summary Current-window totals.
	 * @param array<string,mixed> $window  Normalized filters, carrying the dates.
	 * @return array<string,int|null>
	 */
	private function deltas( array $summary, array $window ): array {
		$from = (string) ( $window['date_from'] ?? '' );
		$to   = (string) ( $window['date_to'] ?? '' );

		if ( '' === $from || '' === $to ) {
			return array();
		}

		$start = strtotime( $from );
		$end   = strtotime( $to );

		if ( false === $start || false === $end || $end < $start ) {
			return array();
		}

		$length = max( 1, (int) round( ( $end - $start ) / DAY_IN_SECONDS ) + 1 );

		$previous = $this->analytics->report(
			array_merge(
				$window,
				array(
					'date_from' => gmdate( 'Y-m-d', $start - ( $length * DAY_IN_SECONDS ) ),
					'date_to'   => gmdate( 'Y-m-d', $start - DAY_IN_SECONDS ),
					'page'      => 1,
				)
			)
		);

		$before = (array) ( $previous['summary'] ?? array() );
		$deltas = array();

		foreach ( array( 'visitors', 'views', 'pages', 'languages' ) as $metric ) {
			$was = (int) ( $before[ $metric ] ?? 0 );
			$now = (int) ( $summary[ $metric ] ?? 0 );

			$deltas[ $metric ] = $was > 0 ? (int) round( 100 * ( $now - $was ) / $was ) : null;
		}

		return $deltas;
	}

	/**
	 * Turn the daily rows into the two series the chart draws.
	 *
	 * @param array<int,array<string,mixed>> $daily Rows from Analytics::report().
	 * @return array{labels:array<int,string>,series:array<int,array<string,mixed>>}
	 */
	private function chart( array $daily ): array {
		$labels   = array();
		$views    = array();
		$visitors = array();

		foreach ( $daily as $row ) {
			$date       = (string) ( $row['visit_date'] ?? '' );
			$timestamp  = strtotime( $date );
			$labels[]   = false !== $timestamp ? date_i18n( 'M j', $timestamp ) : $date;
			$views[]    = (int) ( $row['views'] ?? 0 );
			$visitors[] = (int) ( $row['visitors'] ?? 0 );
		}

		return array(
			'labels' => $labels,
			'series' => array(
				array(
					'label'  => __( 'Page views', 'localizepilot' ),
					'tone'   => 'brand',
					'area'   => true,
					'points' => $views,
				),
				array(
					'label'  => __( 'Unique visitors', 'localizepilot' ),
					'tone'   => 'ink',
					'points' => $visitors,
				),
			),
		);
	}

	/**
	 * Visitor activity grouped by language, for the Language Performance table.
	 *
	 * @param array<string,mixed> $filters Filters accepted by Analytics::report().
	 * @return array<int,array<string,mixed>>
	 */
	public function by_language( array $filters = array() ): array {
		global $wpdb;

		$report = $this->analytics->report( array_merge( $filters, array( 'language' => '' ) ) );
		$rows   = array();
		$total  = max( 1, (int) ( $report['summary']['views'] ?? 0 ) );

		$table = Analytics::table_name();
		$from  = (string) ( $report['filters']['date_from'] ?? '' );
		$to    = (string) ( $report['filters']['date_to'] ?? '' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal; bounds are prepared.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, MAX(visited_at) AS last_visit
				 FROM {$table}
				 WHERE visit_date BETWEEN %s AND %s
				 GROUP BY language
				 ORDER BY visitors DESC, views DESC",
				$from,
				$to
			),
			ARRAY_A
		);

		$source = strtolower( (string) ( Plugin::instance()->get_settings()['source_language'] ?? 'en' ) );

		foreach ( (array) $results as $row ) {
			$code = sanitize_key( (string) ( $row['language'] ?? '' ) );

			$rows[] = array(
				'code'       => $code,
				'name'       => Language_Catalog::label( $code, 'english' ),
				'is_source'  => $code === $source,
				'visitors'   => (int) ( $row['visitors'] ?? 0 ),
				'views'      => (int) ( $row['views'] ?? 0 ),
				'share'      => (int) round( 100 * (int) ( $row['views'] ?? 0 ) / $total ),
				'last_visit' => (string) ( $row['last_visit'] ?? '' ),
			);
		}

		return $rows;
	}

	/**
	 * Filters describing the window immediately before the given one.
	 *
	 * @param array<string,mixed> $window Normalized filters from a report.
	 * @return array<string,mixed>|null Null when the window has no usable dates.
	 */
	private function previous_window( array $window ): ?array {
		$from = strtotime( (string) ( $window['date_from'] ?? '' ) );
		$to   = strtotime( (string) ( $window['date_to'] ?? '' ) );

		if ( false === $from || false === $to || $to < $from ) {
			return null;
		}

		$length = max( 1, (int) round( ( $to - $from ) / DAY_IN_SECONDS ) + 1 );

		return array_merge(
			$window,
			array(
				'date_from' => gmdate( 'Y-m-d', $from - ( $length * DAY_IN_SECONDS ) ),
				'date_to'   => gmdate( 'Y-m-d', $from - DAY_IN_SECONDS ),
				'page'      => 1,
			)
		);
	}

	/**
	 * Visitors and views per language for one window, keyed by language code.
	 *
	 * @param array<string,mixed> $filters Filters accepted by Analytics::report().
	 * @return array<string,array{visitors:int,views:int}>
	 */
	private function language_totals( array $filters ): array {
		global $wpdb;

		$report = $this->analytics->report( array_merge( $filters, array( 'language' => '' ) ) );
		$table  = Analytics::table_name();
		$from   = (string) ( $report['filters']['date_from'] ?? '' );
		$to     = (string) ( $report['filters']['date_to'] ?? '' );

		if ( '' === $from || '' === $to ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal; bounds are prepared.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
				 FROM {$table}
				 WHERE visit_date BETWEEN %s AND %s
				 GROUP BY language",
				$from,
				$to
			),
			ARRAY_A
		);

		$totals = array();

		foreach ( (array) $results as $row ) {
			$code = sanitize_key( (string) ( $row['language'] ?? '' ) );

			if ( '' === $code ) {
				continue;
			}

			$totals[ $code ] = array(
				'visitors' => (int) ( $row['visitors'] ?? 0 ),
				'views'    => (int) ( $row['views'] ?? 0 ),
			);
		}

		return $totals;
	}

	/**
	 * The Language Insights card.
	 *
	 * Every row is derived from recorded activity. A row whose comparison has
	 * no prior data to stand on is left out rather than guessed at, so this can
	 * legitimately return fewer than three rows, or none at all.
	 *
	 * @param array<string,mixed> $filters Filters accepted by Analytics::report().
	 * @return array<int,array{icon:string,tone:string,title:string,note:string}>
	 */
	public function insights( array $filters = array() ): array {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		$languages = $this->by_language( $filters );

		if ( empty( $languages ) ) {
			return array();
		}

		$report   = $this->analytics->report( $filters );
		$window   = (array) ( $report['filters'] ?? array() );
		$previous = $this->previous_window( $window );
		$before   = null !== $previous ? $this->language_totals( $previous ) : array();
		$insights = array();

		// Biggest visitor increase against the preceding window of equal length.
		$growth = array();

		foreach ( $languages as $language ) {
			$code = (string) $language['code'];
			$was  = (int) ( $before[ $code ]['visitors'] ?? 0 );
			$now  = (int) $language['visitors'];

			if ( $was > 0 && $now > $was ) {
				$growth[ $code ] = array(
					'name'    => (string) $language['name'],
					'percent' => (int) round( 100 * ( $now - $was ) / $was ),
				);
			}
		}

		uasort( $growth, static fn( array $a, array $b ): int => $b['percent'] <=> $a['percent'] );

		if ( ! empty( $growth ) ) {
			$top = reset( $growth );

			$insights[] = array(
				'icon'  => 'arrow-right',
				'tone'  => 'success',
				/* translators: %s: Language name. */
				'title' => sprintf( __( '%s is your fastest-growing language', 'localizepilot' ), $top['name'] ),
				/* translators: %d: Percentage increase in visitors. */
				'note'  => sprintf( __( '+%d%% visitors this period', 'localizepilot' ), $top['percent'] ),
			);
		}

		// Largest share of recorded page views.
		$leader = $languages[0];

		foreach ( $languages as $language ) {
			if ( (int) $language['views'] > (int) $leader['views'] ) {
				$leader = $language;
			}
		}

		if ( (int) $leader['views'] > 0 ) {
			$insights[] = array(
				'icon'  => 'nav-analytics',
				'tone'  => 'brand',
				/* translators: %s: Language name. */
				'title' => sprintf( __( '%s drives the most traffic', 'localizepilot' ), (string) $leader['name'] ),
				/* translators: %d: Percentage of total page views. */
				'note'  => sprintf( __( '%d%% of total page views', 'localizepilot' ), (int) $leader['share'] ),
			);
		}

		// A second riser, so the card fills out when the data supports it.
		if ( count( $growth ) > 1 ) {
			array_shift( $growth );
			$second = reset( $growth );

			$insights[] = array(
				'icon'  => 'clock',
				'tone'  => 'info',
				/* translators: %s: Language name. */
				'title' => sprintf( __( '%s engagement is increasing', 'localizepilot' ), $second['name'] ),
				/* translators: %d: Percentage increase. */
				'note'  => sprintf( __( '+%d%% compared with the previous period', 'localizepilot' ), $second['percent'] ),
			);
		}

		return array_slice( $insights, 0, 3 );
	}

	/**
	 * The Needs Attention card.
	 *
	 * Counts of things that are genuinely checkable: languages that are not
	 * fully translated, content with no recorded activity, and languages that
	 * lost traffic against the preceding window. A count of zero is dropped, so
	 * the card never manufactures a problem to look busy.
	 *
	 * @param array<string,mixed> $filters Filters accepted by Analytics::report().
	 * @return array<int,array{count:int,label:string,action:string,url:string,tone:string}>
	 */
	public function attention( array $filters = array() ): array {
		$stats     = new Language_Stats();
		$languages = $stats->enabled();
		$rows      = array();

		// Enabled languages that are not fully translated.
		$incomplete = 0;

		foreach ( $languages as $language ) {
			if ( empty( $language['is_source'] ) && (int) $language['coverage'] < 100 ) {
				$incomplete++;
			}
		}

		if ( $incomplete > 0 ) {
			$rows[] = array(
				'count'  => $incomplete,
				'label'  => _n(
					'language has incomplete localization',
					'languages have incomplete localization',
					$incomplete,
					'localizepilot'
				),
				'action' => __( 'Review languages', 'localizepilot' ),
				'url'    => Screen_Registry::url( 'languages' ),
				'tone'   => 'warning',
			);
		}

		if ( ! $this->is_enabled() ) {
			return $rows;
		}

		$report = $this->analytics->report( $filters );
		$active = (int) ( $report['summary']['pages'] ?? 0 );
		$total  = 0;

		foreach ( $languages as $language ) {
			$total += (int) ( $language['translated'] ?? 0 );
		}

		$idle = max( 0, $total - $active );

		if ( $idle > 0 ) {
			$rows[] = array(
				'count'  => $idle,
				'label'  => _n(
					'localized page has no recent activity',
					'localized pages have no recent activity',
					$idle,
					'localizepilot'
				),
				'action' => __( 'Review language coverage', 'localizepilot' ),
				'url'    => Screen_Registry::url( 'translations' ),
				'tone'   => 'muted',
			);
		}

		// Languages whose page views fell against the preceding window.
		$window   = (array) ( $report['filters'] ?? array() );
		$previous = $this->previous_window( $window );

		if ( null !== $previous ) {
			$before  = $this->language_totals( $previous );
			$now     = $this->language_totals( $window );
			$dropped = 0;

			foreach ( $before as $code => $totals ) {
				if ( (int) $totals['views'] > (int) ( $now[ $code ]['views'] ?? 0 ) ) {
					$dropped++;
				}
			}

			if ( $dropped > 0 ) {
				$rows[] = array(
					'count'  => $dropped,
					'label'  => _n(
						'language received less traffic than before',
						'languages received less traffic than before',
						$dropped,
						'localizepilot'
					),
					'action' => __( 'View pages', 'localizepilot' ),
					'url'    => Screen_Registry::url( 'seo-urls' ),
					'tone'   => 'warning',
				);
			}
		}

		return array_slice( $rows, 0, 3 );
	}
}
