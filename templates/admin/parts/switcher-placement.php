<?php
/**
 * Switcher placement.
 *
 * Header and Shortcode are real: one is a stored setting, the other is a
 * registered shortcode. Floating and Footer are design-only.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type bool $in_header Whether the header switcher is enabled.
 * }
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_in_header = ! empty( $args['in_header'] );

$lp_options = array(
	array(
		'key'         => 'header',
		'label'       => __( 'Header', 'localizepilot' ),
		'description' => __( 'Show in the main navigation.', 'localizepilot' ),
		'active'      => $lp_in_header,
		'feature'     => '',
	),
	array(
		'key'         => 'floating',
		'label'       => __( 'Floating', 'localizepilot' ),
		'description' => __( 'Visible while scrolling.', 'localizepilot' ),
		'active'      => false,
		'feature'     => 'switcher_placement',
	),
	array(
		'key'         => 'footer',
		'label'       => __( 'Footer', 'localizepilot' ),
		'description' => __( 'Place in website footer.', 'localizepilot' ),
		'active'      => false,
		'feature'     => 'switcher_placement',
	),
	array(
		'key'         => 'shortcode',
		'label'       => __( 'Shortcode', 'localizepilot' ),
		'description' => __( 'Add manually anywhere.', 'localizepilot' ),
		'active'      => ! $lp_in_header,
		'feature'     => '',
	),
);
?>
<section class="lp-card">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Switcher placement', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Choose where visitors can access the language selector.', 'localizepilot' ); ?></p>
		</div>
	</header>

	<div class="lp-card__body">
		<div class="lp-placement-grid" role="group" aria-label="<?php esc_attr_e( 'Switcher placement', 'localizepilot' ); ?>">
			<?php
			foreach ( $lp_options as $lp_option ) :
				$lp_gate = '' !== $lp_option['feature'] ? Preview::attributes( (string) $lp_option['feature'] ) : '';
				?>
				<button
					type="button"
					class="lp-placement<?php echo $lp_option['active'] ? ' is-active' : ''; ?>"
					data-lp-switcher-placement="<?php echo esc_attr( (string) $lp_option['key'] ); ?>"
					aria-pressed="<?php echo $lp_option['active'] ? 'true' : 'false'; ?>"
					<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
				>
					<span class="lp-placement__icon" aria-hidden="true">
						<?php Template::the_icon( 'nav-language-switcher', 'lp-icon' ); ?>
					</span>

					<strong class="lp-placement__label"><?php echo esc_html( (string) $lp_option['label'] ); ?></strong>
					<span class="lp-placement__note"><?php echo esc_html( (string) $lp_option['description'] ); ?></span>

					<span class="lp-placement__check" aria-hidden="true">
						<?php Template::the_icon( 'check-circle', 'lp-icon' ); ?>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
</section>
