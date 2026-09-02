<?php
/**
 * Empty state for tables and lists.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $icon    Icon name.
 *     @type string $title   Heading.
 *     @type string $message Supporting line.
 *     @type array  $action  Optional {label, url}.
 *     @type int    $level   Heading level, 3 by default because an empty state
 *                           almost always sits inside a card whose title is
 *                           already an h2. A card with no title passes 2, so
 *                           the outline never skips a level.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_action = (array) ( $args['action'] ?? array() );
$lp_level  = min( 4, max( 2, (int) ( $args['level'] ?? 3 ) ) );
$lp_tag    = 'h' . $lp_level;
?>
<div class="lp-empty">
	<?php Template::the_icon( (string) ( $args['icon'] ?? 'nav-overview' ), 'lp-empty__icon' ); ?>
	<<?php echo esc_html( $lp_tag ); ?> class="lp-empty__title">
		<?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?>
	</<?php echo esc_html( $lp_tag ); ?>>
	<?php if ( ! empty( $args['message'] ) ) : ?>
		<p class="lp-empty__message"><?php echo esc_html( (string) $args['message'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $lp_action['label'] ) ) : ?>
		<a class="lp-btn lp-btn--primary" href="<?php echo esc_url( (string) ( $lp_action['url'] ?? '#' ) ); ?>">
			<span><?php echo esc_html( (string) $lp_action['label'] ); ?></span>
		</a>
	<?php endif; ?>
</div>
