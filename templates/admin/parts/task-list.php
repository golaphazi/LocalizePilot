<?php
/**
 * Task list — the "Needs Your Attention" and insight rows.
 *
 * Each row is an icon tile, a title with an optional sub-line, and a trailing
 * affordance: either a chevron for navigation or a short action label.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $items   Each {icon, tone, title, meta, url, action, count}.
 *     @type string $variant default | detail. The detail variant is the
 *                          roomier row the SEO screen uses, with a bigger
 *                          tile and its own side padding.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_items = (array) ( $args['items'] ?? array() );

if ( empty( $lp_items ) ) {
	return;
}
?>
<ul class="lp-tasks<?php echo 'detail' === ( $args['variant'] ?? '' ) ? ' lp-tasks--detail' : ''; ?>">
	<?php
	foreach ( $lp_items as $lp_item ) :
		$lp_tone   = (string) ( $lp_item['tone'] ?? 'brand' );
		$lp_url    = (string) ( $lp_item['url'] ?? '' );
		$lp_action = (string) ( $lp_item['action'] ?? '' );
		$lp_count  = (string) ( $lp_item['count'] ?? '' );
		?>
		<li class="lp-task">
			<a class="lp-task__link" href="<?php echo esc_url( '' !== $lp_url ? $lp_url : '#' ); ?>">
				<?php if ( '' !== $lp_count ) : ?>
					<span class="lp-task__count lp-task__count--<?php echo esc_attr( $lp_tone ); ?>">
						<?php echo esc_html( $lp_count ); ?>
					</span>
				<?php else : ?>
					<span class="lp-task__icon lp-task__icon--<?php echo esc_attr( $lp_tone ); ?>">
						<?php Template::the_icon( (string) ( $lp_item['icon'] ?? 'nav-overview' ), 'lp-icon' ); ?>
					</span>
				<?php endif; ?>

				<span class="lp-task__text">
					<strong><?php echo esc_html( (string) ( $lp_item['title'] ?? '' ) ); ?></strong>
					<?php if ( ! empty( $lp_item['meta'] ) ) : ?>
						<span><?php echo esc_html( (string) $lp_item['meta'] ); ?></span>
					<?php endif; ?>
				</span>

				<?php if ( '' !== $lp_action ) : ?>
					<span class="lp-task__action"><?php echo esc_html( $lp_action ); ?></span>
				<?php else : ?>
					<span class="lp-task__chevron">
						<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
					</span>
				<?php endif; ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
