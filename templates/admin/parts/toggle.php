<?php
/**
 * Toggle switch with a label and optional description.
 *
 * A real checkbox underneath, so it submits with the form and is operable from
 * the keyboard without any script.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $name        Field name.
 *     @type string $label       Visible label.
 *     @type string $description Line under the label.
 *     @type bool   $checked
 *     @type string $feature     Preview feature key that makes this inert.
 * }
 */

use LocalizePilot\Admin\Preview;

defined( 'ABSPATH' ) || exit;

$lp_name = (string) ( $args['name'] ?? '' );
$lp_id   = 'lp-toggle-' . sanitize_title( $lp_name );
$lp_gate = ! empty( $args['feature'] ) ? Preview::attributes( (string) $args['feature'] ) : '';
?>
<div class="lp-toggle">
	<span class="lp-toggle__text">
		<label class="lp-toggle__label" for="<?php echo esc_attr( $lp_id ); ?>">
			<?php echo esc_html( (string) ( $args['label'] ?? '' ) ); ?>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<span class="lp-toggle__description"><?php echo esc_html( (string) $args['description'] ); ?></span>
		<?php endif; ?>
	</span>

	<span class="lp-toggle__switch">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $lp_id ); ?>"
			name="<?php echo esc_attr( $lp_name ); ?>"
			value="1"
			<?php checked( ! empty( $args['checked'] ) ); ?>
			<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
		>
		<span class="lp-toggle__track" aria-hidden="true"></span>
	</span>
</div>
