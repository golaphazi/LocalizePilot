<?php
/**
 * Dual-series line chart, rendered as inline SVG on the server.
 *
 * Server-rendered so the chart is visible without JavaScript, prints, and adds
 * no dependency. ui.js attaches only the hover tooltip.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $series Each {key, label, tone: brand|ink, points: int[], area: bool}.
 *     @type array  $labels X-axis labels, one per point.
 *     @type string $id     Unique id, needed for the gradient definition.
 * }
 */

defined( 'ABSPATH' ) || exit;

$lp_series = array_values( (array) ( $args['series'] ?? array() ) );
$lp_labels = array_values( (array) ( $args['labels'] ?? array() ) );

if ( empty( $lp_series ) ) {
	return;
}

$lp_id = sanitize_title( (string) ( $args['id'] ?? 'lp-chart' ) );

// Geometry. The viewBox is fixed and the SVG scales to its container, so the
// same chart works at every screen width.
$lp_w      = 720;
$lp_h      = 240;
$lp_pad_l  = 44;
$lp_pad_r  = 12;
$lp_pad_t  = 14;
$lp_pad_b  = 28;
$lp_plot_w = $lp_w - $lp_pad_l - $lp_pad_r;
$lp_plot_h = $lp_h - $lp_pad_t - $lp_pad_b;

$lp_all = array();
foreach ( $lp_series as $lp_one ) {
	$lp_all = array_merge( $lp_all, array_map( 'floatval', (array) ( $lp_one['points'] ?? array() ) ) );
}

$lp_count = max( 1, count( (array) ( $lp_series[0]['points'] ?? array() ) ) );
$lp_max   = max( 1.0, empty( $lp_all ) ? 1.0 : max( $lp_all ) );

// Round the ceiling up to something a human would pick for an axis.
$lp_step  = 10 ** max( 0, (int) floor( log10( $lp_max ) ) - 1 );
$lp_max   = ceil( $lp_max / $lp_step ) * $lp_step;
$lp_ticks = 4;

$lp_x = static function ( int $index ) use ( $lp_pad_l, $lp_plot_w, $lp_count ): float {
	return $lp_count < 2
		? $lp_pad_l + $lp_plot_w / 2
		: $lp_pad_l + ( $lp_plot_w * $index / ( $lp_count - 1 ) );
};

$lp_y = static function ( float $value ) use ( $lp_pad_t, $lp_plot_h, $lp_max ): float {
	return $lp_pad_t + $lp_plot_h - ( $lp_plot_h * min( $value, $lp_max ) / $lp_max );
};
?>
<div class="lp-chart-scroll">
	<svg
		class="lp-chart"
		viewBox="0 0 <?php echo esc_attr( (string) $lp_w ); ?> <?php echo esc_attr( (string) $lp_h ); ?>"
		role="img"
		aria-label="<?php echo esc_attr( (string) ( $args['label'] ?? __( 'Activity over time', 'localizepilot' ) ) ); ?>"
	>
		<defs>
			<linearGradient id="<?php echo esc_attr( $lp_id ); ?>-fill" x1="0" y1="0" x2="0" y2="1">
				<stop offset="0%" stop-color="#19C7E8" stop-opacity="0.18" />
				<stop offset="100%" stop-color="#19C7E8" stop-opacity="0" />
			</linearGradient>
		</defs>

		<?php for ( $lp_i = 0; $lp_i <= $lp_ticks; $lp_i++ ) : ?>
			<?php
			$lp_value = $lp_max - ( $lp_max * $lp_i / $lp_ticks );
			$lp_line  = $lp_y( $lp_value );
			?>
			<line
				class="lp-chart__grid"
				x1="<?php echo esc_attr( (string) $lp_pad_l ); ?>"
				y1="<?php echo esc_attr( (string) round( $lp_line, 2 ) ); ?>"
				x2="<?php echo esc_attr( (string) ( $lp_w - $lp_pad_r ) ); ?>"
				y2="<?php echo esc_attr( (string) round( $lp_line, 2 ) ); ?>"
			/>
			<text
				class="lp-chart__axis"
				x="<?php echo esc_attr( (string) ( $lp_pad_l - 8 ) ); ?>"
				y="<?php echo esc_attr( (string) round( $lp_line + 4, 2 ) ); ?>"
				text-anchor="end"
			><?php echo esc_html( number_format_i18n( (int) round( $lp_value ) ) ); ?></text>
		<?php endfor; ?>

		<?php
		foreach ( $lp_series as $lp_index => $lp_one ) :
			$lp_points = array_map( 'floatval', (array) ( $lp_one['points'] ?? array() ) );
			$lp_tone   = 'ink' === ( $lp_one['tone'] ?? 'brand' ) ? 'ink' : 'brand';

			if ( empty( $lp_points ) ) {
				continue;
			}

			$lp_coords = array();
			foreach ( $lp_points as $lp_i => $lp_value ) {
				$lp_coords[] = round( $lp_x( (int) $lp_i ), 2 ) . ',' . round( $lp_y( $lp_value ), 2 );
			}

			if ( ! empty( $lp_one['area'] ) ) :
				$lp_base = round( $lp_pad_t + $lp_plot_h, 2 );
				$lp_area = 'M' . $lp_x( 0 ) . ',' . $lp_base . ' L' . implode( ' L', $lp_coords )
					. ' L' . round( $lp_x( count( $lp_points ) - 1 ), 2 ) . ',' . $lp_base . ' Z';
				?>
				<path class="lp-chart__area" d="<?php echo esc_attr( $lp_area ); ?>" fill="url(#<?php echo esc_attr( $lp_id ); ?>-fill)" />
				<?php
			endif;
			?>
			<polyline
				class="lp-chart__line lp-chart__line--<?php echo esc_attr( $lp_tone ); ?>"
				points="<?php echo esc_attr( implode( ' ', $lp_coords ) ); ?>"
			/>
			<?php
			// Only the final point is marked, which is the value people look for.
			$lp_last = end( $lp_points );
			?>
			<circle
				class="lp-chart__point lp-chart__point--<?php echo esc_attr( $lp_tone ); ?>"
				cx="<?php echo esc_attr( (string) round( $lp_x( count( $lp_points ) - 1 ), 2 ) ); ?>"
				cy="<?php echo esc_attr( (string) round( $lp_y( (float) $lp_last ), 2 ) ); ?>"
				r="4"
			/>
		<?php endforeach; ?>

		<?php
		foreach ( $lp_labels as $lp_i => $lp_label ) :
			// Thin the labels out so they never collide on a narrow chart.
			$lp_every = max( 1, (int) ceil( $lp_count / 7 ) );

			if ( 0 !== $lp_i % $lp_every && $lp_i !== $lp_count - 1 ) {
				continue;
			}
			?>
			<text
				class="lp-chart__axis"
				x="<?php echo esc_attr( (string) round( $lp_x( (int) $lp_i ), 2 ) ); ?>"
				y="<?php echo esc_attr( (string) ( $lp_h - 8 ) ); ?>"
				text-anchor="middle"
			><?php echo esc_html( (string) $lp_label ); ?></text>
		<?php endforeach; ?>
	</svg>
</div>

<?php if ( count( $lp_series ) > 0 ) : ?>
	<ul class="lp-chart-legend">
		<?php foreach ( $lp_series as $lp_one ) : ?>
			<li class="lp-chart-legend__item lp-chart-legend__item--<?php echo esc_attr( 'ink' === ( $lp_one['tone'] ?? 'brand' ) ? 'ink' : 'brand' ); ?>">
				<i aria-hidden="true"></i><?php echo esc_html( (string) ( $lp_one['label'] ?? '' ) ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
