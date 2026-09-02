<?php
/**
 * Page header: title, description, and the screen's primary actions.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_actions = (array) ( $args['actions'] ?? array() );
?>
<div class="lp-page-header">
	<div class="lp-page-header__text">
		<h1 class="lp-page-title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></h1>
		<?php if ( '' !== (string) ( $args['description'] ?? '' ) ) : ?>
			<p class="lp-page-description"><?php echo esc_html( (string) $args['description'] ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $lp_actions ) ) : ?>
		<div class="lp-page-header__actions">
			<?php
			foreach ( $lp_actions as $lp_action ) :
				$lp_label    = (string) ( $lp_action['label'] ?? '' );
				$lp_url      = (string) ( $lp_action['url'] ?? '' );
				$lp_primary  = 'primary' === ( $lp_action['style'] ?? '' );
				$lp_style    = $lp_primary ? 'lp-btn lp-btn--primary' : 'lp-btn lp-btn--ghost';
				$lp_external = ! empty( $lp_action['external'] );
				$lp_gate     = ! empty( $lp_action['feature'] ) ? Preview::attributes( (string) $lp_action['feature'] ) : '';

				/*
				 * The design leads with a named icon — "+ Upload Media",
				 * "⚙ URL Settings" — and trails the plain call to action with
				 * its forward arrow, so icon placement follows from whether the
				 * screen named one.
				 */
				$lp_icon  = (string) ( $lp_action['icon'] ?? '' );
				$lp_leads = '' !== $lp_icon;

				if ( '' === $lp_icon && $lp_primary ) {
					$lp_icon = 'arrow-right';
				}

				if ( '' === $lp_label ) {
					continue;
				}

				if ( '' !== $lp_url ) :
					?>
					<a
						class="<?php echo esc_attr( $lp_style ); ?>"
						href="<?php echo esc_url( $lp_url ); ?>"
						<?php echo $lp_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
						<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
					>
						<?php if ( $lp_leads ) : ?>
							<?php Template::the_icon( $lp_icon, 'lp-icon' ); ?>
						<?php endif; ?>
						<span><?php echo esc_html( $lp_label ); ?></span>
						<?php if ( $lp_external ) : ?>
							<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
						<?php elseif ( ! $lp_leads && '' !== $lp_icon ) : ?>
							<?php Template::the_icon( $lp_icon, 'lp-icon' ); ?>
						<?php endif; ?>
					</a>
				<?php else : ?>
					<button
						type="button"
						class="<?php echo esc_attr( $lp_style ); ?>"
						data-lp-action="<?php echo esc_attr( sanitize_title( $lp_label ) ); ?>"
						<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
					>
						<?php if ( $lp_leads ) : ?>
							<?php Template::the_icon( $lp_icon, 'lp-icon' ); ?>
						<?php endif; ?>
						<span><?php echo esc_html( $lp_label ); ?></span>
						<?php if ( ! $lp_leads && '' !== $lp_icon ) : ?>
							<?php Template::the_icon( $lp_icon, 'lp-icon' ); ?>
						<?php endif; ?>
					</button>
					<?php
				endif;
			endforeach;
			?>
		</div>
	<?php endif; ?>
</div>
