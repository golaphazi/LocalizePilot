<?php
/**
 * Console sidebar. Nav is generated from Screen_Registry, so it can never
 * drift from the registered pages.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_current = (string) ( $args['slug'] ?? '' );
$lp_groups  = (array) ( $args['navigation'] ?? array() );
?>
<aside class="lp-sidebar" id="lp-sidebar" aria-label="<?php esc_attr_e( 'LocalizePilot console', 'localizepilot' ); ?>">
	<div class="lp-sidebar__brand">
		<a class="lp-logo" href="<?php echo esc_url( admin_url( 'admin.php?page=localizepilot' ) ); ?>">
			<?php Template::the_icon( 'logo', 'lp-logo__mark' ); ?>
			<span class="screen-reader-text">LocalizePilot</span>
		</a>
	</div>

	<nav class="lp-nav" aria-label="<?php esc_attr_e( 'Console sections', 'localizepilot' ); ?>">
		<?php foreach ( $lp_groups as $lp_group ) : ?>
			<div class="lp-nav__group">
				<?php if ( '' !== (string) $lp_group['label'] ) : ?>
					<span class="lp-nav__title"><?php echo esc_html( $lp_group['label'] ); ?></span>
				<?php endif; ?>

				<ul class="lp-nav__list">
					<?php
					foreach ( (array) $lp_group['items'] as $lp_item ) :
						$lp_active = $lp_current === (string) $lp_item['slug'];
						?>
						<li>
							<a
								class="lp-nav__item<?php echo $lp_active ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( (string) $lp_item['url'] ); ?>"
								<?php echo $lp_active ? ' aria-current="page"' : ''; ?>
							>
								<?php Template::the_icon( (string) $lp_item['icon'], 'lp-nav__icon' ); ?>
								<span><?php echo esc_html( (string) $lp_item['label'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</nav>

	<div class="lp-sidebar__footer">
		<a class="lp-nav__item" href="https://localizepilot.com/help/" target="_blank" rel="noopener noreferrer">
			<?php Template::the_icon( 'nav-help-center', 'lp-nav__icon' ); ?>
			<span><?php esc_html_e( 'Help Center', 'localizepilot' ); ?></span>
		</a>
		<a class="lp-nav__item" href="https://localizepilot.com/docs/" target="_blank" rel="noopener noreferrer">
			<?php Template::the_icon( 'nav-documentation', 'lp-nav__icon' ); ?>
			<span><?php esc_html_e( 'Documentation', 'localizepilot' ); ?></span>
		</a>
		<span class="lp-sidebar__version">
			<?php
			/* translators: %s is the plugin version number. */
			echo esc_html( sprintf( __( 'Version %s', 'localizepilot' ), LOCALIZEPILOT_VERSION ) );
			?>
		</span>
	</div>
</aside>
