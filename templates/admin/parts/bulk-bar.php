<?php
/**
 * Bulk action bar, revealed once a row is selected.
 *
 * Rendered on every table and shown by ui.js when the selection is non-empty,
 * so the markup stays server-owned and the script only toggles a class.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $actions Each {action, label}.
 *     @type string $feature Preview feature key gating the whole bar.
 * }
 */

use LocalizePilot\Admin\Preview;

defined( 'ABSPATH' ) || exit;

$lp_actions = (array) ( $args['actions'] ?? array() );

if ( empty( $lp_actions ) ) {
	return;
}

$lp_feature = (string) ( $args['feature'] ?? '' );
$lp_gate    = '' !== $lp_feature ? Preview::attributes( $lp_feature ) : '';
?>
<div class="lp-bulk-bar" data-lp-bulk-bar hidden>
	<?php
	/* translators: %s is the number of selected rows. */
	$lp_count_template = __( '%s selected', 'localizepilot' );
	?>
	<span
		class="lp-bulk-bar__count"
		data-lp-bulk-count
		data-lp-count-template="<?php echo esc_attr( $lp_count_template ); ?>"
	><?php echo esc_html( sprintf( $lp_count_template, '0' ) ); ?></span>

	<div class="lp-bulk-bar__actions">
		<?php foreach ( $lp_actions as $lp_action ) : ?>
			<button
				type="button"
				class="lp-bulk-bar__button"
				data-lp-bulk-action="<?php echo esc_attr( (string) ( $lp_action['action'] ?? '' ) ); ?>"
				<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
			>
				<?php echo esc_html( (string) ( $lp_action['label'] ?? '' ) ); ?>
			</button>
		<?php endforeach; ?>
	</div>

	<button type="button" class="lp-bulk-bar__clear" data-lp-bulk-clear>
		<?php esc_html_e( 'Clear', 'localizepilot' ); ?>
	</button>
</div>
