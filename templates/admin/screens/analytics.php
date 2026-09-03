<?php
/**
 * Analytics screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data = (array) ( $args['data'] ?? array() );

if ( empty( $lp_data['enabled'] ) ) {
	Template::render(
		'parts/card',
		array(
			'flush' => true,
			'body'  => Template::capture(
				'parts/empty-state',
				array(
					// Untitled card, so this is the section heading.
					'level'   => 2,
					'icon'    => 'nav-analytics',
					'title'   => __( 'Language analytics is switched off', 'localizepilot' ),
					'message' => __( 'Turn on first-party analytics to see which language versions visitors are actually reading. Nothing is collected until you do.', 'localizepilot' ),
					'action'  => array(
						'label' => __( 'Open Settings', 'localizepilot' ),
						'url'   => Screen_Registry::url( 'settings' ),
					),
				)
			),
		)
	);

	/*
	 * Coverage gaps do not depend on tracking, so this card still has something
	 * true to report even with collection switched off.
	 */
	Template::render(
		'parts/analytics-insights',
		array(
			'insights'     => array(),
			'attention'    => (array) ( $lp_data['attention'] ?? array() ),
			'enabled'      => false,
			'settings_url' => (string) ( $lp_data['settings_url'] ?? Screen_Registry::url( 'settings' ) ),
		)
	);

	return;
}

$lp_filters = (array) ( $lp_data['filters'] ?? array() );
$lp_report  = (array) ( $lp_data['report'] ?? array() );
$lp_summary = (array) ( $lp_report['summary'] ?? array() );
$lp_deltas  = (array) ( $lp_report['deltas'] ?? array() );
$lp_chart   = (array) ( $lp_report['chart'] ?? array() );

/**
 * Format a percentage delta for a KPI card.
 *
 * @param int|null $delta Percentage change, or null when there is no baseline.
 * @return array<string,string>
 */
$lp_delta = static function ( ?int $delta ): array {
	if ( null === $delta || 0 === $delta ) {
		return array();
	}

	return array(
		'delta'      => sprintf( '%s%s%%', $delta > 0 ? '+' : '−', number_format_i18n( abs( $delta ) ) ),
		'delta_tone' => $delta > 0 ? 'up' : 'down',
	);
};
?>

<div class="lp-stack lp-stack--cards">
	<?php
Template::render(
	'parts/card',
	array(
		'title'    => __( 'Visitor Activity', 'localizepilot' ),
		'subtitle' => __( 'Filter analytics by language, date range, or a specific page.', 'localizepilot' ),
		'body'     => Template::capture( 'parts/analytics-filters', $args ),
	)
);

Template::render(
	'parts/kpi-grid',
	array(
		'items' => array(
			array_merge(
				array(
					'label' => __( 'Unique Visitors', 'localizepilot' ),
					'value' => number_format_i18n( (int) ( $lp_summary['visitors'] ?? 0 ) ),
					'note'  => __( 'Distinct visitors in the selected period', 'localizepilot' ),
				),
				$lp_delta( $lp_deltas['visitors'] ?? null )
			),
			array_merge(
				array(
					'label' => __( 'Page Views', 'localizepilot' ),
					'value' => number_format_i18n( (int) ( $lp_summary['views'] ?? 0 ) ),
					'note'  => __( 'Total recorded page views', 'localizepilot' ),
				),
				$lp_delta( $lp_deltas['views'] ?? null )
			),
			array_merge(
				array(
					'label' => __( 'Language Pages', 'localizepilot' ),
					'value' => number_format_i18n( (int) ( $lp_summary['pages'] ?? 0 ) ),
					'note'  => __( 'Unique language and URL combinations', 'localizepilot' ),
				),
				$lp_delta( $lp_deltas['pages'] ?? null )
			),
			array(
				'label' => __( 'Languages Viewed', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_summary['languages'] ?? 0 ) ),
				'note'  => __( 'Languages with recorded activity', 'localizepilot' ),
			),
		),
	)
);

$lp_has_activity = ( (int) ( $lp_summary['views'] ?? 0 ) ) > 0;

Template::render(
	'parts/card',
	array(
		'title'    => __( 'Visitors & Page Views', 'localizepilot' ),
		'subtitle' => __( 'Daily activity for the selected filters.', 'localizepilot' ),
		'body'     => $lp_has_activity
			? Template::capture(
				'parts/line-chart',
				array(
					'id'     => 'lp-analytics-chart',
					'label'  => __( 'Visitors and page views over time', 'localizepilot' ),
					'labels' => (array) ( $lp_chart['labels'] ?? array() ),
					'series' => (array) ( $lp_chart['series'] ?? array() ),
				)
			)
			: Template::capture(
				'parts/empty-state',
				array(
					'icon'    => 'nav-analytics',
					'title'   => __( 'No activity in this period', 'localizepilot' ),
					'message' => __( 'Widen the date range, or clear the filters, to see recorded visits.', 'localizepilot' ),
				)
			),
	)
);

/* Language performance. */
$lp_language_rows = array_map(
	static function ( array $row ): array {
		return array(
			'id'    => $row['code'],
			'label' => $row['name'],
			'cells' => array(
				'language'   => '<span class="lp-cell__pair">'
					. Template::capture( 'parts/lang-chip', array( 'code' => $row['code'], 'tone' => $row['is_source'] ? 'source' : 'target' ) )
					. '<span class="lp-cell__title">' . esc_html( $row['name'] ) . '</span>'
					. ( $row['is_source'] ? '<span class="lp-cell__muted">' . esc_html__( 'Source', 'localizepilot' ) . '</span>' : '' )
					. '</span>',
				'visitors'   => '<span class="lp-cell__title">' . esc_html( number_format_i18n( $row['visitors'] ) ) . '</span>',
				'views'      => '<span class="lp-cell__muted">' . esc_html( number_format_i18n( $row['views'] ) ) . '</span>',
				'share'      => Template::capture( 'parts/progress', array( 'value' => (int) $row['share'], 'label' => true ) ),
				'last_visit' => '<span class="lp-cell__muted">' . esc_html(
					'' !== $row['last_visit']
						? date_i18n( get_option( 'date_format' ), (int) strtotime( $row['last_visit'] ) )
						: __( 'No activity', 'localizepilot' )
				) . '</span>',
			),
		);
	},
	(array) ( $lp_data['by_language'] ?? array() )
);

Template::render(
	'parts/card',
	array(
		'flush' => true,
		'title' => __( 'Language Performance', 'localizepilot' ),
		'body'  => Template::capture(
			'parts/table',
			array(
				'label'   => __( 'Language performance', 'localizepilot' ),
				'columns' => array(
					array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
					array( 'key' => 'visitors', 'label' => __( 'Unique Visitors', 'localizepilot' ) ),
					array( 'key' => 'views', 'label' => __( 'Page Views', 'localizepilot' ) ),
					array( 'key' => 'share', 'label' => __( 'Share', 'localizepilot' ) ),
					array( 'key' => 'last_visit', 'label' => __( 'Last Visit', 'localizepilot' ), 'align' => 'right' ),
				),
				'rows'    => $lp_language_rows,
				'empty'   => array(
					'icon'    => 'nav-languages',
					'title'   => __( 'No language activity yet', 'localizepilot' ),
					'message' => __( 'Once visitors reach a translated page, each language appears here.', 'localizepilot' ),
				),
			)
		),
	)
);

/* Top localized pages. */
// Share of the period's total page views, the same basis the language table uses.
$lp_view_total = max( 1, (int) ( $lp_report['summary']['views'] ?? 0 ) );

$lp_page_rows = array_map(
	static function ( array $row ) use ( $lp_view_total ): array {
		$url   = (string) ( $row['page_url'] ?? '' );
		$share = (int) round( 100 * (int) ( $row['views'] ?? 0 ) / $lp_view_total );

		return array(
			'id'    => (string) ( $row['page_hash'] ?? '' ),
			'label' => $url,
			'cells' => array(
				'page'       => '<span class="lp-cell__title">' . esc_html( \LocalizePilot\Analytics::display_url( $url ) ) . '</span>',
				'language'   => Template::capture( 'parts/lang-chip', array( 'code' => (string) ( $row['language'] ?? '' ), 'tone' => 'target' ) ),
				'visitors'   => '<span class="lp-cell__title">' . esc_html( number_format_i18n( (int) ( $row['visitors'] ?? 0 ) ) ) . '</span>',
				'views'      => '<span class="lp-cell__muted">' . esc_html( number_format_i18n( (int) ( $row['views'] ?? 0 ) ) ) . '</span>',
				'share'      => Template::capture(
					'parts/progress',
					array( 'value' => $share, 'label' => true, 'tone' => 'auto' )
				),
				'last_visit' => '<span class="lp-cell__muted">' . esc_html(
					date_i18n( get_option( 'date_format' ), (int) strtotime( (string) ( $row['last_visit'] ?? 'now' ) ) )
				) . '</span>',
			),
		);
	},
	(array) ( $lp_report['items'] ?? array() )
);

Template::render(
	'parts/card',
	array(
		'flush' => true,
		'title' => __( 'Top Localized Pages', 'localizepilot' ),
		'body'  => Template::capture(
			'parts/table',
			array(
				'label'   => __( 'Top localized pages', 'localizepilot' ),
				'columns' => array(
					array( 'key' => 'page', 'label' => __( 'Page', 'localizepilot' ) ),
					array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
					array( 'key' => 'visitors', 'label' => __( 'Unique Visitors', 'localizepilot' ) ),
					array( 'key' => 'views', 'label' => __( 'Page Views', 'localizepilot' ) ),
					array( 'key' => 'share', 'label' => __( 'Share', 'localizepilot' ) ),
					array( 'key' => 'last_visit', 'label' => __( 'Last Visit', 'localizepilot' ), 'align' => 'right' ),
				),
				'rows'    => $lp_page_rows,
				'empty'   => array(
					'icon'    => 'nav-seo-urls',
					'title'   => __( 'No pages recorded yet', 'localizepilot' ),
					'message' => __( 'Translated pages appear here as visitors reach them.', 'localizepilot' ),
				),
			)
		)
		. ( ! empty( $lp_page_rows )
			? Template::capture(
				'parts/pagination',
				array(
					'total'    => (int) ( $lp_report['total'] ?? 0 ),
					'page'     => (int) ( $lp_report['page'] ?? 1 ),
					'per_page' => (int) ( $lp_report['per_page'] ?? 20 ),
					'base_url' => (string) ( $lp_data['base_url'] ?? '' ),
				)
			)
			: '' ),
	)
);
?>

<?php
Template::render(
	'parts/analytics-insights',
	array(
		'insights'     => (array) ( $lp_data['insights'] ?? array() ),
		'attention'    => (array) ( $lp_data['attention'] ?? array() ),
		'enabled'      => ! empty( $lp_data['enabled'] ),
		'settings_url' => (string) ( $lp_data['settings_url'] ?? Screen_Registry::url( 'settings' ) ),
	)
);
?>

<p class="lp-filtered-note">
	<a href="<?php echo esc_url( Screen_Registry::url( 'settings' ) ); ?>"><?php esc_html_e( 'Analytics settings', 'localizepilot' ); ?></a>
</p>
</div>
