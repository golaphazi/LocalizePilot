<?php
/**
 * Analytics filter panel.
 *
 * A plain GET form, so filtered reports are shareable and work without
 * JavaScript. Export is gated behind Preview.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data    = (array) ( $args['data'] ?? array() );
$lp_filters = (array) ( $lp_data['filters'] ?? array() );
?>
<form class="lp-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-lp-spa-filter>
	<input type="hidden" name="page" value="localizepilot-analytics">

	<?php
	Template::render(
		'parts/field',
		array(
			'type'    => 'select',
			'name'    => 'language',
			'label'   => __( 'Language', 'localizepilot' ),
			'value'   => (string) ( $lp_filters['language'] ?? '' ),
			'options' => (array) ( $lp_data['languages'] ?? array() ),
		)
	);

	Template::render(
		'parts/field',
		array(
			'type'    => 'select',
			'name'    => 'range',
			'label'   => __( 'Date range', 'localizepilot' ),
			'value'   => (string) ( $lp_filters['range'] ?? '7' ),
			'options' => (array) ( $lp_data['ranges'] ?? array() ),
		)
	);

	Template::render(
		'parts/field',
		array(
			'name'        => 'url',
			'label'       => __( 'Specific page', 'localizepilot' ),
			'value'       => (string) ( $lp_filters['url'] ?? '' ),
			'placeholder' => __( 'Search page URL…', 'localizepilot' ),
		)
	);
	?>

	<div class="lp-filters__actions">
		<button type="submit" class="lp-btn lp-btn--primary">
			<span><?php esc_html_e( 'Apply Filters', 'localizepilot' ); ?></span>
		</button>
		<a class="lp-btn lp-btn--ghost" href="<?php echo esc_url( (string) ( $lp_data['base_url'] ?? '' ) ); ?>">
			<span><?php esc_html_e( 'Reset', 'localizepilot' ); ?></span>
		</a>
		<button
			type="button"
			class="lp-btn lp-btn--ghost"
			<?php echo Preview::attributes( 'analytics_export' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped literals. ?>
		>
			<span><?php esc_html_e( 'Export', 'localizepilot' ); ?></span>
		</button>
	</div>
</form>
