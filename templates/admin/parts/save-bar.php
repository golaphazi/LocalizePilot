<?php
/**
 * Shared settings form footer.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $title
 *     @type string $message
 *     @type string $label
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="lp-save-bar">
	<div>
		<strong><?php echo esc_html( (string) ( $args['title'] ?? __( 'Save your LocalizePilot settings', 'localizepilot' ) ) ); ?></strong>
		<span><?php echo esc_html( (string) ( $args['message'] ?? __( 'Changes take effect after you save.', 'localizepilot' ) ) ); ?></span>
	</div>
	<button type="submit" class="lp-btn lp-btn--primary">
		<?php echo esc_html( (string) ( $args['label'] ?? __( 'Save changes', 'localizepilot' ) ) ); ?>
	</button>
</div>
