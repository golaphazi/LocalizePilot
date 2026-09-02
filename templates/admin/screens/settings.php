<?php
/**
 * General Settings screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_data     = (array) ( $args['data'] ?? array() );
$lp_settings = (array) ( $lp_data['settings'] ?? array() );
$lp_usage    = (array) ( $lp_data['usage'] ?? array() );
$lp_rules    = array(
	'translate_attributes',
	'translate_internal_links',
	'refresh_on_source_change',
	'stale_cache_fallback',
);
$lp_rule_count = count(
	array_filter(
		$lp_rules,
		static fn( string $key ): bool => ! empty( $lp_settings[ $key ] )
	)
);

if ( '' !== (string) ( $lp_data['analytics_message'] ?? '' ) ) :
	?>
	<div class="lp-banner lp-banner--info">
		<strong><?php esc_html_e( 'Analytics updated', 'localizepilot' ); ?></strong>
		<p><?php echo esc_html( (string) $lp_data['analytics_message'] ); ?></p>
	</div>
	<?php
endif;

Template::render(
	'parts/kpi-grid',
	array(
		'variant' => 'compact',
		'items'   => array(
			array(
				'label' => __( 'LocalizePilot', 'localizepilot' ),
				'value' => ! empty( $lp_settings['enabled'] ) ? __( 'Active', 'localizepilot' ) : __( 'Off', 'localizepilot' ),
				'note'  => ! empty( $lp_settings['enabled'] ) ? __( 'Language routes enabled', 'localizepilot' ) : __( 'Frontend disabled', 'localizepilot' ),
				'note_tone' => ! empty( $lp_settings['enabled'] ) ? 'success' : 'warning',
			),
			array(
				'label' => __( 'Daily usage', 'localizepilot' ),
				'value' => sprintf(
					/* translators: 1: Used requests, 2: Daily limit. */
					__( '%1$s / %2$s', 'localizepilot' ),
					number_format_i18n( (int) ( $lp_usage['count'] ?? 0 ) ),
					number_format_i18n( (int) ( $lp_usage['limit'] ?? 10 ) )
				),
				'note'  => ! empty( $lp_usage['enabled'] ) ? __( 'Limit active', 'localizepilot' ) : __( 'Limit disabled', 'localizepilot' ),
				'note_tone' => 'brand',
			),
			array(
				'label' => __( 'Translation rules', 'localizepilot' ),
				'value' => sprintf(
					/* translators: %s: Number of enabled rules. */
					__( '%s on', 'localizepilot' ),
					number_format_i18n( $lp_rule_count )
				),
				'note'  => __( 'of 4 available', 'localizepilot' ),
				'note_tone' => 'muted',
			),
			array(
				'label' => __( 'Analytics', 'localizepilot' ),
				'value' => ! empty( $lp_settings['analytics_enabled'] ) ? __( 'On', 'localizepilot' ) : __( 'Off', 'localizepilot' ),
				'note'  => sprintf(
					/* translators: %s: Retention days. */
					__( '%s-day retention', 'localizepilot' ),
					number_format_i18n( (int) ( $lp_settings['analytics_retention_days'] ?? 365 ) )
				),
				'note_tone' => ! empty( $lp_settings['analytics_enabled'] ) ? 'success' : 'muted',
			),
		),
	)
);
?>

<form class="lp-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
	<?php
	Template::render(
		'parts/settings-form-fields',
		array(
			'tab'      => 'settings',
			'redirect' => (string) ( $lp_data['base_url'] ?? '' ),
		)
	);
	?>

	<div class="lp-cols lp-cols--equal">
		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'System and usage', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Control frontend language routes and automatic translation limits.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[enabled]',
						'label'       => __( 'Enable LocalizePilot', 'localizepilot' ),
						'description' => __( 'Enable multilingual URLs and frontend translation.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['enabled'] ),
					)
				);
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[daily_limit_enabled]',
						'label'       => __( 'Daily API limit', 'localizepilot' ),
						'description' => __( 'Restrict how many new automatic translations can run each day.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['daily_limit_enabled'] ),
					)
				);
				Template::render(
					'parts/field',
					array(
						'type'  => 'number',
						'name'  => Plugin::OPTION . '[daily_limit]',
						'label' => __( 'Translations per day', 'localizepilot' ),
						'value' => (string) ( $lp_settings['daily_limit'] ?? 10 ),
						'min'   => '1',
						'max'   => '10000',
						'step'  => '1',
						'hint'  => __( 'The counter resets automatically at the start of each day.', 'localizepilot' ),
					)
				);
				?>
			</div>
		</section>

		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Translation behavior', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Choose what LocalizePilot rewrites and how it handles source changes.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[translate_attributes]',
						'label'       => __( 'Translate attributes', 'localizepilot' ),
						'description' => __( 'Alt, title, placeholder, ARIA labels, and supported SEO descriptions.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['translate_attributes'] ),
					)
				);
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[translate_internal_links]',
						'label'       => __( 'Localize internal links', 'localizepilot' ),
						'description' => __( 'Keep internal links inside the visitor’s selected language path.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['translate_internal_links'] ),
					)
				);
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[refresh_on_source_change]',
						'label'       => __( 'Refresh when source changes', 'localizepilot' ),
						'description' => __( 'Invalidate rendered HTML when the English source changes.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['refresh_on_source_change'] ),
					)
				);
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[stale_cache_fallback]',
						'label'       => __( 'Use stale cache on provider failure', 'localizepilot' ),
						'description' => __( 'Serve the last translation if the selected provider is unavailable.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['stale_cache_fallback'] ),
					)
				);
				?>
			</div>
		</section>
	</div>

	<div class="lp-cols lp-cols--equal">
		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'First-party analytics', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Measure local language-page views without storing raw IP addresses or user-agent values.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[analytics_enabled]',
						'label'       => __( 'Enable visitor analytics', 'localizepilot' ),
						'description' => __( 'Store page-view events locally for the Analytics screen.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['analytics_enabled'] ),
					)
				);
				Template::render(
					'parts/field',
					array(
						'type'  => 'number',
						'name'  => Plugin::OPTION . '[analytics_retention_days]',
						'label' => __( 'Data retention in days', 'localizepilot' ),
						'value' => (string) ( $lp_settings['analytics_retention_days'] ?? 365 ),
						'min'   => '7',
						'max'   => '3650',
						'step'  => '1',
						'hint'  => __( 'Events older than this window are removed by scheduled cleanup.', 'localizepilot' ),
					)
				);
				?>
				<div class="lp-danger-zone">
					<div>
						<strong><?php esc_html_e( 'Clear analytics data', 'localizepilot' ); ?></strong>
						<span><?php esc_html_e( 'Permanently delete every LocalizePilot visitor event.', 'localizepilot' ); ?></span>
					</div>
					<a
						class="lp-btn lp-btn--danger"
						href="<?php echo esc_url( (string) ( $lp_data['clear_analytics'] ?? '' ) ); ?>"
						data-lp-confirm="<?php esc_attr_e( 'Delete all LocalizePilot visitor analytics data?', 'localizepilot' ); ?>"
					>
						<?php esc_html_e( 'Clear data', 'localizepilot' ); ?>
					</a>
				</div>
			</div>
		</section>

		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Editorial workflow', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Gutenberg translations retain a persistent review state.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<div class="lp-status-guide">
					<div><?php Template::render( 'parts/badge', array( 'label' => __( 'Automatic', 'localizepilot' ), 'tone' => 'automatic' ) ); ?><span><?php esc_html_e( 'Generated by the selected API.', 'localizepilot' ); ?></span></div>
					<div><?php Template::render( 'parts/badge', array( 'label' => __( 'Edited', 'localizepilot' ), 'tone' => 'edited' ) ); ?><span><?php esc_html_e( 'Corrected manually in Gutenberg.', 'localizepilot' ); ?></span></div>
					<div><?php Template::render( 'parts/badge', array( 'label' => __( 'Reviewed', 'localizepilot' ), 'tone' => 'reviewed' ) ); ?><span><?php esc_html_e( 'Checked and approved for publishing.', 'localizepilot' ); ?></span></div>
					<div><?php Template::render( 'parts/badge', array( 'label' => __( 'Needs update', 'localizepilot' ), 'tone' => 'needs_update' ) ); ?><span><?php esc_html_e( 'The source changed after translation.', 'localizepilot' ); ?></span></div>
				</div>
				<a class="lp-btn lp-btn--ghost lp-btn--block" href="<?php echo esc_url( (string) ( $lp_data['translations_url'] ?? '' ) ); ?>">
					<?php esc_html_e( 'Manage Gutenberg translations', 'localizepilot' ); ?>
				</a>
			</div>
		</section>
	</div>

	<?php
	Template::render(
		'parts/save-bar',
		array(
			'title'   => __( 'Save general settings', 'localizepilot' ),
			'message' => __( 'Settings changes invalidate rendered page cache; saved Gutenberg translations remain safe.', 'localizepilot' ),
			'label'   => __( 'Save settings', 'localizepilot' ),
		)
	);
	?>
</form>
