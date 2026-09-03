<?php
/**
 * Live preview of the language switcher.
 *
 * The switcher markup is the real one, rendered by Language_Switcher, so this
 * shows what visitors get rather than a picture of it. The Desktop/Mobile
 * toggle is design-only.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $preview   Rendered switcher markup.
 *     @type array  $languages Enabled languages, for the empty-state count.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_preview   = (string) ( $args['preview'] ?? '' );
$lp_languages = (array) ( $args['languages'] ?? array() );
$lp_labels    = array();

foreach ( $lp_languages as $lp_language ) {
	$lp_code = sanitize_key( (string) ( $lp_language['code'] ?? '' ) );

	if ( '' === $lp_code ) {
		continue;
	}

	$lp_labels[ $lp_code ] = array(
		'native'  => (string) ( $lp_language['native'] ?? strtoupper( $lp_code ) ),
		'english' => (string) ( $lp_language['name'] ?? strtoupper( $lp_code ) ),
		'code'    => strtoupper( $lp_code ),
	);
}
?>
<section
	class="lp-card lp-switcher-preview"
	data-lp-switcher-preview
	data-lp-switcher-labels="<?php echo esc_attr( (string) wp_json_encode( $lp_labels ) ); ?>"
>
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Live preview', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'See how your language switcher will appear on your website.', 'localizepilot' ); ?></p>
		</div>

		<div class="lp-segmented lp-segmented--sm" role="group" aria-label="<?php esc_attr_e( 'Preview width', 'localizepilot' ); ?>">
			<button type="button" class="lp-segmented__option is-active" data-lp-preview-device="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'localizepilot' ); ?></button>
			<button type="button" class="lp-segmented__option" data-lp-preview-device="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'localizepilot' ); ?></button>
		</div>
	</header>

	<div class="lp-card__body">
		<div class="lp-switcher-frame" data-lp-switcher-frame>
			<div class="lp-switcher-frame__bar">
				<span class="lp-switcher-frame__brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>

				<nav class="lp-switcher-frame__nav" aria-hidden="true">
					<span><?php esc_html_e( 'Home', 'localizepilot' ); ?></span>
					<span><?php esc_html_e( 'About', 'localizepilot' ); ?></span>
					<span><?php esc_html_e( 'Services', 'localizepilot' ); ?></span>
				</nav>

				<div class="lp-switcher-frame__switcher">
					<?php
					if ( '' !== $lp_preview ) {
						// Output of Language_Switcher::render(), which escapes as it builds.
						echo wp_kses_post( $lp_preview );
					} else {
						Template::render(
							'parts/empty-state',
							array(
								'icon'    => 'nav-language-switcher',
								'title'   => __( 'Nothing to switch between', 'localizepilot' ),
								'message' => __( 'Enable a second language to preview the switcher.', 'localizepilot' ),
								'level'   => 3,
							)
						);
					}
					?>
				</div>
			</div>

			<div class="lp-switcher-frame__body">
				<span><?php esc_html_e( 'Website content area', 'localizepilot' ); ?></span>
			</div>
		</div>

		<p class="lp-switcher-preview__note" data-lp-switcher-preview-note data-count="<?php echo esc_attr( (string) count( $lp_languages ) ); ?>">
			<?php
			printf(
				/* translators: %d: Number of languages shown in the switcher. */
				esc_html( _n( 'Desktop preview · %d language', 'Desktop preview · %d languages', count( $lp_languages ), 'localizepilot' ) ),
				(int) count( $lp_languages )
			);
			?>
		</p>
	</div>
</section>
