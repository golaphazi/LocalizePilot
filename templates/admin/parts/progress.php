<?php
/**
 * Progress bar with an optional value label beside it.
 *
 * Complete bars read green; anything in progress reads brand cyan, matching
 * how the design distinguishes "done" from "under way".
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type int    $value Percentage, 0-100.
 *     @type bool   $label Show the numeric label.
 *     @type string $tone  auto | brand | success | warning.
 *     @type string $width Track width, defaults to the table's 80px.
 * }
 */

defined( 'ABSPATH' ) || exit;

$lp_value = max( 0, min( 100, (int) ( $args['value'] ?? 0 ) ) );
$lp_tone  = (string) ( $args['tone'] ?? 'auto' );

if ( 'auto' === $lp_tone ) {
	$lp_tone = 100 === $lp_value ? 'success' : 'brand';
}

$lp_width = (string) ( $args['width'] ?? '' );
?>
<div class="lp-progress">
	<div
		class="lp-progress__track"
		<?php echo '' !== $lp_width ? ' style="width:' . esc_attr( $lp_width ) . '"' : ''; ?>
		role="progressbar"
		aria-valuenow="<?php echo esc_attr( (string) $lp_value ); ?>"
		aria-valuemin="0"
		aria-valuemax="100"
	>
		<span
			class="lp-progress__fill lp-progress__fill--<?php echo esc_attr( $lp_tone ); ?>"
			style="width:<?php echo esc_attr( (string) $lp_value ); ?>%"
		></span>
	</div>
	<?php if ( ! empty( $args['label'] ) ) : ?>
		<span class="lp-progress__value"><?php echo esc_html( $lp_value . '%' ); ?></span>
	<?php endif; ?>
</div>
