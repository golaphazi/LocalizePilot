<?php
/**
 * Language Switcher screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_data      = (array) ( $args['data'] ?? array() );
$lp_settings  = (array) ( $lp_data['settings'] ?? array() );
$lp_languages = (array) ( $lp_data['languages'] ?? array() );
$lp_style     = (string) ( $lp_settings['menu_style'] ?? 'dropdown' );
$lp_position  = (string) ( $lp_settings['menu_position'] ?? 'end' );
$lp_labels    = (string) ( $lp_settings['language_label'] ?? 'native' );
?>

<form
	class="lp-settings-form"
	method="post"
	action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>"
	data-lp-switcher-form
>
	<?php
	Template::render(
		'parts/settings-form-fields',
		array(
			'tab'      => 'language-switcher',
			'redirect' => (string) ( $lp_data['base_url'] ?? '' ),
		)
	);
	?>

	<div class="lp-cols lp-cols--equal lp-switcher-layout">
		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Default appearance', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Used by the automatic header switcher, shortcode, and Gutenberg block.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[header_switcher]',
						'label'       => __( 'Add switcher to the site header', 'localizepilot' ),
						'description' => __( 'Disable this when you place the switcher with a shortcode or block.', 'localizepilot' ),
						'checked'     => ! empty( $lp_settings['header_switcher'] ),
					)
				);
				?>

				<fieldset class="lp-choice-field">
					<legend class="lp-field__label"><?php esc_html_e( 'Default layout', 'localizepilot' ); ?></legend>
					<div class="lp-choice-grid lp-choice-grid--two">
						<label class="lp-choice-card<?php echo 'dropdown' === $lp_style ? ' is-selected' : ''; ?>">
							<input
								type="radio"
								name="<?php echo esc_attr( Plugin::OPTION ); ?>[menu_style]"
								value="dropdown"
								data-lp-switcher-style
								<?php checked( 'dropdown', $lp_style ); ?>
							>
							<span class="lp-choice-card__visual lp-choice-card__visual--dropdown">
								<i></i><i></i><i></i>
							</span>
							<span>
								<strong><?php esc_html_e( 'Dropdown', 'localizepilot' ); ?></strong>
								<small><?php esc_html_e( 'Compact menu for headers', 'localizepilot' ); ?></small>
							</span>
						</label>
						<label class="lp-choice-card<?php echo 'inline' === $lp_style ? ' is-selected' : ''; ?>">
							<input
								type="radio"
								name="<?php echo esc_attr( Plugin::OPTION ); ?>[menu_style]"
								value="inline"
								data-lp-switcher-style
								<?php checked( 'inline', $lp_style ); ?>
							>
							<span class="lp-choice-card__visual lp-choice-card__visual--inline">
								<i></i><i></i><i></i>
							</span>
							<span>
								<strong><?php esc_html_e( 'Inline links', 'localizepilot' ); ?></strong>
								<small><?php esc_html_e( 'Visible language list', 'localizepilot' ); ?></small>
							</span>
						</label>
					</div>
				</fieldset>

				<div class="lp-form-grid">
					<?php
					Template::render(
						'parts/field',
						array(
							'type'    => 'select',
							'id'      => 'lp-switcher-position',
							'name'    => Plugin::OPTION . '[menu_position]',
							'label'   => __( 'Default alignment', 'localizepilot' ),
							'value'   => $lp_position,
							'options' => array(
								'start'  => __( 'Start', 'localizepilot' ),
								'center' => __( 'Center', 'localizepilot' ),
								'end'    => __( 'End', 'localizepilot' ),
							),
						)
					);
					Template::render(
						'parts/field',
						array(
							'type'    => 'select',
							'id'      => 'lp-switcher-labels',
							'name'    => Plugin::OPTION . '[language_label]',
							'label'   => __( 'Language labels', 'localizepilot' ),
							'value'   => $lp_labels,
							'options' => array(
								'native'  => __( 'Native names', 'localizepilot' ),
								'english' => __( 'English names', 'localizepilot' ),
								'code'    => __( 'Language codes', 'localizepilot' ),
							),
						)
					);
					?>
				</div>
			</div>
		</section>

		<section class="lp-card lp-switcher-preview-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Live preview', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Preview follows the defaults on the left before you save.', 'localizepilot' ); ?></p>
				</div>
				<?php Template::render( 'parts/badge', array( 'label' => __( 'Preview', 'localizepilot' ), 'tone' => 'info' ) ); ?>
			</header>
			<div class="lp-card__body">
				<div
					class="lp-switcher-stage is-<?php echo esc_attr( $lp_position ); ?>"
					data-lp-switcher-stage
				>
					<div
						class="lp-switcher-demo<?php echo 'inline' === $lp_style ? ' is-inline' : ''; ?><?php echo 'code' === $lp_labels ? ' is-code-labels' : ''; ?>"
						data-lp-switcher-preview
					>
						<?php foreach ( $lp_languages as $lp_index => $lp_language ) : ?>
							<?php
							$lp_code = (string) ( $lp_language['code'] ?? '' );
							$lp_text = 'english' === $lp_labels
								? (string) ( $lp_language['name'] ?? '' )
								: ( 'code' === $lp_labels ? strtoupper( $lp_code ) : (string) ( $lp_language['native'] ?? '' ) );
							?>
							<span
								class="lp-switcher-demo__language<?php echo 0 === $lp_index ? ' is-current' : ''; ?>"
								data-lp-switcher-language
								data-code="<?php echo esc_attr( strtoupper( $lp_code ) ); ?>"
								data-native="<?php echo esc_attr( (string) ( $lp_language['native'] ?? '' ) ); ?>"
								data-english="<?php echo esc_attr( (string) ( $lp_language['name'] ?? '' ) ); ?>"
							>
								<i><?php echo esc_html( strtoupper( $lp_code ) ); ?></i>
								<b><?php echo esc_html( $lp_text ); ?></b>
							</span>
						<?php endforeach; ?>
						<span class="lp-switcher-demo__chevron" aria-hidden="true"></span>
					</div>
				</div>

				<div class="lp-note">
					<?php Template::the_icon( 'nav-language-switcher', 'lp-icon lp-note__icon' ); ?>
					<span><?php esc_html_e( 'An individual shortcode or block can override layout, labels, and alignment.', 'localizepilot' ); ?></span>
				</div>
			</div>
		</section>
	</div>

	<?php
	Template::render(
		'parts/save-bar',
		array(
			'title'   => __( 'Save switcher defaults', 'localizepilot' ),
			'message' => __( 'Existing shortcode and block overrides are left unchanged.', 'localizepilot' ),
			'label'   => __( 'Save switcher', 'localizepilot' ),
		)
	);
	?>
</form>

<div class="lp-cols lp-cols--equal lp-switcher-tools">
	<section class="lp-card lp-settings-card">
		<header class="lp-card__head">
			<div>
				<span class="lp-eyebrow"><?php esc_html_e( 'Shortcode', 'localizepilot' ); ?></span>
				<h2 class="lp-card__title"><?php esc_html_e( 'Place it anywhere', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'Use posts, pages, widgets, templates, or a supported page builder.', 'localizepilot' ); ?></p>
			</div>
		</header>
		<div class="lp-card__body">
			<div class="lp-code-copy">
				<code id="lp-switcher-shortcode">[localizepilot_switcher]</code>
				<button type="button" class="lp-btn lp-btn--ghost" data-lp-copy="#lp-switcher-shortcode"><?php esc_html_e( 'Copy', 'localizepilot' ); ?></button>
			</div>

			<h3 class="lp-subheading"><?php esc_html_e( 'Example with overrides', 'localizepilot' ); ?></h3>
			<div class="lp-code-copy">
				<code id="lp-switcher-shortcode-example">[localizepilot_switcher style="inline" labels="native" alignment="center"]</code>
				<button type="button" class="lp-btn lp-btn--ghost" data-lp-copy="#lp-switcher-shortcode-example"><?php esc_html_e( 'Copy', 'localizepilot' ); ?></button>
			</div>

			<dl class="lp-attribute-list">
				<div><dt><code>style</code></dt><dd>inherit, dropdown, inline</dd></div>
				<div><dt><code>labels</code></dt><dd>inherit, native, english, code</dd></div>
				<div><dt><code>alignment</code></dt><dd>inherit, start, center, end</dd></div>
				<div><dt><code>class</code></dt><dd><?php esc_html_e( 'Optional custom CSS class', 'localizepilot' ); ?></dd></div>
			</dl>
		</div>
	</section>

	<section class="lp-card lp-settings-card">
		<header class="lp-card__head">
			<div>
				<span class="lp-eyebrow"><?php esc_html_e( 'Gutenberg block', 'localizepilot' ); ?></span>
				<h2 class="lp-card__title"><?php esc_html_e( 'LocalizePilot Language Switcher', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'Add the native dynamic block from the WordPress block inserter.', 'localizepilot' ); ?></p>
			</div>
		</header>
		<div class="lp-card__body">
			<ol class="lp-steps">
				<li><span>1</span><p><?php esc_html_e( 'Open a post, page, template, header, or navigation area in the block editor.', 'localizepilot' ); ?></p></li>
				<li><span>2</span><p><?php esc_html_e( 'Search for “LocalizePilot Language Switcher”.', 'localizepilot' ); ?></p></li>
				<li><span>3</span><p><?php esc_html_e( 'Choose layout, label format, and alignment from the block sidebar.', 'localizepilot' ); ?></p></li>
			</ol>

			<div class="lp-feature-pills">
				<span><?php esc_html_e( 'Dynamic current-page URLs', 'localizepilot' ); ?></span>
				<span><?php esc_html_e( 'Enabled languages only', 'localizepilot' ); ?></span>
				<span><?php esc_html_e( 'Per-block overrides', 'localizepilot' ); ?></span>
			</div>
		</div>
	</section>
</div>
