<?php
/**
 * A figure the plugin does not measure.
 *
 * The Performance design is full of durations, rates and scores that nothing
 * in the plugin records. Rather than print a plausible number, every one of
 * them renders through here: an em dash, the reason, and the preview marker.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $feature Preview feature key. Defaults to perf_metrics.
 *     @type string $unit    Optional unit shown after the dash, e.g. "ms".
 *     @type bool   $inline  Render as a bare span, for use inside a table cell.
 * }
 */

use LocalizePilot\Admin\Preview;

defined( 'ABSPATH' ) || exit;

$lp_feature = (string) ( $args['feature'] ?? 'perf_metrics' );
$lp_gate    = Preview::attributes( $lp_feature );
$lp_label   = esc_attr__( 'Not measured', 'localizepilot' );

if ( ! empty( $args['inline'] ) ) :
	?>
	<span class="lp-metric lp-metric--inline" aria-label="<?php echo esc_attr( $lp_label ); ?>"<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>&mdash;</span>
	<?php
	return;
endif;
?>
<span class="lp-metric"<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
	<strong class="lp-metric__value" aria-label="<?php echo esc_attr( $lp_label ); ?>">
		&mdash;<?php echo ! empty( $args['unit'] ) ? ' ' . esc_html( (string) $args['unit'] ) : ''; ?>
	</strong>
</span>
