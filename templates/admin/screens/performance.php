<?php
/**
 * Performance.
 *
 * LocalizePilot publishes the three events this screen would be built on —
 * page-render time, rendered-cache outcomes and provider-call time — and keeps
 * none of them. Recording, storing and reporting them is the paid add-on's job.
 *
 * So this screen does not render a dashboard full of dashes. It says what the
 * feature does and where to get it, and renders no figure at all: an empty
 * gauge invites you to read a value into it, and there is no value here to
 * read. When the add-on is installed and licensed it replaces this screen
 * entirely, and every number on it comes from a counter something incremented.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Paywall;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data    = (array) ( $args['data'] ?? array() );
$lp_feature = (array) ( Paywall::get( 'performance' ) ?? array() );
$lp_urls    = (array) ( $lp_data['urls'] ?? array() );
?>
<div class="lp-column">

	<?php
	Template::render(
		'parts/upsell',
		array(
			'title'   => (string) ( $lp_feature['title'] ?? __( 'Performance monitoring', 'localizepilot' ) ),
			'promise' => (string) ( $lp_feature['promise'] ?? '' ),
			'points'  => (array) ( $lp_feature['points'] ?? array() ),
		)
	);
	?>

	<section class="lp-card">
		<div class="lp-card__body">
			<h2 class="lp-card__title"><?php esc_html_e( 'What LocalizePilot already tells you', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle">
				<?php esc_html_e( 'Performance monitoring measures delivery over time. These screens describe how things are set up right now, and they are part of the free plugin.', 'localizepilot' ); ?>
			</p>

			<div class="lp-license-help__links">
				<a class="lp-card__link" href="<?php echo esc_url( (string) ( $lp_urls['cache'] ?? '' ) ); ?>">
					<?php esc_html_e( 'Cache Management', 'localizepilot' ); ?>
					<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
				</a>
				<a class="lp-card__link" href="<?php echo esc_url( (string) ( $lp_urls['providers'] ?? '' ) ); ?>">
					<?php esc_html_e( 'Providers', 'localizepilot' ); ?>
					<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
				</a>
				<a class="lp-card__link" href="<?php echo esc_url( (string) ( $lp_urls['languages'] ?? '' ) ); ?>">
					<?php esc_html_e( 'Languages', 'localizepilot' ); ?>
					<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
				</a>
			</div>
		</div>
	</section>

</div>
