<?php
/**
 * A full-screen stand-in for a feature that lives in the paid add-on.
 *
 * The modal is for one locked control on a screen that otherwise works. This
 * is for a screen that is entirely the add-on's — it says what the screen
 * would do and where to get it, rather than rendering a shell of controls
 * that cannot be operated.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $title   Heading.
 *     @type string $promise One sentence on what the feature does.
 *     @type array  $points  Bullet list of specifics.
 *     @type string $footnote Optional line under the button.
 * }
 */

use LocalizePilot\Admin\Paywall;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_state  = Paywall::state();
$lp_points = (array) ( $args['points'] ?? array() );
?>
<section class="lp-card lp-upsell">
	<div class="lp-card__body">
		<span class="lp-upsell__eyebrow"><?php echo esc_html( (string) ( $lp_state['eyebrow'] ?? '' ) ); ?></span>

		<h2 class="lp-card__title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></h2>

		<?php if ( '' !== (string) ( $args['promise'] ?? '' ) ) : ?>
			<p class="lp-card__subtitle"><?php echo esc_html( (string) $args['promise'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $lp_points ) ) : ?>
			<ul class="lp-feature-list">
				<?php foreach ( $lp_points as $lp_point ) : ?>
					<li>
						<?php Template::the_icon( 'check-circle', 'lp-icon lp-feature-list__icon' ); ?>
						<span><?php echo esc_html( (string) $lp_point ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="lp-upsell__foot">
			<a
				class="lp-btn lp-btn--primary"
				href="<?php echo esc_url( (string) ( $lp_state['cta_url'] ?? '' ) ); ?>"
				<?php if ( ! empty( $lp_state['external'] ) ) : ?>
					target="_blank" rel="noopener noreferrer"
				<?php endif; ?>
			>
				<?php echo esc_html( (string) ( $lp_state['cta_label'] ?? '' ) ); ?>
				<?php if ( ! empty( $lp_state['external'] ) ) : ?>
					<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
				<?php endif; ?>
			</a>

			<span class="lp-upsell__note">
				<?php echo esc_html( (string) ( $args['footnote'] ?? ( $lp_state['note'] ?? '' ) ) ); ?>
			</span>
		</div>
	</div>
</section>
