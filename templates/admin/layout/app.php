<?php
/**
 * Console shell.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data supplied by Abstract_Screen::render().
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_slug = (string) ( $args['slug'] ?? '' );
?>
<div class="lp-app" data-lp-screen="<?php echo esc_attr( $lp_slug ); ?>">
	<a class="lp-skip-link" href="#lp-main"><?php esc_html_e( 'Skip to console content', 'localizepilot' ); ?></a>

	<?php Template::render( 'layout/sidebar', $args ); ?>

	<button type="button" class="lp-sidebar__scrim" data-lp-nav-close tabindex="-1">
		<span class="screen-reader-text"><?php esc_html_e( 'Close navigation', 'localizepilot' ); ?></span>
	</button>

	<div class="lp-shell">
		<?php Template::render( 'layout/topbar', $args ); ?>

		<main class="lp-main" id="lp-main" tabindex="-1">
			<div class="lp-container">
				<?php
				/*
				 * The announcer, not the view, is the live region. A live
				 * region wrapped around the whole screen makes a screen reader
				 * read every word of it again on each client-side navigation;
				 * this says only which screen arrived. aria-busy stays on the
				 * view so assistive tech knows it is mid-swap.
				 */
				?>
				<p class="screen-reader-text" id="lp-announcer" role="status" data-lp-announcer></p>

				<?php
				/*
				 * The console discards WordPress admin notices, which is what
				 * keeps other plugins from printing into it. That leaves
				 * LocalizePilot and its add-ons with nowhere to speak, so this
				 * is the one channel that survives — and it sits outside
				 * #lp-view deliberately, so a message about the whole console
				 * is not swept away by a client-side navigation.
				 *
				 * Render a .lp-banner here; the wrapper only appears when
				 * something actually did.
				 */
				ob_start();

				/**
				 * Fires where console-wide messages are printed.
				 *
				 * Anything echoed here must be escaped by whoever echoes it.
				 */
				do_action( 'localizepilot_console_notices' );

				$lp_notices = trim( (string) ob_get_clean() );

				if ( '' !== $lp_notices ) {
					echo '<div class="lp-notices">' . $lp_notices . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup from the action above, escaped at its source.
				}
				?>

				<div class="lp-view" id="lp-view" data-lp-view aria-busy="false">
					<?php Template::render( 'parts/view', $args ); ?>
				</div>
			</div>
		</main>
	</div>

	<?php
	/*
	 * Overlays live here, as siblings of the shell, not inside the topbar:
	 * the topbar's backdrop-filter makes it the containing block for any
	 * position:fixed descendant, which would confine this to the header.
	 */
	Template::render( 'layout/command-palette', $args );
	Template::render( 'layout/paywall', $args );
	?>
</div>
