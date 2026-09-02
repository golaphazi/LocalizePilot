<?php
/**
 * Shown on a console route whose screen template does not exist yet.
 *
 * This is scaffolding for the phased build, not a shipped state: it disappears
 * on its own as each screen template lands in templates/admin/screens.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_phases = array(
	'translations'      => 2,
	'languages'         => 2,
	'analytics'         => 2,
	'overview'          => 2,
	'media'             => 3,
	'seo-urls'          => 3,
	'providers'         => 4,
	'language-switcher' => 4,
	'settings'          => 4,
	'performance'       => 4,
);

$lp_slug  = (string) ( $args['slug'] ?? '' );
$lp_phase = $lp_phases[ $lp_slug ] ?? 0;
?>
<div class="lp-scaffold">
	<?php Template::the_icon( 'nav-' . $lp_slug, 'lp-scaffold__icon' ); ?>

	<h2 class="lp-scaffold__title">
		<?php
		/* translators: %s is the name of the console screen. */
		echo esc_html( sprintf( __( 'The %s screen is not built yet', 'localizepilot' ), (string) ( $args['title'] ?? '' ) ) );
		?>
	</h2>

	<p class="lp-scaffold__body">
		<?php if ( $lp_phase > 0 ) : ?>
			<?php
			/* translators: %d is the build phase number. */
			echo esc_html( sprintf( __( 'Scheduled for phase %d. The shell around it — sidebar, top bar, breadcrumb and routing — is finished and is what you are looking at now.', 'localizepilot' ), $lp_phase ) );
			?>
		<?php else : ?>
			<?php esc_html_e( 'The shell around it — sidebar, top bar, breadcrumb and routing — is finished and is what you are looking at now.', 'localizepilot' ); ?>
		<?php endif; ?>
	</p>

	<code class="lp-scaffold__path">templates/admin/screens/<?php echo esc_html( $lp_slug ); ?>.php</code>
</div>
