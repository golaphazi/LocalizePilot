<?php
/**
 * Language Switcher screen.
 *
 * Four controls are real and save through the Settings API: enable in header,
 * layout, label format and alignment. Flags, the Button layout, language
 * order, floating/footer placement, the block card and the Advanced Behavior
 * switches have no backend and are gated through Preview so they render as
 * designed but do nothing.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_data      = (array) ( $args['data'] ?? array() );
$lp_settings  = (array) ( $lp_data['settings'] ?? array() );
$lp_languages = (array) ( $lp_data['languages'] ?? array() );
$lp_base_url  = (string) ( $lp_data['base_url'] ?? '' );
$lp_in_header = ! empty( $lp_data['in_header'] );
$lp_placement = (array) ( $lp_data['placement'] ?? array() );
$lp_style     = (string) ( $lp_settings['menu_style'] ?? 'dropdown' );
$lp_position  = (string) ( $lp_settings['menu_position'] ?? 'end' );

Template::render(
	'parts/kpi-grid',
	array(
		'items' => array(
			array(
				'label'      => __( 'Switcher status', 'localizepilot' ),
				'value'      => $lp_in_header ? __( 'Enabled', 'localizepilot' ) : __( 'Disabled', 'localizepilot' ),
				'value_tone' => $lp_in_header ? 'success' : 'muted',
				'display'    => 'status',
				'note'       => $lp_in_header
					? __( 'Your language switcher is visible on the website.', 'localizepilot' )
					: __( 'The switcher only appears where you place it manually.', 'localizepilot' ),
			),
			array(
				'label' => __( 'Display languages', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_data['visitor_languages'] ?? 0 ) ),
				'note'  => __( 'Languages available to visitors', 'localizepilot' ),
			),
			array(
				'label'   => __( 'Current placement', 'localizepilot' ),
				'value'   => (string) ( $lp_placement['label'] ?? '' ),
				'display' => 'status',
				'note'    => (string) ( $lp_placement['note'] ?? '' ),
			),
		),
	)
);
?>

<form class="lp-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" data-lp-switcher-form>
	<?php
	Template::render(
		'parts/settings-form-fields',
		array(
			'tab'      => 'language-switcher',
			'redirect' => $lp_base_url,
		)
	);
	?>

	<div class="lp-cols lp-cols--wide-narrow">
		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Switcher configuration', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Choose how your language switcher looks and behaves.', 'localizepilot' ); ?></p>
				</div>
			</header>

			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/toggle',
					array(
						'name'        => Plugin::OPTION . '[header_switcher]',
						'label'       => __( 'Enable language switcher', 'localizepilot' ),
						'description' => __( 'Allow visitors to switch between your enabled languages.', 'localizepilot' ),
						'checked'     => $lp_in_header,
					)
				);
				?>

				<div class="lp-fieldset">
					<h3 class="lp-fieldset__title"><?php esc_html_e( 'Appearance', 'localizepilot' ); ?></h3>

					<div class="lp-control-row">
						<span class="lp-field__label"><?php esc_html_e( 'Layout', 'localizepilot' ); ?></span>
						<div class="lp-segmented" role="group" aria-label="<?php esc_attr_e( 'Switcher layout', 'localizepilot' ); ?>">
							<?php
							/*
							 * Dropdown and Inline are the two the renderer
							 * understands. Button is in the design only, so it
							 * renders as an option that cannot be chosen.
							 */
							$lp_layouts = array(
								'dropdown' => array( 'label' => __( 'Dropdown', 'localizepilot' ), 'feature' => '' ),
								'inline'   => array( 'label' => __( 'Inline', 'localizepilot' ), 'feature' => '' ),
								'button'   => array( 'label' => __( 'Button', 'localizepilot' ), 'feature' => 'switcher_placement' ),
							);

							foreach ( $lp_layouts as $lp_value => $lp_layout ) :
								$lp_gate = '' !== $lp_layout['feature'] ? Preview::attributes( $lp_layout['feature'] ) : '';
								?>
								<label class="lp-segmented__option<?php echo $lp_value === $lp_style ? ' is-active' : ''; ?>"<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>>
									<input
										type="radio"
										name="<?php echo esc_attr( Plugin::OPTION . '[menu_style]' ); ?>"
										value="<?php echo esc_attr( $lp_value ); ?>"
										data-lp-switcher-style
										<?php checked( $lp_value, $lp_style ); ?>
										<?php disabled( '' !== $lp_layout['feature'] ); ?>
									>
									<span><?php echo esc_html( $lp_layout['label'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<?php
					Template::render(
						'parts/field',
						array(
							'type'    => 'select',
							'name'    => Plugin::OPTION . '[language_label]',
							'id'      => 'lp-switcher-labels',
							'label'   => __( 'Language labels', 'localizepilot' ),
							'value'   => (string) ( $lp_settings['language_label'] ?? 'native' ),
							'options' => array(
								'native'  => __( 'Native names', 'localizepilot' ),
								'english' => __( 'English names', 'localizepilot' ),
								'code'    => __( 'Language codes', 'localizepilot' ),
							),
						)
					);

					Template::render(
						'parts/toggle',
						array(
							'name'        => 'localizepilot_switcher_flags',
							'label'       => __( 'Show flags', 'localizepilot' ),
							'description' => __( 'Display a flag beside each language.', 'localizepilot' ),
							'checked'     => true,
							'feature'     => 'switcher_flags',
						)
					);
					?>

					<div class="lp-control-row">
						<span class="lp-field__label"><?php esc_html_e( 'Alignment', 'localizepilot' ); ?></span>
						<div class="lp-segmented" role="group" aria-label="<?php esc_attr_e( 'Switcher alignment', 'localizepilot' ); ?>">
							<?php
							$lp_alignments = array(
								'start'  => __( 'Left', 'localizepilot' ),
								'center' => __( 'Center', 'localizepilot' ),
								'end'    => __( 'Right', 'localizepilot' ),
							);

							foreach ( $lp_alignments as $lp_value => $lp_label ) :
								?>
								<label class="lp-segmented__option<?php echo $lp_value === $lp_position ? ' is-active' : ''; ?>">
									<input
										type="radio"
										name="<?php echo esc_attr( Plugin::OPTION . '[menu_position]' ); ?>"
										value="<?php echo esc_attr( $lp_value ); ?>"
										<?php checked( $lp_value, $lp_position ); ?>
									>
									<span><?php echo esc_html( $lp_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</section>

		<?php
		Template::render(
			'parts/switcher-preview',
			array(
				'preview'   => (string) ( $lp_data['preview'] ?? '' ),
				'languages' => $lp_languages,
			)
		);
		?>
	</div>

	<?php
	Template::render(
		'parts/switcher-order',
		array(
			'languages' => $lp_languages,
		)
	);

	Template::render(
		'parts/switcher-placement',
		array(
			'in_header' => $lp_in_header,
		)
	);
	?>

	<div class="lp-cols lp-cols--even">
		<?php
		Template::render(
			'parts/switcher-shortcode',
			array(
				'shortcode' => (string) ( $lp_data['shortcode'] ?? '' ),
				'advanced'  => (string) ( $lp_data['advanced_shortcode'] ?? '' ),
			)
		);

		Template::render( 'parts/switcher-block', array() );
		?>
	</div>

	<section class="lp-card">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'Advanced behaviour', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'Fine-tune how the language switcher behaves for visitors.', 'localizepilot' ); ?></p>
			</div>
		</header>

		<div class="lp-card__body">
			<?php
			/*
			 * None of these four exist as settings. They are shown because the
			 * design specifies them, and gated so nobody believes they work.
			 */
			$lp_behaviour = array(
				array(
					'name'        => 'localizepilot_switcher_remember',
					'label'       => __( 'Remember visitor language', 'localizepilot' ),
					'description' => __( 'Remember the language selected by the visitor.', 'localizepilot' ),
					'checked'     => true,
				),
				array(
					'name'        => 'localizepilot_switcher_detect',
					'label'       => __( 'Detect browser language', 'localizepilot' ),
					'description' => __( 'Suggest a language based on the visitor\'s browser settings.', 'localizepilot' ),
					'checked'     => false,
				),
				array(
					'name'        => 'localizepilot_switcher_same_page',
					'label'       => __( 'Keep visitors on the same page', 'localizepilot' ),
					'description' => __( 'Open the equivalent translated page whenever available.', 'localizepilot' ),
					'checked'     => true,
				),
				array(
					'name'        => 'localizepilot_switcher_hide_single',
					'label'       => __( 'Hide when only one language is available', 'localizepilot' ),
					'description' => __( 'Hide the switcher when there is no language choice.', 'localizepilot' ),
					'checked'     => true,
				),
			);

			foreach ( $lp_behaviour as $lp_row ) {
				Template::render( 'parts/toggle', $lp_row + array( 'feature' => 'switcher_behavior' ) );
			}
			?>
		</div>
	</section>

	<?php
	Template::render(
		'parts/save-bar',
		array(
			'title'   => __( 'Save your LocalizePilot settings', 'localizepilot' ),
			'message' => __( 'Changes to the language switcher will update how visitors select languages on your site.', 'localizepilot' ),
			'label'   => __( 'Save changes', 'localizepilot' ),
		)
	);
	?>
</form>
