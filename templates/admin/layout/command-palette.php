<?php
/**
 * Global search palette.
 *
 * Rendered at app level rather than inside the topbar on purpose: the topbar
 * carries backdrop-filter, which makes it the containing block for any
 * position:fixed descendant. Nested there, this overlay's inset:0 resolved to
 * the header's own box, so its backdrop painted as a dark band across the top
 * of the screen instead of covering the page.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;
?>
<div class="lp-command" id="lp-command-search" data-lp-command hidden>
	<button type="button" class="lp-command__backdrop" data-lp-command-close tabindex="-1">
		<span class="screen-reader-text"><?php esc_html_e( 'Close search', 'localizepilot' ); ?></span>
	</button>
	<section class="lp-command__panel" role="dialog" aria-modal="true" aria-labelledby="lp-command-title">
		<header class="lp-command__head">
			<?php Template::the_icon( 'search', 'lp-icon' ); ?>
			<h2 class="screen-reader-text" id="lp-command-title"><?php esc_html_e( 'Search LocalizePilot', 'localizepilot' ); ?></h2>
			<label class="screen-reader-text" for="lp-command-input"><?php esc_html_e( 'Search', 'localizepilot' ); ?></label>
			<input
				type="search"
				id="lp-command-input"
				data-lp-command-input
				autocomplete="off"
				placeholder="<?php esc_attr_e( 'Search translations, content, languages, settings…', 'localizepilot' ); ?>"
			>
			<button type="button" class="lp-icon-button" data-lp-command-close>
				<?php Template::the_icon( 'close', 'lp-icon' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close search', 'localizepilot' ); ?></span>
			</button>
		</header>
		<div class="lp-command__body" data-lp-command-results aria-live="polite"></div>
	</section>
</div>
