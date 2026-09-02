<?php
/**
 * Card — the surface every screen section sits on.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $title    Optional heading.
 *     @type string $subtitle Optional line under the heading.
 *     @type array  $link     Optional {label, url, external} shown at the right of the header.
 *     @type string $body     Rendered HTML for the card body.
 *     @type string $class    Extra classes.
 *     @type bool   $flush    True when the body supplies its own padding (tables).
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_title    = (string) ( $args['title'] ?? '' );
$lp_subtitle = (string) ( $args['subtitle'] ?? '' );
$lp_link     = (array) ( $args['link'] ?? array() );
$lp_flush    = ! empty( $args['flush'] );
$lp_classes  = 'lp-card' . ( $lp_flush ? ' lp-card--flush' : '' ) . ' ' . (string) ( $args['class'] ?? '' );
?>
<section class="<?php echo esc_attr( trim( $lp_classes ) ); ?>">
	<?php if ( '' !== $lp_title ) : ?>
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php echo esc_html( $lp_title ); ?></h2>
				<?php if ( '' !== $lp_subtitle ) : ?>
					<p class="lp-card__subtitle"><?php echo esc_html( $lp_subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $lp_link['label'] ) ) : ?>
				<a class="lp-card__link" href="<?php echo esc_url( (string) ( $lp_link['url'] ?? '#' ) ); ?>">
					<?php echo esc_html( (string) $lp_link['label'] ); ?>
					<?php if ( ! empty( $lp_link['external'] ) ) : ?>
						<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
					<?php else : ?>
						<?php Template::the_icon( 'arrow-right-sm', 'lp-icon lp-card__link-arrow' ); ?>
					<?php endif; ?>
				</a>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<div class="lp-card__body">
		<?php echo $args['body'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered partial output. ?>
	</div>
</section>
