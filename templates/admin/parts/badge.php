<?php
/**
 * Status badge — dot plus label on a tinted pill.
 *
 * The tone names match Translation_Manager::statuses(), so a status value maps
 * straight onto a badge with no lookup table in the calling template. The
 * semantic names are the same four hues under the names the SEO, media and
 * hreflang states use, so those screens do not have to borrow a translation
 * status name for something that is not a translation status.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $label Text.
 *     @type string $tone  reviewed | edited | needs_update | automatic | neutral,
 *                         or success | violet | warning | info | danger.
 * }
 */

defined( 'ABSPATH' ) || exit;

$lp_tones = array( 'reviewed', 'edited', 'needs_update', 'automatic', 'neutral', 'success', 'violet', 'warning', 'info', 'danger' );
$lp_tone  = (string) ( $args['tone'] ?? 'neutral' );
$lp_tone  = in_array( $lp_tone, $lp_tones, true ) ? $lp_tone : 'neutral';
$lp_label = (string) ( $args['label'] ?? '' );

if ( '' === $lp_label ) {
	return;
}
?>
<span class="lp-badge lp-badge--<?php echo esc_attr( str_replace( '_', '-', $lp_tone ) ); ?>">
	<i aria-hidden="true"></i><?php echo esc_html( $lp_label ); ?>
</span>
