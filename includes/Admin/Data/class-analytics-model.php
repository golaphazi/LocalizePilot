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
}
