<?php
/**
 * Search result list used by the top-bar command search.
 *
 * @package LocalizePilot
 * @var array<string,mixed> $args
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_results = (array) ( $args['results'] ?? array() );
$lp_query   = (string) ( $args['query'] ?? '' );

if ( empty( $lp_results ) ) :
	?>
	<div class="lp-command__empty">
		<?php Template::the_icon( 'search', 'lp-icon' ); ?>
		<strong><?php esc_html_e( 'No matching results', 'localizepilot' ); ?></strong>
		<span><?php esc_html_e( 'Try a page title, language, setting, or console screen.', 'localizepilot' ); ?></span>
	</div>
	<?php
	return;
endif;
?>
<p class="lp-command__hint">
	<?php echo '' === $lp_query ? esc_html__( 'Jump to', 'localizepilot' ) : esc_html__( 'Search results', 'localizepilot' ); ?>
</p>
<ul class="lp-command__results" role="listbox">
	<?php foreach ( $lp_results as $lp_result ) : ?>
		<li>
			<a
				class="lp-command__result"
				href="<?php echo esc_url( (string) ( $lp_result['url'] ?? '' ) ); ?>"
				role="option"
				<?php echo ! empty( $lp_result['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
			>
				<?php Template::the_icon( (string) ( $lp_result['icon'] ?? 'search' ), 'lp-icon' ); ?>
				<span>
					<strong><?php echo esc_html( (string) ( $lp_result['title'] ?? '' ) ); ?></strong>
					<small><?php echo esc_html( (string) ( $lp_result['meta'] ?? '' ) ); ?></small>
				</span>
				<?php Template::the_icon( 'arrow-right-sm', 'lp-icon lp-command__arrow' ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
