<?php
/**
 * Performance screen.
 *
 * Follows the Figma design section for section. Every figure it asks for —
 * hit rates, render times, provider response, health scores — is unmeasured,
 * so each renders through parts/metric as an em dash carrying the preview
 * marker. The languages, the providers and the cache summary are real.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data      = (array) ( $args['data'] ?? array() );
$lp_stats     = (array) ( $lp_data['stats'] ?? array() );
$lp_settings  = (array) ( $lp_data['settings'] ?? array() );
$lp_languages = (array) ( $lp_data['languages'] ?? array() );
$lp_providers = (array) ( $lp_data['providers'] ?? array() );
$lp_urls      = (array) ( $lp_data['urls'] ?? array() );
$lp_gate      = Preview::attributes( 'perf_metrics' );

/*
 * The four headline figures. All four are timings or rates the plugin never
 * records, so all four are dashes.
 */
$lp_kpis = array(
	array(
		'label' => __( 'Cache hit rate', 'localizepilot' ),
		'note'  => __( 'Requests served from cache', 'localizepilot' ),
	),
	array(
		'label' => __( 'Avg. render time', 'localizepilot' ),
		'note'  => __( 'Average translated page render time', 'localizepilot' ),
	),
	array(
		'label' => __( 'Provider response', 'localizepilot' ),
		'note'  => __( 'Average translation provider response', 'localizepilot' ),
	),
	array(
		'label' => __( 'Slow pages', 'localizepilot' ),
		'note'  => __( 'Pages above 500 ms', 'localizepilot' ),
	),
);
?>
<div class="lp-kpis lp-kpis--metric">
	<?php foreach ( $lp_kpis as $lp_kpi ) : ?>
		<article class="lp-kpi">
			<span class="lp-kpi__label"><?php echo esc_html( $lp_kpi['label'] ); ?></span>
			<div class="lp-kpi__figure">
				<?php Template::render( 'parts/metric', array() ); ?>
			</div>
			<span class="lp-kpi__sub"><?php echo esc_html( $lp_kpi['note'] ); ?></span>
		</article>
	<?php endforeach; ?>
</div>

<section class="lp-card">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Performance overview', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Track how efficiently translated content is delivered to visitors.', 'localizepilot' ); ?></p>
		</div>

		<div class="lp-segmented lp-segmented--sm" role="group" aria-label="<?php esc_attr_e( 'Overview metric', 'localizepilot' ); ?>" data-lp-performance-tabs>
			<button
				type="button"
				class="lp-segmented__option"
				data-lp-performance-metric="render"
				data-title="<?php esc_attr_e( 'No render-time history yet', 'localizepilot' ); ?>"
				data-message="<?php esc_attr_e( 'LocalizePilot does not record page render timings yet, so there is no trend to plot.', 'localizepilot' ); ?>"
				data-unit="<?php esc_attr_e( 'ms', 'localizepilot' ); ?>"
				aria-pressed="false"
			><?php esc_html_e( 'Render time', 'localizepilot' ); ?></button>
			<button
				type="button"
				class="lp-segmented__option is-active"
				data-lp-performance-metric="cache"
				data-title="<?php esc_attr_e( 'No cache-hit history yet', 'localizepilot' ); ?>"
				data-message="<?php esc_attr_e( 'LocalizePilot does not record cache hits and misses yet, so there is no trend to plot.', 'localizepilot' ); ?>"
				data-unit="%"
				aria-pressed="true"
			><?php esc_html_e( 'Cache hit rate', 'localizepilot' ); ?></button>
		</div>
	</header>

	<div class="lp-card__body" data-lp-performance-overview>
		<?php
		Template::render(
			'parts/empty-state',
			array(
				'icon'    => 'nav-performance',
				'title'   => __( 'No performance history yet', 'localizepilot' ),
				'message' => __( 'LocalizePilot does not record render or response timings, so there is no trend to plot.', 'localizepilot' ),
				'level'   => 3,
			)
		);
		?>

		<dl class="lp-stat-row" data-lp-performance-summary>
			<?php
			$lp_summary = array(
				__( 'Fastest', 'localizepilot' ),
				__( 'Average', 'localizepilot' ),
				__( 'Slowest', 'localizepilot' ),
			);

			foreach ( $lp_summary as $lp_term ) :
				?>
				<div>
					<dt><?php echo esc_html( $lp_term ); ?></dt>
					<dd><?php Template::render( 'parts/metric', array( 'unit' => __( 'ms', 'localizepilot' ) ) ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>
</section>

<section class="lp-card lp-card--flush">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Performance by language', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Compare delivery and rendering performance across your language versions.', 'localizepilot' ); ?></p>
		</div>

		<a class="lp-card__link" href="<?php echo esc_url( (string) ( $lp_urls['languages'] ?? '' ) ); ?>">
			<?php esc_html_e( 'View all languages', 'localizepilot' ); ?>
			<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
		</a>
	</header>

	<?php
	$lp_rows = array();

	foreach ( $lp_languages as $lp_language ) {
		$lp_code = (string) ( $lp_language['code'] ?? '' );

		$lp_rows[] = array(
			'id'    => $lp_code,
			'label' => (string) ( $lp_language['name'] ?? '' ),
			'cells' => array(
				'language' => Template::capture(
					'parts/lang-chip',
					array(
						'code'  => $lp_code,
						'tone'  => ! empty( $lp_language['is_source'] ) ? 'source' : 'target',
						'title' => (string) ( $lp_language['native'] ?? '' ),
					)
				) . '<span class="lp-cell__title">' . esc_html( (string) ( $lp_language['name'] ?? '' ) ) . '</span>',
				'hit_rate' => Template::capture( 'parts/metric', array( 'inline' => true ) ),
				'render'   => Template::capture( 'parts/metric', array( 'inline' => true ) ),
				// Real: how many pieces of content exist in this language.
				'pages'    => '<span class="lp-cell__title">' . esc_html( number_format_i18n( (int) ( $lp_language['translated'] ?? 0 ) ) ) . '</span>',
				'status'   => Template::capture( 'parts/metric', array( 'inline' => true ) ),
			),
		);
	}

	Template::render(
		'parts/table',
		array(
			'label'   => __( 'Performance by language', 'localizepilot' ),
			'columns' => array(
				array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ), 'width' => '28%' ),
				array( 'key' => 'hit_rate', 'label' => __( 'Cache hit rate', 'localizepilot' ) ),
				array( 'key' => 'render', 'label' => __( 'Avg. render', 'localizepilot' ) ),
				array( 'key' => 'pages', 'label' => __( 'Translated pages', 'localizepilot' ) ),
				array( 'key' => 'status', 'label' => __( 'Status', 'localizepilot' ) ),
			),
			'rows'    => $lp_rows,
			'empty'   => array(
				'icon'    => 'nav-languages',
				'title'   => __( 'No languages enabled', 'localizepilot' ),
				'message' => __( 'Enable a language to compare delivery across your site.', 'localizepilot' ),
			),
		)
	);
	?>
</section>

<section class="lp-card">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Pages needing attention', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Localized pages with slower-than-average rendering performance.', 'localizepilot' ); ?></p>
		</div>
	</header>

	<div class="lp-card__body">
		<?php
		/*
		 * This list can only exist once render times are recorded. An empty
		 * state is the truthful rendering of it, not a sample of pages.
		 */
		Template::render(
			'parts/empty-state',
			array(
				'icon'    => 'alert-triangle',
				'title'   => __( 'Nothing flagged', 'localizepilot' ),
				'message' => __( 'Pages are listed here once render times are recorded and one falls behind the average.', 'localizepilot' ),
				'level'   => 3,
			)
		);
		?>
	</div>
</section>

<?php
Template::render(
	'parts/perf-providers',
	array(
		'providers' => $lp_providers,
		'url'       => (string) ( $lp_urls['providers'] ?? '' ),
	)
);
?>

<div class="lp-cols lp-cols--wide-narrow">
	<section class="lp-card">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Performance alerts', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'Potential issues affecting multilingual page delivery.', 'localizepilot' ); ?></p>
			</div>
		</header>

		<div class="lp-card__body">
			<?php
			/*
			 * Every alert in the design is a threshold on a measurement. With
			 * nothing measured, there is nothing that could raise one — except
			 * the cache conditions, which Cache Management already reports.
			 */
			Template::render(
				'parts/empty-state',
				array(
					'icon'    => 'check-circle',
					'title'   => __( 'No alerts', 'localizepilot' ),
					'message' => __( 'Delivery alerts appear here once performance measurement is available.', 'localizepilot' ),
					'action'  => array(
						'label' => __( 'Review cache health', 'localizepilot' ),
						'url'   => (string) ( $lp_urls['cache'] ?? '' ),
					),
					'level'   => 3,
				)
			);
			?>
		</div>
	</section>

	<section class="lp-card">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Multilingual performance health', 'localizepilot' ); ?></h2>
			</div>
		</header>

		<div class="lp-card__body lp-perf-health"<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
			<div class="lp-perf-health__score">
				<span class="lp-perf-health__dial" aria-hidden="true"></span>
				<strong aria-label="<?php esc_attr_e( 'Not measured', 'localizepilot' ); ?>">&mdash;</strong>
				<small><?php esc_html_e( 'Overall', 'localizepilot' ); ?></small>
			</div>

			<dl class="lp-detail-list">
				<?php
				$lp_health = array(
					__( 'Translation delivery', 'localizepilot' ),
					__( 'Page rendering', 'localizepilot' ),
					__( 'Cache efficiency', 'localizepilot' ),
				);

				foreach ( $lp_health as $lp_term ) :
					?>
					<div class="lp-detail-list__row">
						<dt><?php echo esc_html( $lp_term ); ?></dt>
						<dd><?php Template::render( 'parts/metric', array( 'inline' => true ) ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>

			<p class="lp-perf-health__note"><?php esc_html_e( 'A health score needs render and response timings, which are not collected.', 'localizepilot' ); ?></p>
		</div>
	</section>
</div>

<section class="lp-card lp-perf-note">
	<div class="lp-card__body">
		<strong><?php esc_html_e( 'How performance is measured', 'localizepilot' ); ?></strong>
		<p>
			<?php esc_html_e( 'This screen is designed to report translated page rendering time, translation provider response time, cache efficiency, and language-level delivery performance. LocalizePilot does not collect those timings yet, so each figure reads as unavailable.', 'localizepilot' ); ?>
		</p>
		<a class="lp-card__link" href="<?php echo esc_url( (string) ( $lp_urls['cache'] ?? '' ) ); ?>">
			<?php esc_html_e( 'See what is measured, in Cache Management', 'localizepilot' ); ?>
			<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
		</a>
	</div>
</section>
