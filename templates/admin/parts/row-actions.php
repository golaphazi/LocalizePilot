<?php
/**
 * The action cluster at the right of a table row: one visible action plus an
 * overflow menu.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $primary Visible action {label, url, style: link|solid, drawer, external, feature}.
 *     @type array  $menu    Overflow items, each {label, url, destructive, external, drawer, feature}.
 *     @type string $label   Row name, for the menu's accessible name.
 *     @type string $feature Preview feature key gating both.
 * }
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_primary = (array) ( $args['primary'] ?? array() );
$lp_menu    = (array) ( $args['menu'] ?? array() );
$lp_gate    = ! empty( $args['feature'] ) ? Preview::attributes( (string) $args['feature'] ) : '';
$lp_primary_gate = ! empty( $lp_primary['feature'] )
	? Preview::attributes( (string) $lp_primary['feature'] )
	: $lp_gate;
$lp_solid   = 'solid' === ( $lp_primary['style'] ?? 'link' );
?>
<div class="lp-row-actions">
	<?php if ( ! empty( $lp_primary['label'] ) ) : ?>
		<?php
		/*
		 * A drawer trigger stays an ordinary link to the same view. The script
		 * fetches the panel and opens it in place; without it the browser
		 * follows the href and the server renders the drawer already open.
		 */
		$lp_drawer = (int) ( $lp_primary['drawer'] ?? 0 );
		?>
		<a
			class="lp-row-action<?php echo $lp_solid ? ' lp-row-action--solid' : ''; ?>"
			href="<?php echo esc_url( (string) ( $lp_primary['url'] ?? '#' ) ); ?>"
			<?php echo ! empty( $lp_primary['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
			<?php if ( $lp_drawer > 0 ) : ?>
				data-lp-drawer-remote="<?php echo esc_attr( (string) $lp_drawer ); ?>"
				aria-haspopup="dialog"
			<?php endif; ?>
			<?php echo $lp_primary_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
		>
			<?php echo esc_html( (string) $lp_primary['label'] ); ?>
		</a>
	<?php endif; ?>

	<?php if ( ! empty( $lp_menu ) ) : ?>
		<?php
		/*
		 * A real popup menu: the trigger says so, the list is a menu, and its
		 * items are menu items. ui.js reads those roles to drive arrow-key
		 * navigation, so the semantics and the behaviour cannot drift apart.
		 */
		/*
		 * Derived from the row rather than a counter, so the same row renders
		 * the same id every time. The navigation endpoint returns this markup
		 * and it has to match what a full page load produces.
		 */
		$lp_menu_id = 'lp-menu-' . substr( md5( (string) wp_json_encode( $args ) ), 0, 10 );
		?>
		<div class="lp-menu" data-lp-menu>
			<button
				type="button"
				class="lp-menu__trigger"
				data-lp-menu-trigger
				aria-expanded="false"
				aria-haspopup="menu"
				aria-controls="<?php echo esc_attr( $lp_menu_id ); ?>"
			>
				<?php Template::the_icon( 'more-vertical', 'lp-icon' ); ?>
				<span class="screen-reader-text">
					<?php
					/* translators: %s is the name of the row. */
					echo esc_html( sprintf( __( 'More actions for %s', 'localizepilot' ), (string) ( $args['label'] ?? '' ) ) );
					?>
				</span>
			</button>

			<div class="lp-menu__list" id="<?php echo esc_attr( $lp_menu_id ); ?>" role="menu" data-lp-menu-list hidden>
				<?php foreach ( $lp_menu as $lp_item ) : ?>
					<?php
					$lp_item_drawer = (int) ( $lp_item['drawer'] ?? 0 );
					$lp_item_gate   = ! empty( $lp_item['feature'] )
						? Preview::attributes( (string) $lp_item['feature'] )
						: $lp_gate;
					?>
					<a
						class="lp-menu__item<?php echo ! empty( $lp_item['destructive'] ) ? ' is-destructive' : ''; ?>"
						href="<?php echo esc_url( (string) ( $lp_item['url'] ?? '#' ) ); ?>"
						role="menuitem"
						tabindex="-1"
						<?php echo ! empty( $lp_item['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
						<?php if ( $lp_item_drawer > 0 ) : ?>
							data-lp-drawer-remote="<?php echo esc_attr( (string) $lp_item_drawer ); ?>"
							aria-haspopup="dialog"
						<?php endif; ?>
						<?php echo $lp_item_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
					>
						<?php echo esc_html( (string) ( $lp_item['label'] ?? '' ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
