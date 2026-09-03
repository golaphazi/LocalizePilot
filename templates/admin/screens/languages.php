<?php
/**
 * Languages screen.
 *
 * The enabled-language cards and the picker are one form, so the whole
 * language set is saved in a single submit — matching the design's save bar.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_data      = (array) ( $args['data'] ?? array() );
$lp_enabled   = (array) ( $lp_data['enabled'] ?? array() );
$lp_available = (array) ( $lp_data['available'] ?? array() );

Template::render(
	'parts/kpi-grid',
	array(
		'variant' => 'compact',
		'items'   => array(
			array(
				'label'     => __( 'Enabled Languages', 'localizepilot' ),
				'value'     => number_format_i18n( count( $lp_enabled ) ),
				'note'      => __( 'including the source', 'localizepilot' ),
				'note_tone' => 'muted',
			),
			array(
				'label'     => __( 'Available Languages', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_data['catalog_total'] ?? 0 ) ),
				'note'      => __( 'supported languages', 'localizepilot' ),
				'note_tone' => 'muted',
			),
			array(
				'label'     => __( 'Translated Languages', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_data['translated'] ?? 0 ) ),
				'note'      => sprintf(
					/* translators: %s is a percentage. */
					__( '%s%% average coverage', 'localizepilot' ),
					number_format_i18n( (int) ( $lp_data['coverage'] ?? 0 ) )
				),
				'note_tone' => 'brand',
			),
		),
	)
);
?>

<form class="lp-stack lp-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
	<?php
	Template::render(
		'parts/settings-form-fields',
		array(
			'tab'      => 'languages',
			'redirect' => (string) ( $lp_data['base_url'] ?? '' ),
		)
	);
	?>

	<section class="lp-section">
		<header class="lp-section__head">
			<div>
				<h2 class="lp-section__title"><?php esc_html_e( 'Enabled Languages', 'localizepilot' ); ?></h2>
				<p class="lp-section__subtitle"><?php esc_html_e( 'These languages are currently available on your website.', 'localizepilot' ); ?></p>
			</div>
		</header>

		<div class="lp-lang-grid">
			<?php foreach ( $lp_enabled as $lp_language ) : ?>
				<article class="lp-lang-card<?php echo ! empty( $lp_language['is_source'] ) ? ' is-source' : ''; ?>">
					<div class="lp-lang-card__head">
						<span class="lp-lang-card__code"><?php echo esc_html( strtoupper( (string) $lp_language['code'] ) ); ?></span>
						<span class="lp-lang-card__names">
							<strong><?php echo esc_html( (string) $lp_language['name'] ); ?></strong>
							<span><?php echo esc_html( strtoupper( (string) $lp_language['code'] ) . ' · ' . $lp_language['native'] ); ?></span>
						</span>
					</div>

					<?php if ( ! empty( $lp_language['is_source'] ) ) : ?>
						<p class="lp-lang-card__source"><?php esc_html_e( 'Source language', 'localizepilot' ); ?></p>
					<?php else : ?>
						<p class="lp-lang-card__coverage">
							<?php
							printf(
								/* translators: %s is a percentage. */
								esc_html__( '%s%% translated', 'localizepilot' ),
								esc_html( number_format_i18n( (int) $lp_language['coverage'] ) )
							);
							?>
						</p>
						<?php
						Template::render(
							'parts/progress',
							array( 'value' => (int) $lp_language['coverage'], 'width' => '100%' )
						);
						?>
					<?php endif; ?>

					<?php
					Template::render(
						'parts/badge',
						array( 'tone' => 'reviewed', 'label' => __( 'Active', 'localizepilot' ) )
					);
					?>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="lp-section" id="lp-available-languages">
		<header class="lp-section__head">
			<div>
				<h2 class="lp-section__title"><?php esc_html_e( 'Available Languages', 'localizepilot' ); ?></h2>
				<p class="lp-section__subtitle"><?php esc_html_e( 'Choose additional languages to make available on your site.', 'localizepilot' ); ?></p>
			</div>
		</header>

		<div class="lp-lang-grid lp-lang-grid--picker">
			<?php foreach ( $lp_available as $lp_language ) : ?>
				<label class="lp-lang-option<?php echo ! empty( $lp_language['enabled'] ) ? ' is-selected' : ''; ?>">
					<span class="lp-lang-card__code"><?php echo esc_html( strtoupper( (string) $lp_language['code'] ) ); ?></span>
					<span class="lp-lang-card__names">
						<strong><?php echo esc_html( (string) $lp_language['name'] ); ?></strong>
						<span><?php echo esc_html( strtoupper( (string) $lp_language['code'] ) . ' · ' . $lp_language['native'] ); ?></span>
					</span>
					<span class="lp-checkbox">
						<input
							type="checkbox"
							name="<?php echo esc_attr( Plugin::OPTION ); ?>[enabled_languages][]"
							value="<?php echo esc_attr( (string) $lp_language['code'] ); ?>"
							<?php checked( ! empty( $lp_language['enabled'] ) ); ?>
						>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
	</section>

	<?php
	$lp_url_rows = '';

	foreach ( (array) ( $lp_data['urls'] ?? array() ) as $lp_url ) {
		$lp_url_rows .= '<div class="lp-url-row"><span>' . esc_html( (string) $lp_url['label'] ) . '</span>'
			. '<code>' . esc_html( (string) $lp_url['url'] ) . '</code></div>';
	}

	Template::render(
		'parts/card',
		array(
			'title'    => __( 'Language URLs', 'localizepilot' ),
			'subtitle' => __( 'LocalizePilot keeps visitors on the same page while changing the language prefix in the URL.', 'localizepilot' ),
			'body'     => '<div class="lp-url-structure"><span>' . esc_html__( 'URL structure', 'localizepilot' ) . '</span><code>/{language}/{path}/</code></div>'
				. '<div class="lp-url-list">' . $lp_url_rows . '</div>',
		)
	);
	?>

	<div class="lp-save-bar">
		<div>
			<strong><?php esc_html_e( 'Save your language settings', 'localizepilot' ); ?></strong>
			<span><?php esc_html_e( 'Changing the enabled languages clears the rendered page cache.', 'localizepilot' ); ?></span>
		</div>
		<button type="submit" class="lp-btn lp-btn--primary">
			<span><?php esc_html_e( 'Save languages', 'localizepilot' ); ?></span>
		</button>
	</div>
</form>
