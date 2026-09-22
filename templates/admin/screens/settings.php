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

	<?php
	$lp_content      = (array) ( $lp_data['content'] ?? array() );
	$lp_translations = (int) ( $lp_content['translations'] ?? 0 );
	?>
	<section class="lp-card lp-settings-card lp-content-settings">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Content and language', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'The language your site is written in, and which kinds of content can be translated.', 'localizepilot' ); ?></p>
			</div>
		</header>
		<div class="lp-card__body lp-content-settings__body">
			<div class="lp-content-settings__language">
				<?php
				Template::render(
					'parts/field',
					array(
						'type'    => 'select',
						'name'    => Plugin::OPTION . '[source_language]',
						'id'      => 'lp-source-language',
						'label'   => __( 'Default language', 'localizepilot' ),
						'value'   => (string) ( $lp_content['source'] ?? 'en' ),
						'options' => (array) ( $lp_content['languages'] ?? array() ),
						'hint'    => __( 'Pages without a language prefix are served in this language, and translations are made from it.', 'localizepilot' ),
					)
				);
				?>

				<?php if ( $lp_translations > 0 ) : ?>
					<div class="lp-banner lp-banner--warning lp-content-settings__guard">
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: number of translations, 2: language name such as "English". */
									_n(
										'This site has %1$s translation made from %2$s. Changing the default language does not re-translate it: it will be treated as made from the new language, and search engines will see your main URLs change language.',
										'This site has %1$s translations made from %2$s. Changing the default language does not re-translate them: they will be treated as made from the new language, and search engines will see your main URLs change language.',
										$lp_translations,
										'localizepilot'
									),
									number_format_i18n( $lp_translations ),
									(string) ( $lp_content['source_name'] ?? '' )
								)
							);
							?>
						</p>
						<label class="lp-content-settings__confirm">
							<span class="lp-checkbox">
								<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[source_language_confirm]" value="1">
							</span>
							<span><?php esc_html_e( 'Change the default language anyway', 'localizepilot' ); ?></span>
						</label>
					</div>
				<?php endif; ?>
			</div>

			<fieldset class="lp-content-settings__types">
				<legend class="lp-field__label"><?php esc_html_e( 'Translatable content', 'localizepilot' ); ?></legend>
				<?php
				/*
				 * Tells the sanitizer this list was on the form. Unticked
				 * boxes submit nothing, so without it "none ticked" and "not on
				 * this form" would look identical.
				 */
				?>
				<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translatable_post_types_field]" value="1">

				<div class="lp-type-list">
					<?php foreach ( (array) ( $lp_content['post_types'] ?? array() ) as $lp_type ) : ?>
						<label class="lp-type-option<?php echo empty( $lp_type['registered'] ) ? ' is-unavailable' : ''; ?>">
							<span class="lp-checkbox">
								<input
									type="checkbox"
									name="<?php echo esc_attr( Plugin::OPTION ); ?>[translatable_post_types][]"
									value="<?php echo esc_attr( (string) $lp_type['slug'] ); ?>"
									<?php checked( ! empty( $lp_type['checked'] ) ); ?>
									<?php disabled( empty( $lp_type['registered'] ) ); ?>
								>
							</span>
							<span class="lp-type-option__text">
								<strong><?php echo esc_html( (string) $lp_type['label'] ); ?></strong>
								<?php if ( empty( $lp_type['registered'] ) ) : ?>
									<span><?php esc_html_e( 'Not active on this site — kept for when it is.', 'localizepilot' ); ?></span>
								<?php else : ?>
									<code><?php echo esc_html( (string) $lp_type['slug'] ); ?></code>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<span class="lp-field__hint"><?php esc_html_e( 'Each ticked type gets a LocalizePilot panel in its editor and language URLs on your site. Turning one off keeps its existing translations.', 'localizepilot' ); ?></span>
			</fieldset>
		</div>
	</section>

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
						'description' => __( 'Invalidate rendered HTML when the original page changes.', 'localizepilot' ),
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
