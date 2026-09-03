<?php
/**
 * Translation provider performance.
 *
 * The provider list and its connection state are real — the plugin knows
 * which providers hold a key and which one is selected. Response time and
 * success rate are not measured, so they render as dashes.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $providers Each {id, label, mark, state, tone, ready}.
 *     @type string $url       Link to the Providers screen.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_providers = (array) ( $args['providers'] ?? array() );
$lp_url       = (string) ( $args['url'] ?? '' );
?>
<section class="lp-card">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Translation provider performance', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Monitor response time and reliability across your active translation providers.', 'localizepilot' ); ?></p>
		</div>
	</header>

	<div class="lp-card__body">
		<?php if ( empty( $lp_providers ) ) : ?>
			<?php
			Template::render(
				'parts/empty-state',
				array(
					'icon'    => 'nav-providers',
					'title'   => __( 'No provider connected', 'localizepilot' ),
					'message' => __( 'Add a provider API key to see it listed here.', 'localizepilot' ),
					'action'  => array(
						'label' => __( 'Set up a provider', 'localizepilot' ),
						'url'   => $lp_url,
					),
					'level'   => 3,
				)
			);
			?>
		<?php else : ?>
			<ul class="lp-provider-perf">
				<?php foreach ( $lp_providers as $lp_provider ) : ?>
					<li class="lp-provider-perf__row">
						<div class="lp-provider-perf__id">
							<span class="lp-provider-perf__mark" aria-hidden="true"><?php echo esc_html( (string) ( $lp_provider['mark'] ?? '' ) ); ?></span>
							<div>
								<strong><?php echo esc_html( (string) ( $lp_provider['label'] ?? '' ) ); ?></strong>
								<?php
								Template::render(
									'parts/badge',
									array(
										'label' => (string) ( $lp_provider['state'] ?? '' ),
										'tone'  => (string) ( $lp_provider['tone'] ?? 'neutral' ),
									)
								);
								?>
							</div>
						</div>

						<div class="lp-provider-perf__metric">
							<span><?php esc_html_e( 'Response time', 'localizepilot' ); ?></span>
							<?php Template::render( 'parts/metric', array( 'inline' => true ) ); ?>
						</div>

						<div class="lp-provider-perf__metric">
							<span><?php esc_html_e( 'Success rate', 'localizepilot' ); ?></span>
							<?php Template::render( 'parts/metric', array( 'inline' => true ) ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

			<a class="lp-card__link" href="<?php echo esc_url( $lp_url ); ?>">
				<?php esc_html_e( 'Manage providers', 'localizepilot' ); ?>
				<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
