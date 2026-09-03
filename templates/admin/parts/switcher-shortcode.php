<?php
/**
 * Shortcode placement.
 *
 * Both shortcodes are real: [localizepilot_switcher] is registered, and the
 * renderer accepts the style, labels, alignment and class attributes shown.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $shortcode Basic form.
 *     @type string $advanced  Form with the supported attributes.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_shortcode = (string) ( $args['shortcode'] ?? '' );
$lp_advanced  = (string) ( $args['advanced'] ?? '' );
?>
<section class="lp-card">
	<div class="lp-card__body">
		<span class="lp-card__eyebrow"><?php esc_html_e( 'Shortcode', 'localizepilot' ); ?></span>
		<h2 class="lp-card__title"><?php esc_html_e( 'Place it anywhere', 'localizepilot' ); ?></h2>
		<p class="lp-card__subtitle"><?php esc_html_e( 'Add the language switcher to posts, pages, widgets, templates, or supported page builders.', 'localizepilot' ); ?></p>

		<div class="lp-copy-row">
			<code id="lp-switcher-shortcode"><?php echo esc_html( $lp_shortcode ); ?></code>
			<button type="button" class="lp-btn lp-btn--ghost lp-btn--sm" data-lp-copy="#lp-switcher-shortcode">
				<span data-lp-copy-text><?php esc_html_e( 'Copy', 'localizepilot' ); ?></span>
			</button>
		</div>

		<div class="lp-fieldset">
			<h3 class="lp-fieldset__title"><?php esc_html_e( 'Advanced overrides', 'localizepilot' ); ?></h3>

			<div class="lp-copy-row">
				<code id="lp-switcher-shortcode-advanced"><?php echo esc_html( $lp_advanced ); ?></code>
				<button type="button" class="lp-btn lp-btn--ghost lp-btn--sm" data-lp-copy="#lp-switcher-shortcode-advanced">
					<span data-lp-copy-text><?php esc_html_e( 'Copy', 'localizepilot' ); ?></span>
				</button>
			</div>

			<ul class="lp-attr-list">
				<?php foreach ( array( 'style', 'labels', 'alignment', 'class' ) as $lp_attribute ) : ?>
					<li><code><?php echo esc_html( $lp_attribute ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
