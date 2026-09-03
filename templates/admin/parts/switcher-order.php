<?php
/**
 * Language order.
 *
 * The order is not stored anywhere, so the list reflects the enabled languages
 * in their current order and the reordering itself is gated through Preview.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array $languages Rows of {code, name, native, flag, is_source}.
 * }
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_languages = (array) ( $args['languages'] ?? array() );

if ( empty( $lp_languages ) ) {
	return;
}
?>
<section class="lp-card">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Language order', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Choose the order languages appear in your switcher.', 'localizepilot' ); ?></p>
		</div>

		<button type="button" class="lp-card__link"<?php echo Preview::attributes( 'switcher_order' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
			<?php esc_html_e( 'Reset order', 'localizepilot' ); ?>
		</button>
	</header>

	<div class="lp-card__body">
		<ul class="lp-order-list"<?php echo Preview::attributes( 'switcher_order' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
			<?php foreach ( $lp_languages as $lp_language ) : ?>
				<li class="lp-order-list__item">
					<span class="lp-order-list__grip" aria-hidden="true"></span>

					<?php if ( '' !== (string) ( $lp_language['flag'] ?? '' ) ) : ?>
						<span class="lp-order-list__flag" aria-hidden="true"><?php echo esc_html( (string) $lp_language['flag'] ); ?></span>
					<?php endif; ?>

					<span class="lp-order-list__name"><?php echo esc_html( (string) ( $lp_language['name'] ?? '' ) ); ?></span>

					<?php
					Template::render(
						'parts/lang-chip',
						array(
							'code'  => (string) ( $lp_language['code'] ?? '' ),
							'tone'  => empty( $lp_language['is_source'] ) ? 'target' : 'source',
							'title' => (string) ( $lp_language['native'] ?? '' ),
						)
					);

					Template::render(
						'parts/badge',
						array(
							'label' => empty( $lp_language['is_source'] )
								? __( 'Active', 'localizepilot' )
								: __( 'Source', 'localizepilot' ),
							'tone'  => empty( $lp_language['is_source'] ) ? 'success' : 'info',
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
