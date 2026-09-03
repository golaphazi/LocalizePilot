<?php
/**
 * Gutenberg block instructions.
 *
 * The plugin registers no block yet, so this card is gated: it describes the
 * designed flow without implying the block is installable today.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Unused.
 */

use LocalizePilot\Admin\Preview;

defined( 'ABSPATH' ) || exit;

$lp_steps = array(
	__( 'Open a post, page, template, header, or navigation area in the block editor.', 'localizepilot' ),
	__( 'Search for "LocalizePilot Language Switcher" in the block inserter.', 'localizepilot' ),
	__( 'Choose the layout, label format, and alignment from the block sidebar.', 'localizepilot' ),
);

$lp_traits = array(
	'⚡' => __( 'Dynamic page URLs', 'localizepilot' ),
	'🌐' => __( 'Uses enabled languages', 'localizepilot' ),
	'✏️' => __( 'Per-block overrides', 'localizepilot' ),
);
?>
<section class="lp-card"<?php echo Preview::attributes( 'switcher_block' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
	<div class="lp-card__body">
		<span class="lp-card__eyebrow"><?php esc_html_e( 'Gutenberg block', 'localizepilot' ); ?></span>
		<h2 class="lp-card__title"><?php esc_html_e( 'LocalizePilot Language Switcher', 'localizepilot' ); ?></h2>
		<p class="lp-card__subtitle"><?php esc_html_e( 'Add the native dynamic block from the WordPress block editor.', 'localizepilot' ); ?></p>

		<ol class="lp-step-list">
			<?php foreach ( $lp_steps as $lp_index => $lp_step ) : ?>
				<li>
					<span class="lp-step-list__num" aria-hidden="true"><?php echo esc_html( (string) ( $lp_index + 1 ) ); ?></span>
					<span><?php echo esc_html( $lp_step ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<ul class="lp-trait-list">
			<?php foreach ( $lp_traits as $lp_glyph => $lp_trait ) : ?>
				<li>
					<span aria-hidden="true"><?php echo esc_html( $lp_glyph ); ?></span>
					<?php echo esc_html( $lp_trait ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
