<?php
/**
 * Language chip — the uppercase two-letter code used throughout the tables.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $code  Language code.
 *     @type string $tone  source | target.
 *     @type string $title Optional tooltip, usually the language name.
 * }
 */

defined( 'ABSPATH' ) || exit;

$lp_code = strtoupper( sanitize_key( (string) ( $args['code'] ?? '' ) ) );

if ( '' === $lp_code ) {
	return;
}

$lp_tone  = 'target' === ( $args['tone'] ?? 'source' ) ? 'target' : 'source';
$lp_title = (string) ( $args['title'] ?? '' );
?>
<span
	class="lp-lang-chip lp-lang-chip--<?php echo esc_attr( $lp_tone ); ?>"
	<?php echo '' !== $lp_title ? ' title="' . esc_attr( $lp_title ) . '"' : ''; ?>
><?php echo esc_html( $lp_code ); ?></span>
