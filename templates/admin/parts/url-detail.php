<?php
/**
 * Drawer body for one URL.
 *
 * Serves both designed drawers. URL Details and Issue Details differ only in
 * the banner above this body and the label on the footer's primary action,
 * both of which parts/drawer owns.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args A row from Url_Repository::detail().
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_signals = array();

foreach ( (array) ( $args['signals'] ?? array() ) as $lp_signal ) {
	$lp_signals[] = array(
		'label' => (string) ( $lp_signal['code'] ?? '' ),
		'value' => (string) ( $lp_signal['url'] ?? '' ),
	);
}
?>
<div class="lp-detail">
	<h3 class="lp-detail__title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></h3>

	<dl class="lp-detail__meta">
		<div>
			<dt><?php esc_html_e( 'Content Type', 'localizepilot' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $args['type'] ?? '' ) ); ?></dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Source Language', 'localizepilot' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $args['source_name'] ?? '' ) ); ?></dd>
		</div>
		<div class="lp-detail__meta-wide">
			<dt><?php esc_html_e( 'Source URL', 'localizepilot' ); ?></dt>
			<dd><code class="lp-detail__code"><?php echo esc_html( (string) ( $args['url'] ?? '' ) ); ?></code></dd>
		</div>
	</dl>

	<section class="lp-detail__section">
		<h4 class="lp-detail__heading"><?php esc_html_e( 'Translated URLs', 'localizepilot' ); ?></h4>
		<p class="lp-detail__lede"><?php esc_html_e( 'Language versions connected to this page.', 'localizepilot' ); ?></p>

		<?php
		Template::render(
			'parts/url-map',
			array(
				'rows'     => (array) ( $args['languages'] ?? array() ),
				'variant'  => 'stacked',
				'external' => true,
			)
		);
		?>
	</section>

	<section class="lp-detail__section">
		<h4 class="lp-detail__heading"><?php esc_html_e( 'Canonical URL', 'localizepilot' ); ?></h4>

		<?php
		/*
		 * The plugin sets the canonical of a translated request to that
		 * language's URL and does not store an override, so this shows what it
		 * emits. Editing one is gated until there is somewhere to save it.
		 */
		Template::render(
			'parts/field',
			array(
				'type'    => 'text',
				'name'    => 'canonical_' . (int) ( $args['id'] ?? 0 ),
				'value'   => (string) ( $args['canonical'] ?? '' ),
				'hint'    => __( 'This URL identifies the preferred version of this page.', 'localizepilot' ),
				'feature' => 'canonical_editing',
			)
		);
		?>
	</section>

	<section class="lp-detail__section">
		<div class="lp-detail__heading-row">
			<h4 class="lp-detail__heading"><?php esc_html_e( 'Language Signals', 'localizepilot' ); ?></h4>
			<?php
			Template::render(
				'parts/badge',
				array(
					'label' => (string) ( $args['signal_label'] ?? '' ),
					'tone'  => (string) ( $args['signal_tone'] ?? 'neutral' ),
				)
			);
			?>
		</div>

		<p class="lp-detail__lede">
			<?php esc_html_e( 'The hreflang alternates published on this page.', 'localizepilot' ); ?>
		</p>

		<?php Template::render(
			'parts/signal-list',
			array(
				'rows'  => $lp_signals,
				'class' => 'lp-signals--caps',
			)
		); ?>
	</section>
</div>
