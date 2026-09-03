<?php
/**
 * Form field: label, control, and an optional hint.
 *
 * Covers the text, number, select, textarea and readonly-code controls the
 * settings and drawer designs use.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $type    text | number | password | select | textarea | code.
 *     @type string $name    Field name.
 *     @type string $label   Visible label.
 *     @type string $value   Current value.
 *     @type string $hint    Line under the control.
 *     @type string $suffix  Unit printed beside the control, e.g. "hours".
 *     @type array  $options Select options, value => label.
 *     @type bool   $readonly
 *     @type string $feature Preview feature key that makes this inert.
 * }
 */

use LocalizePilot\Admin\Preview;

defined( 'ABSPATH' ) || exit;

$lp_type  = (string) ( $args['type'] ?? 'text' );
$lp_name  = (string) ( $args['name'] ?? '' );
$lp_id    = (string) ( $args['id'] ?? ( 'lp-field-' . sanitize_title( $lp_name ) ) );
$lp_value = (string) ( $args['value'] ?? '' );
$lp_gate  = ! empty( $args['feature'] ) ? Preview::attributes( (string) $args['feature'] ) : '';
$lp_ro    = ! empty( $args['readonly'] ) || ( ! empty( $args['feature'] ) && Preview::is_preview( (string) $args['feature'] ) );
?>
<p class="lp-field lp-field--<?php echo esc_attr( $lp_type ); ?>">
	<?php if ( ! empty( $args['label'] ) ) : ?>
		<label class="lp-field__label" for="<?php echo esc_attr( $lp_id ); ?>">
			<?php echo esc_html( (string) $args['label'] ); ?>
		</label>
	<?php endif; ?>

	<?php if ( 'select' === $lp_type ) : ?>
		<select class="lp-field__control" id="<?php echo esc_attr( $lp_id ); ?>" name="<?php echo esc_attr( $lp_name ); ?>" <?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
			<?php foreach ( (array) ( $args['options'] ?? array() ) as $lp_key => $lp_text ) : ?>
				<option value="<?php echo esc_attr( (string) $lp_key ); ?>" <?php selected( (string) $lp_key, $lp_value ); ?>>
					<?php echo esc_html( (string) $lp_text ); ?>
				</option>
			<?php endforeach; ?>
		</select>

	<?php elseif ( 'textarea' === $lp_type ) : ?>
		<textarea
			class="lp-field__control"
			id="<?php echo esc_attr( $lp_id ); ?>"
			name="<?php echo esc_attr( $lp_name ); ?>"
			rows="<?php echo esc_attr( (string) ( $args['rows'] ?? 4 ) ); ?>"
			<?php echo ! empty( $args['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $args['placeholder'] ) . '"' : ''; ?>
			<?php echo $lp_ro ? ' readonly' : ''; ?>
			<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
		><?php echo esc_textarea( $lp_value ); ?></textarea>

	<?php elseif ( 'code' === $lp_type ) : ?>
		<code class="lp-field__code"><?php echo esc_html( $lp_value ); ?></code>

	<?php else : ?>
		<?php
		/*
		 * A unit printed beside the control rather than inside the label, which
		 * is how the design shows "24 hours" on the cache lifetime.
		 */
		$lp_suffix = (string) ( $args['suffix'] ?? '' );

		if ( '' !== $lp_suffix ) :
			?>
			<span class="lp-field__row">
		<?php endif; ?>

		<input
			class="lp-field__control"
			type="<?php echo esc_attr( $lp_type ); ?>"
			id="<?php echo esc_attr( $lp_id ); ?>"
			name="<?php echo esc_attr( $lp_name ); ?>"
			value="<?php echo esc_attr( $lp_value ); ?>"
			<?php echo ! empty( $args['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $args['placeholder'] ) . '"' : ''; ?>
			<?php echo isset( $args['min'] ) ? ' min="' . esc_attr( (string) $args['min'] ) . '"' : ''; ?>
			<?php echo isset( $args['max'] ) ? ' max="' . esc_attr( (string) $args['max'] ) . '"' : ''; ?>
			<?php echo isset( $args['step'] ) ? ' step="' . esc_attr( (string) $args['step'] ) . '"' : ''; ?>
			<?php echo ! empty( $args['autocomplete'] ) ? ' autocomplete="' . esc_attr( (string) $args['autocomplete'] ) . '"' : ''; ?>
			<?php echo $lp_ro ? ' readonly' : ''; ?>
			<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
		>

		<?php if ( '' !== $lp_suffix ) : ?>
				<span class="lp-field__suffix"><?php echo esc_html( $lp_suffix ); ?></span>
			</span>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $args['hint'] ) ) : ?>
		<span class="lp-field__hint"><?php echo esc_html( (string) $args['hint'] ); ?></span>
	<?php endif; ?>
</p>
