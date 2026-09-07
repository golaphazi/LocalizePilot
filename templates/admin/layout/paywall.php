<?php
/**
 * The paywall modal.
 *
 * One instance for the whole console, filled in by the client from the
 * catalogue in the payload — so a screen with a dozen locked controls costs
 * one dialog, not a dozen.
 *
 * Rendered at app level rather than inside the topbar for the same reason the
 * search palette is: the topbar carries backdrop-filter, which makes it the
 * containing block for any position:fixed descendant, and an overlay nested
 * there paints inside the header instead of over the page.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Paywall;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_state = Paywall::state();
?>
<div class="lp-modal" id="lp-paywall" data-lp-paywall-modal hidden>
	<button type="button" class="lp-modal__backdrop" data-lp-paywall-close tabindex="-1">
		<span class="screen-reader-text"><?php esc_html_e( 'Close', 'localizepilot' ); ?></span>
	</button>

	<section
		class="lp-modal__panel"
		role="dialog"
		aria-modal="true"
		aria-labelledby="lp-paywall-title"
		aria-describedby="lp-paywall-promise"
	>
		<header class="lp-modal__head">
			<div class="lp-modal__heading">
				<span class="lp-modal__eyebrow" data-lp-paywall-eyebrow>
					<?php echo esc_html( (string) ( $lp_state['eyebrow'] ?? '' ) ); ?>
				</span>
				<h2 class="lp-modal__title" id="lp-paywall-title" data-lp-paywall-title></h2>
			</div>

			<button type="button" class="lp-icon-button" data-lp-paywall-close>
				<?php Template::the_icon( 'close', 'lp-icon' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close', 'localizepilot' ); ?></span>
			</button>
		</header>

		<div class="lp-modal__body">
			<p class="lp-modal__promise" id="lp-paywall-promise" data-lp-paywall-promise></p>
			<ul class="lp-feature-list" data-lp-paywall-points></ul>

			<?php
			/*
			 * The client clones this rather than building an <li> of its own,
			 * so the check icon stays server-inlined like every other icon in
			 * the console and no SVG has to live in a script.
			 */
			?>
			<template data-lp-paywall-point><li><?php Template::the_icon( 'check-circle', 'lp-icon lp-feature-list__icon' ); ?><span data-lp-point-label></span></li></template>
		</div>

		<footer class="lp-modal__foot">
			<?php
			/*
			 * A real anchor with a real href, written server-side. It works
			 * before the script runs and if the script never runs, and the
			 * client only ever rewrites its text and destination.
			 */
			?>
			<a
				class="lp-btn lp-btn--primary"
				data-lp-paywall-cta
				href="<?php echo esc_url( (string) ( $lp_state['cta_url'] ?? '' ) ); ?>"
				<?php if ( ! empty( $lp_state['external'] ) ) : ?>
					target="_blank" rel="noopener noreferrer"
				<?php endif; ?>
			>
				<span data-lp-paywall-cta-label><?php echo esc_html( (string) ( $lp_state['cta_label'] ?? '' ) ); ?></span>
				<?php if ( ! empty( $lp_state['external'] ) ) : ?>
					<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
				<?php endif; ?>
			</a>

			<span class="lp-modal__note" data-lp-paywall-note>
				<?php echo esc_html( (string) ( $lp_state['note'] ?? '' ) ); ?>
			</span>
		</footer>
	</section>
</div>
