<?php
/**
 * Language Insights and Needs Attention, the two cards that close the
 * Analytics screen.
 *
 * Both are computed from recorded activity and real coverage, and both drop
 * any row the data cannot support — so a quiet site shows an empty state
 * rather than an invented finding.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $insights  Each {icon, tone, title, note}.
 *     @type array  $attention Each {count, label, action, url, tone}.
 *     @type bool   $enabled   Whether analytics collection is on.
 *     @type string $settings_url Where to turn collection on.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_insights  = (array) ( $args['insights'] ?? array() );
$lp_attention = (array) ( $args['attention'] ?? array() );
$lp_enabled   = ! empty( $args['enabled'] );
$lp_settings  = (string) ( $args['settings_url'] ?? '' );
?>
<div class="lp-cols lp-cols--2">
	<section class="lp-card">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Language Insights', 'localizepilot' ); ?></h2>
			</div>
		</header>

		<div class="lp-card__body">
			<?php if ( empty( $lp_insights ) ) : ?>
				<?php
				Template::render(
					'parts/empty-state',
					array(
						'icon'    => 'nav-analytics',
						'title'   => $lp_enabled
							? __( 'Not enough history yet', 'localizepilot' )
							: __( 'Analytics collection is off', 'localizepilot' ),
						'message' => $lp_enabled
							? __( 'Insights compare this period with the one before it. They appear once there is activity in both.', 'localizepilot' )
							: __( 'Turn on analytics to compare how your language versions are performing.', 'localizepilot' ),
						'action'  => $lp_enabled ? array() : array(
							'label' => __( 'Analytics settings', 'localizepilot' ),
							'url'   => $lp_settings,
						),
						'level'   => 3,
					)
				);
				?>
			<?php else : ?>
				<ul class="lp-insight-list">
					<?php foreach ( $lp_insights as $lp_insight ) : ?>
						<li class="lp-insight">
							<span class="lp-insight__icon lp-insight__icon--<?php echo esc_attr( (string) ( $lp_insight['tone'] ?? 'brand' ) ); ?>">
								<?php Template::the_icon( (string) ( $lp_insight['icon'] ?? 'nav-analytics' ), 'lp-icon' ); ?>
							</span>
							<span class="lp-insight__body">
								<strong><?php echo esc_html( (string) ( $lp_insight['title'] ?? '' ) ); ?></strong>
								<small><?php echo esc_html( (string) ( $lp_insight['note'] ?? '' ) ); ?></small>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<section class="lp-card">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Needs Attention', 'localizepilot' ); ?></h2>
			</div>
		</header>

		<div class="lp-card__body">
			<?php if ( empty( $lp_attention ) ) : ?>
				<?php
				Template::render(
					'parts/empty-state',
					array(
						'icon'    => 'check-circle',
						'title'   => __( 'Nothing needs attention', 'localizepilot' ),
						'message' => __( 'Every enabled language is fully localized and none has lost traffic.', 'localizepilot' ),
						'level'   => 3,
					)
				);
				?>
			<?php else : ?>
				<ul class="lp-attention-list">
					<?php foreach ( $lp_attention as $lp_row ) : ?>
						<li class="lp-attention">
							<span class="lp-attention__count lp-attention__count--<?php echo esc_attr( (string) ( $lp_row['tone'] ?? 'muted' ) ); ?>">
								<?php echo esc_html( number_format_i18n( (int) ( $lp_row['count'] ?? 0 ) ) ); ?>
							</span>

							<span class="lp-attention__body">
								<strong><?php echo esc_html( (string) ( $lp_row['label'] ?? '' ) ); ?></strong>

								<?php if ( '' !== (string) ( $lp_row['url'] ?? '' ) ) : ?>
									<a class="lp-card__link" href="<?php echo esc_url( (string) $lp_row['url'] ); ?>">
										<?php echo esc_html( (string) ( $lp_row['action'] ?? '' ) ); ?>
										<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
									</a>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>
</div>
