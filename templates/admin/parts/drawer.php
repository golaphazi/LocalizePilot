<?php
/**
 * Right-hand drawer.
 *
 * One partial serves both drawer designs — the plain detail view and the
 * warning variant — by filling or leaving empty the banner slot and swapping
 * the primary action label.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $id      DOM id used by the trigger's aria-controls.
 *     @type string $title   Drawer heading.
 *     @type array  $banner  Optional {tone, title, message}.
 *     @type string $body    Rendered HTML.
 *     @type array  $actions Footer buttons, each {label, style, url, icon}.
 *     @type bool   $open    Render already open, for a deep-linked drawer.
 * }
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_id      = (string) ( $args['id'] ?? 'lp-drawer' );
$lp_title   = (string) ( $args['title'] ?? '' );
$lp_banner  = (array) ( $args['banner'] ?? array() );
$lp_actions = (array) ( $args['actions'] ?? array() );
?>
<div class="lp-drawer" id="<?php echo esc_attr( $lp_id ); ?>" data-lp-drawer <?php echo empty( $args['open'] ) ? 'hidden' : ''; ?>>
	<div class="lp-drawer__backdrop" data-lp-drawer-close></div>

	<div class="lp-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $lp_id ); ?>-title">
		<header class="lp-drawer__head">
			<h2 class="lp-drawer__title" id="<?php echo esc_attr( $lp_id ); ?>-title"><?php echo esc_html( $lp_title ); ?></h2>
			<button type="button" class="lp-drawer__close" data-lp-drawer-close>
				<?php Template::the_icon( 'close', 'lp-icon' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close', 'localizepilot' ); ?></span>
			</button>
		</header>

		<div class="lp-drawer__body" data-lp-drawer-body>
			<?php
			if ( ! empty( $lp_banner['title'] ) ) :
				$lp_tone = (string) ( $lp_banner['tone'] ?? 'warning' );
				$lp_icon = (string) ( $lp_banner['icon'] ?? ( 'warning' === $lp_tone ? 'alert-triangle' : 'link' ) );
				?>
				<div class="lp-banner lp-banner--<?php echo esc_attr( $lp_tone ); ?>">
					<strong>
						<?php Template::the_icon( $lp_icon, 'lp-icon lp-banner__icon' ); ?>
						<span><?php echo esc_html( (string) $lp_banner['title'] ); ?></span>
					</strong>
					<?php if ( ! empty( $lp_banner['message'] ) ) : ?>
						<p><?php echo esc_html( (string) $lp_banner['message'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php echo $args['body'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered partial output. ?>
		</div>

		<?php if ( ! empty( $lp_actions ) ) : ?>
			<footer class="lp-drawer__foot">
				<?php
				foreach ( $lp_actions as $lp_action ) :
					$lp_style = 'primary' === ( $lp_action['style'] ?? '' ) ? 'lp-btn lp-btn--primary' : 'lp-btn lp-btn--ghost';
					$lp_url   = (string) ( $lp_action['url'] ?? '' );
					$lp_gate  = ! empty( $lp_action['feature'] ) ? Preview::attributes( (string) $lp_action['feature'] ) : '';
					$lp_label = (string) ( $lp_action['label'] ?? '' );

					if ( '' !== $lp_url ) :
						?>
						<a
							class="<?php echo esc_attr( $lp_style ); ?>"
							href="<?php echo esc_url( $lp_url ); ?>"
							<?php echo ! empty( $lp_action['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
							<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
						>
							<span><?php echo esc_html( $lp_label ); ?></span>
							<?php if ( ! empty( $lp_action['icon'] ) ) : ?>
								<?php Template::the_icon( (string) $lp_action['icon'], 'lp-icon' ); ?>
							<?php endif; ?>
						</a>
					<?php else : ?>
						<button
							type="button"
							class="<?php echo esc_attr( $lp_style ); ?>"
							<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
						>
							<span><?php echo esc_html( $lp_label ); ?></span>
							<?php if ( ! empty( $lp_action['icon'] ) ) : ?>
								<?php Template::the_icon( (string) $lp_action['icon'], 'lp-icon' ); ?>
							<?php endif; ?>
						</button>
						<?php
					endif;
				endforeach;
				?>
			</footer>
		<?php endif; ?>
	</div>
</div>
