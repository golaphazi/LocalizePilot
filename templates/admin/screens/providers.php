<?php
/**
 * Providers screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_data       = (array) ( $args['data'] ?? array() );
$lp_settings   = (array) ( $lp_data['settings'] ?? array() );
$lp_providers  = (array) ( $lp_data['providers'] ?? array() );
$lp_languages  = (array) ( $lp_data['languages'] ?? array() );
$lp_usage      = (array) ( $lp_data['usage'] ?? array() );
$lp_selected   = (string) ( $lp_settings['translation_provider'] ?? 'translatex' );
$lp_configured = count( array_filter( $lp_providers, static fn( array $provider ): bool => ! empty( $provider['configured'] ) ) );
$lp_active     = array_values(
	array_filter(
		$lp_providers,
		static fn( array $provider ): bool => (string) ( $provider['id'] ?? '' ) === $lp_selected
	)
);
$lp_active     = $lp_active[0] ?? array();
$lp_style_name = array(
	'faithful'  => __( 'Faithful', 'localizepilot' ),
	'natural'   => __( 'Natural', 'localizepilot' ),
	'marketing' => __( 'Marketing', 'localizepilot' ),
	'formal'    => __( 'Formal', 'localizepilot' ),
)[ (string) ( $lp_settings['ai_translation_style'] ?? 'natural' ) ] ?? __( 'Natural', 'localizepilot' );

Template::render(
	'parts/kpi-grid',
	array(
		'variant' => 'compact',
		'items'   => array(
			array(
				'label' => __( 'Active provider', 'localizepilot' ),
				'value' => (string) ( $lp_active['label'] ?? __( 'TranslateX', 'localizepilot' ) ),
				'note'  => ! empty( $lp_active['configured'] ) ? __( 'Connected', 'localizepilot' ) : __( 'Key needed', 'localizepilot' ),
				'note_tone' => ! empty( $lp_active['configured'] ) ? 'success' : 'warning',
			),
			array(
				'label' => __( 'Configured', 'localizepilot' ),
				'value' => number_format_i18n( $lp_configured ),
				'note'  => sprintf(
					/* translators: %s: Total provider count. */
					__( 'of %s providers', 'localizepilot' ),
					number_format_i18n( count( $lp_providers ) )
				),
				'note_tone' => 'muted',
			),
			array(
				'label' => __( 'Daily API use', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_usage['count'] ?? 0 ) ),
				'note'  => ! empty( $lp_usage['enabled'] )
					? sprintf(
						/* translators: %s: Remaining API requests. */
						__( '%s remaining', 'localizepilot' ),
						number_format_i18n( (int) ( $lp_usage['remaining'] ?? 0 ) )
					)
					: __( 'Limit disabled', 'localizepilot' ),
				'note_tone' => 'brand',
			),
			array(
				'label' => __( 'AI style', 'localizepilot' ),
				'value' => $lp_style_name,
				'note'  => __( 'Applied to AI providers', 'localizepilot' ),
				'note_tone' => 'muted',
			),
		),
	)
);
?>

<form
	class="lp-settings-form"
	method="post"
	action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>"
	data-lp-provider-form
>
	<?php
	Template::render(
		'parts/settings-form-fields',
		array(
			'tab'      => 'providers',
			'redirect' => (string) ( $lp_data['base_url'] ?? '' ),
		)
	);
	?>

	<section class="lp-card lp-settings-card">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Translation provider', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'Choose a dedicated translation API or a context-aware AI model.', 'localizepilot' ); ?></p>
			</div>
			<?php
			Template::render(
				'parts/badge',
				array(
					'label' => sprintf(
						/* translators: %s: Number of available providers. */
						__( '%s available', 'localizepilot' ),
						number_format_i18n( count( $lp_providers ) )
					),
					'tone'  => 'info',
				)
			);
			?>
		</header>

		<div class="lp-card__body">
			<div class="lp-provider-grid">
				<?php foreach ( $lp_providers as $lp_provider ) : ?>
					<?php
					$lp_id       = (string) ( $lp_provider['id'] ?? '' );
					$lp_is_active = $lp_id === $lp_selected;
					?>
					<label class="lp-provider-choice<?php echo $lp_is_active ? ' is-selected' : ''; ?>" data-lp-provider-choice>
						<input
							type="radio"
							name="<?php echo esc_attr( Plugin::OPTION ); ?>[translation_provider]"
							value="<?php echo esc_attr( $lp_id ); ?>"
							<?php checked( $lp_is_active ); ?>
						>
						<span class="lp-provider-choice__mark"><?php echo esc_html( (string) ( $lp_provider['mark'] ?? '' ) ); ?></span>
						<span class="lp-provider-choice__text">
							<strong><?php echo esc_html( (string) ( $lp_provider['label'] ?? '' ) ); ?></strong>
							<small><?php echo esc_html( (string) ( $lp_provider['description'] ?? '' ) ); ?></small>
						</span>
						<span class="lp-provider-choice__state">
							<?php if ( ! empty( $lp_provider['configured'] ) ) : ?>
								<?php Template::render( 'parts/badge', array( 'label' => __( 'Configured', 'localizepilot' ), 'tone' => 'success' ) ); ?>
							<?php else : ?>
								<?php Template::render( 'parts/badge', array( 'label' => 'ai' === ( $lp_provider['kind'] ?? '' ) ? __( 'AI', 'localizepilot' ) : __( 'API', 'localizepilot' ), 'tone' => 'neutral' ) ); ?>
							<?php endif; ?>
						</span>
						<i class="lp-provider-choice__radio" aria-hidden="true"></i>
					</label>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $lp_providers as $lp_provider ) : ?>
				<?php
				$lp_id          = (string) ( $lp_provider['id'] ?? '' );
				$lp_key_field   = (string) ( $lp_provider['key_field'] ?? '' );
				$lp_model_field = (string) ( $lp_provider['model_field'] ?? '' );
				$lp_is_active   = $lp_id === $lp_selected;
				?>
				<div
					class="lp-provider-panel"
					data-lp-provider-panel="<?php echo esc_attr( $lp_id ); ?>"
					<?php echo $lp_is_active ? '' : ' hidden'; ?>
				>
					<div class="lp-provider-panel__head">
						<div>
							<strong>
								<?php
								printf(
									/* translators: %s: Provider name. */
									esc_html__( 'Configure %s', 'localizepilot' ),
									esc_html( (string) ( $lp_provider['label'] ?? '' ) )
								);
								?>
							</strong>
							<span><?php esc_html_e( 'Saved credentials are never printed back into this page.', 'localizepilot' ); ?></span>
						</div>
						<?php if ( ! empty( $lp_provider['configured'] ) ) : ?>
							<?php Template::render( 'parts/badge', array( 'label' => __( 'Saved key', 'localizepilot' ), 'tone' => 'success' ) ); ?>
						<?php endif; ?>
					</div>

					<div class="lp-form-grid">
						<p class="lp-field">
							<label class="lp-field__label" for="lp-provider-key-<?php echo esc_attr( $lp_id ); ?>">
								<?php
								printf(
									/* translators: %s: Provider name. */
									esc_html__( '%s API key', 'localizepilot' ),
									esc_html( (string) ( $lp_provider['label'] ?? '' ) )
								);
								?>
							</label>
							<span class="lp-secret-field">
								<input
									class="lp-field__control"
									id="lp-provider-key-<?php echo esc_attr( $lp_id ); ?>"
									type="password"
									name="<?php echo esc_attr( Plugin::OPTION ); ?>[<?php echo esc_attr( $lp_key_field ); ?>]"
									value=""
									placeholder="<?php echo ! empty( $lp_provider['configured'] ) ? esc_attr__( 'Saved key · leave blank to keep it', 'localizepilot' ) : esc_attr__( 'Paste API key', 'localizepilot' ); ?>"
									autocomplete="new-password"
									data-lp-provider-key
								>
								<button type="button" class="lp-secret-field__reveal" data-lp-reveal>
									<?php esc_html_e( 'Show', 'localizepilot' ); ?>
								</button>
							</span>
							<label class="lp-mini-check">
								<input
									type="checkbox"
									name="<?php echo esc_attr( Plugin::OPTION ); ?>[clear_<?php echo esc_attr( $lp_key_field ); ?>]"
									value="1"
								>
								<span><?php esc_html_e( 'Remove the saved key when changes are saved', 'localizepilot' ); ?></span>
							</label>
						</p>

						<?php if ( '' !== $lp_model_field ) : ?>
							<p class="lp-field">
								<label class="lp-field__label" for="lp-provider-model-<?php echo esc_attr( $lp_id ); ?>">
									<?php esc_html_e( 'Model name', 'localizepilot' ); ?>
								</label>
								<input
									class="lp-field__control"
									id="lp-provider-model-<?php echo esc_attr( $lp_id ); ?>"
									type="text"
									name="<?php echo esc_attr( Plugin::OPTION ); ?>[<?php echo esc_attr( $lp_model_field ); ?>]"
									value="<?php echo esc_attr( (string) ( $lp_provider['model'] ?? '' ) ); ?>"
									placeholder="<?php echo esc_attr( (string) ( $lp_provider['default_model'] ?? '' ) ); ?>"
									data-lp-provider-model
								>
								<span class="lp-field__hint"><?php esc_html_e( 'Enter a model ID supported by your provider account.', 'localizepilot' ); ?></span>
							</p>
						<?php else : ?>
							<div class="lp-provider-panel__note">
								<strong><?php esc_html_e( 'Managed translation model', 'localizepilot' ); ?></strong>
								<span><?php esc_html_e( 'This provider selects its translation model automatically.', 'localizepilot' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="lp-provider-test">
				<div>
					<strong><?php esc_html_e( 'Test selected provider', 'localizepilot' ); ?></strong>
					<span><?php esc_html_e( 'Send a short connection test without saving this form first.', 'localizepilot' ); ?></span>
				</div>
				<div class="lp-provider-test__controls">
					<label class="screen-reader-text" for="lp-provider-test-language"><?php esc_html_e( 'Test language', 'localizepilot' ); ?></label>
					<select id="lp-provider-test-language" class="lp-field__control" data-lp-provider-test-language>
						<?php foreach ( $lp_languages as $lp_language ) : ?>
							<?php if ( ! empty( $lp_language['is_source'] ) ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) ( $lp_language['code'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $lp_language['name'] ?? '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="lp-btn lp-btn--ghost" data-lp-provider-test>
						<?php esc_html_e( 'Test connection', 'localizepilot' ); ?>
					</button>
				</div>
				<p class="lp-provider-test__result" role="status" aria-live="polite" data-lp-provider-test-result></p>
			</div>
		</div>
	</section>

	<div class="lp-cols lp-cols--equal">
		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Fallback provider', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Retry once with another service if the primary provider returns an error.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/field',
					array(
						'type'    => 'select',
						'name'    => Plugin::OPTION . '[fallback_provider]',
						'label'   => __( 'Fallback service', 'localizepilot' ),
						'value'   => (string) ( $lp_settings['fallback_provider'] ?? '' ),
						'options' => (array) ( $lp_data['provider_options'] ?? array() ),
						'hint'    => __( 'The primary provider can never also be its own fallback.', 'localizepilot' ),
					)
				);
				?>
			</div>
		</section>

		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'AI translation behavior', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Defaults shared by all context-aware AI providers.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<div class="lp-form-grid">
					<?php
					Template::render(
						'parts/field',
						array(
							'type'    => 'select',
							'name'    => Plugin::OPTION . '[ai_translation_style]',
							'label'   => __( 'Translation style', 'localizepilot' ),
							'value'   => (string) ( $lp_settings['ai_translation_style'] ?? 'natural' ),
							'options' => array(
								'faithful'  => __( 'Faithful', 'localizepilot' ),
								'natural'   => __( 'Natural', 'localizepilot' ),
								'marketing' => __( 'Marketing', 'localizepilot' ),
								'formal'    => __( 'Formal', 'localizepilot' ),
							),
						)
					);
					Template::render(
						'parts/field',
						array(
							'type'  => 'number',
							'name'  => Plugin::OPTION . '[ai_temperature]',
							'label' => __( 'Temperature', 'localizepilot' ),
							'value' => (string) ( $lp_settings['ai_temperature'] ?? '0.2' ),
							'min'   => '0',
							'max'   => '1',
							'step'  => '0.1',
							'hint'  => __( 'Lower values produce more consistent translations.', 'localizepilot' ),
						)
					);
					Template::render(
						'parts/field',
						array(
							'type'  => 'number',
							'name'  => Plugin::OPTION . '[ai_max_output_tokens]',
							'label' => __( 'Maximum output tokens', 'localizepilot' ),
							'value' => (string) ( $lp_settings['ai_max_output_tokens'] ?? 8192 ),
							'min'   => '512',
							'max'   => '32000',
							'step'  => '128',
						)
					);
					?>
				</div>
				<?php
				Template::render(
					'parts/field',
					array(
						'type'        => 'textarea',
						'name'        => Plugin::OPTION . '[ai_custom_instructions]',
						'label'       => __( 'Custom translation instructions', 'localizepilot' ),
						'value'       => (string) ( $lp_settings['ai_custom_instructions'] ?? '' ),
						'rows'        => 4,
						'placeholder' => __( 'Example: Keep product names in English and use informal Spanish.', 'localizepilot' ),
						'hint'        => __( 'Applied to every AI request. Do not include secrets here.', 'localizepilot' ),
					)
				);
				?>
			</div>
		</section>
	</div>

	<?php
	Template::render(
		'parts/save-bar',
		array(
			'title'   => __( 'Save provider configuration', 'localizepilot' ),
			'message' => __( 'Changing provider or model invalidates rendered translations; Gutenberg content remains safe.', 'localizepilot' ),
			'label'   => __( 'Save providers', 'localizepilot' ),
		)
	);
	?>
</form>
