<?php
/**
 * Donut gauge — the Overview cache hit-rate ring.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type int    $value   Percentage, 0-100.
 *     @type string $caption Line under the figure.
 *     @type string $tone    brand | success | warning.
 * }
 */

defined( 'ABSPATH' ) || exit;

$lp_value   = max( 0, min( 100, (int) ( $args['value'] ?? 0 ) ) );
$lp_caption = (string) ( $args['caption'] ?? '' );
$lp_tone    = (string) ( $args['tone'] ?? 'brand' );

$lp_radius        = 52;
$lp_circumference = 2 * M_PI * $lp_radius;
$lp_filled        = $lp_circumference * $lp_value / 100;
?>
<div class="lp-donut">
	<svg class="lp-donut__ring" viewBox="0 0 128 128" role="img"
		aria-label="<?php echo esc_attr( sprintf( '%d%%', $lp_value ) ); ?>">
		<circle class="lp-donut__track" cx="64" cy="64" r="<?php echo esc_attr( (string) $lp_radius ); ?>" />
		<circle
			class="lp-donut__value lp-donut__value--<?php echo esc_attr( $lp_tone ); ?>"
			cx="64"
			cy="64"
			r="<?php echo esc_attr( (string) $lp_radius ); ?>"
			stroke-dasharray="<?php echo esc_attr( round( $lp_filled, 2 ) . ' ' . round( $lp_circumference, 2 ) ); ?>"
			transform="rotate(-90 64 64)"
		/>
	</svg>

	<div class="lp-donut__label">
		<strong><?php echo esc_html( $lp_value . '%' ); ?></strong>
		<?php if ( '' !== $lp_caption ) : ?>
			<span><?php echo esc_html( $lp_caption ); ?></span>
		<?php endif; ?>
	</div>
</div>
