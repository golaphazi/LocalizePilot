<?php
/**
 * Switcher placement.
 *
 * Header, Floating and Footer are where the automatic switcher goes; Shortcode
 * means no automatic switcher, only the ones placed by hand. Floating and
 * Footer come with the paid add-on and open the paywall until it provides
 * them.
 *
 * The choice travels in one hidden field. A placement that is stored but not
 * available right now — the add-on switched off — stays stored; the switcher
 * falls back to the header meanwhile, and the Header card shows as active
 * because that is where visitors actually find it.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type bool   $in_header Whether the automatic switcher is enabled.
 *     @type string $stored    Stored placement.
 *     @type string $effective Placement in use right now.
 * }
 */

use LocalizePilot\Admin\Paywall;
use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_in_header = ! empty( $args['in_header'] );
$lp_stored    = (string) ( $args['stored'] ?? 'header' );
$lp_effective = (string) ( $args['effective'] ?? 'header' );

$lp_options = array(
	array(
		'key'         => 'header',
		'label'       => __( 'Header', 'localizepilot' ),
		'description' => __( 'Show in the main navigation.', 'localizepilot' ),
		'paywall'     => '',
	),
	array(
		'key'         => 'floating',
		'label'       => __( 'Floating', 'localizepilot' ),
		'description' => __( 'Visible while scrolling.', 'localizepilot' ),
		'paywall'     => 'switcher_placements',
	),
	array(
		'key'         => 'footer',
		'label'       => __( 'Footer', 'localizepilot' ),
		'description' => __( 'Place in website footer.', 'localizepilot' ),
		'paywall'     => 'switcher_placements',
	),
	array(
		'key'         => 'shortcode',
		'label'       => __( 'Shortcode', 'localizepilot' ),
		'description' => __( 'Add manually anywhere.', 'localizepilot' ),
		'paywall'     => '',
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
		<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION ); ?>[switcher_placement]" value="<?php echo esc_attr( $lp_stored ); ?>" data-lp-switcher-placement-input>

		<div class="lp-placement-grid" role="group" aria-label="<?php esc_attr_e( 'Switcher placement', 'localizepilot' ); ?>">
			<?php
			foreach ( $lp_options as $lp_option ) :
				$lp_key    = (string) $lp_option['key'];
				$lp_active = 'shortcode' === $lp_key ? ! $lp_in_header : $lp_in_header && $lp_key === $lp_effective;

				// A button can stay enabled, so the marker goes on it directly.
				$lp_gate = '' !== $lp_option['paywall'] ? Paywall::attributes( (string) $lp_option['paywall'], false ) : '';
				?>
				<button
					type="button"
					class="lp-placement<?php echo $lp_active ? ' is-active' : ''; ?><?php echo '' !== $lp_gate ? ' is-locked' : ''; ?>"
					data-lp-switcher-placement="<?php echo esc_attr( $lp_key ); ?>"
					aria-pressed="<?php echo $lp_active ? 'true' : 'false'; ?>"
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

		<?php if ( $lp_stored !== $lp_effective ) : ?>
			<p class="lp-field__hint lp-placement-kept">
				<?php esc_html_e( 'Your chosen placement is saved and comes back as soon as the paid add-on is active again. Until then the switcher is in the header.', 'localizepilot' ); ?>
			</p>
		<?php endif; ?>
	</div>
</section>
