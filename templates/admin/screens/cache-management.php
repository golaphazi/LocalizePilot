<?php
/**
 * Cache Management screen.
 *
 * Configures the cache, operates on the generated files, and lists them. The
 * numbers and the file list are real; the two automation toggles under Cache
 * Behavior are design-only and go through Preview so they are visibly inert.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

$lp_data     = (array) ( $args['data'] ?? array() );
$lp_settings = (array) ( $lp_data['settings'] ?? array() );
$lp_stats    = (array) ( $lp_data['stats'] ?? array() );
$lp_health   = (array) ( $lp_data['health'] ?? array() );
$lp_history  = (array) ( $lp_data['history'] ?? array() );
$lp_filters  = (array) ( $lp_data['filters'] ?? array() );
$lp_actions  = (array) ( $lp_data['actions'] ?? array() );
$lp_base_url = (string) ( $lp_data['base_url'] ?? '' );

$lp_expired  = (int) ( $lp_stats['expired'] ?? 0 );
$lp_writable = (bool) ( $lp_stats['writable'] ?? false );
$lp_recent   = (array) ( $lp_stats['recent'] ?? array() );
$lp_latest   = 0;

foreach ( $lp_recent as $lp_entry ) {
	$lp_latest = max( $lp_latest, (int) ( $lp_entry['modified'] ?? 0 ) );
}

if ( '' !== (string) ( $lp_data['message'] ?? '' ) ) :
	?>
	<div class="lp-banner lp-banner--info">
		<strong><?php esc_html_e( 'Cache updated', 'localizepilot' ); ?></strong>
		<p><?php echo esc_html( (string) $lp_data['message'] ); ?></p>
	</div>
	<?php
endif;

/*
 * The default variant, not the compact one the list screens use: this design
 * stacks the caption under the value rather than setting it alongside.
 */
Template::render(
	'parts/kpi-grid',
	array(
		'items' => array(
			array(
				'label' => __( 'Rendered pages', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_stats['count'] ?? 0 ) ),
				'note'  => __( 'Cached localized pages', 'localizepilot' ),
			),
			array(
				'label' => __( 'Cache size', 'localizepilot' ),
				'value' => size_format( (int) ( $lp_stats['size'] ?? 0 ), 2 ) ?: '0 B',
				'note'  => __( 'Total generated cache', 'localizepilot' ),
			),
			array(
				'label'      => __( 'Expired cache', 'localizepilot' ),
				'value'      => number_format_i18n( $lp_expired ),
				'value_tone' => $lp_expired > 0 ? 'warning' : '',
				'note'       => $lp_expired > 0
					? __( 'Ready to clean', 'localizepilot' )
					: __( 'Nothing to clean', 'localizepilot' ),
				'note_tone'  => $lp_expired > 0 ? 'warning' : 'muted',
			),
			array(
				'label'      => __( 'Cache status', 'localizepilot' ),
				'value'      => (string) ( $lp_health['label'] ?? '' ),
				'value_tone' => (string) ( $lp_health['tone'] ?? 'success' ),
				'display'    => 'status',
				'note'       => $lp_latest > 0
					? sprintf(
						/* translators: %s: Human-readable time difference, e.g. "2 mins". */
						__( 'Last generated %s ago', 'localizepilot' ),
						human_time_diff( $lp_latest )
					)
					: __( 'Nothing generated yet', 'localizepilot' ),
			),
		),
	)
);
?>

<div class="lp-banner lp-banner--<?php echo esc_attr( (string) ( $lp_health['tone'] ?? 'success' ) ); ?> lp-banner--icon">
	<span class="lp-banner__icon">
		<?php Template::the_icon( 'success' === ( $lp_health['tone'] ?? '' ) ? 'check-circle' : 'alert-triangle', 'lp-icon' ); ?>
	</span>
	<div class="lp-banner__body">
		<strong><?php echo esc_html( (string) ( $lp_health['title'] ?? '' ) ); ?></strong>
		<p><?php echo esc_html( (string) ( $lp_health['message'] ?? '' ) ); ?></p>
	</div>
	<a class="lp-card__link" href="<?php echo esc_url( add_query_arg( 'cache_type', 'all', $lp_base_url ) ); ?>">
		<?php esc_html_e( 'View cache logs', 'localizepilot' ); ?>
		<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
	</a>
</div>

<form class="lp-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
	<?php
	/*
	 * The performance tab already sanitises exactly these three fields, so this
	 * screen reuses it rather than introducing a second sanitiser for the same
	 * option keys.
	 */
	Template::render(
		'parts/settings-form-fields',
		array(
			'tab'      => 'performance',
			'redirect' => $lp_base_url,
		)
	);
	?>

	<section class="lp-card lp-settings-card">
		<header class="lp-card__head">
			<div>
				<span class="lp-card__eyebrow"><?php esc_html_e( 'Cache configuration', 'localizepilot' ); ?></span>
				<h2 class="lp-card__title"><?php esc_html_e( 'HTML &amp; object cache', 'localizepilot' ); ?></h2>
				<p class="lp-card__subtitle"><?php esc_html_e( 'Configure how LocalizePilot stores and serves translated page content.', 'localizepilot' ); ?></p>
			</div>
		</header>

		<div class="lp-card__body">
			<?php
			Template::render(
				'parts/toggle',
				array(
					'name'        => Plugin::OPTION . '[cache_enabled]',
					'label'       => __( 'HTML file cache', 'localizepilot' ),
					'description' => __( 'Save translated output as persistent HTML files for faster page delivery.', 'localizepilot' ),
					'checked'     => ! empty( $lp_settings['cache_enabled'] ),
				)
			);

			Template::render(
				'parts/toggle',
				array(
					'name'        => Plugin::OPTION . '[object_cache_enabled]',
					'label'       => __( 'WordPress object cache', 'localizepilot' ),
					'description' => __( 'Use WordPress object caching as an additional cache layer.', 'localizepilot' ),
					'checked'     => ! empty( $lp_settings['object_cache_enabled'] ),
				)
			);

			Template::render(
				'parts/field',
				array(
					'type'   => 'number',
					'name'   => Plugin::OPTION . '[cache_hours]',
					'label'  => __( 'Cache lifetime', 'localizepilot' ),
					'value'  => (string) ( $lp_settings['cache_hours'] ?? 24 ),
					'min'    => '1',
					'max'    => '8760',
					'step'   => '1',
					'suffix' => __( 'hours', 'localizepilot' ),
					'hint'   => __( 'Cached pages older than this period can be automatically marked as expired.', 'localizepilot' ),
				)
			);
			?>

			<div class="lp-fieldset">
				<h3 class="lp-fieldset__title"><?php esc_html_e( 'Cache behaviour', 'localizepilot' ); ?></h3>

				<?php
				Template::render(
					'parts/toggle',
					array(
						'name'        => 'localizepilot_cache_auto_expire',
						'label'       => __( 'Clear expired cache automatically', 'localizepilot' ),
						'description' => __( 'Remove expired rendered pages automatically when cache cleanup runs.', 'localizepilot' ),
						'checked'     => true,
						'feature'     => 'cache_automation',
					)
				);

				Template::render(
					'parts/toggle',
					array(
						'name'        => 'localizepilot_cache_auto_refresh',
						'label'       => __( 'Refresh cache when source content changes', 'localizepilot' ),
						'description' => __( 'Automatically invalidate translated cache when the original content is updated.', 'localizepilot' ),
						'checked'     => true,
						'feature'     => 'cache_automation',
					)
				);
				?>
			</div>

			<div class="lp-fieldset">
				<h3 class="lp-fieldset__title"><?php esc_html_e( 'Cache directory', 'localizepilot' ); ?></h3>

				<div class="lp-path-row lp-path-row--bare">
					<input
						id="lp-cache-directory"
						class="lp-input lp-input--path"
						type="text"
						readonly
						value="<?php echo esc_attr( (string) ( $lp_stats['path'] ?? '' ) ); ?>"
						aria-label="<?php esc_attr_e( 'Cache directory', 'localizepilot' ); ?>"
					/>
					<button type="button" class="lp-btn lp-btn--icon" data-lp-copy="#lp-cache-directory">
						<?php Template::the_icon( 'copy', 'lp-icon' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Copy cache directory path', 'localizepilot' ); ?></span>
					</button>
				</div>

				<p class="lp-path-note">
					<?php
					Template::render(
						'parts/badge',
						array(
							'label' => $lp_writable
								? __( 'Writable', 'localizepilot' )
								: __( 'Not writable', 'localizepilot' ),
							'tone'  => $lp_writable ? 'success' : 'danger',
						)
					);
					?>
					<span><?php esc_html_e( 'Directory where LocalizePilot stores generated localized HTML files.', 'localizepilot' ); ?></span>
				</p>
			</div>
		</div>
	</section>

	<?php
	Template::render(
		'parts/save-bar',
		array(
			'title'   => __( 'Save your LocalizePilot settings', 'localizepilot' ),
			'message' => __( 'Changes to cache settings will apply to newly generated cache files.', 'localizepilot' ),
			'label'   => __( 'Save cache settings', 'localizepilot' ),
		)
	);
	?>
</form>

<section class="lp-card">
	<header class="lp-card__head">
		<div>
			<span class="lp-card__eyebrow"><?php esc_html_e( 'Cache actions', 'localizepilot' ); ?></span>
			<h2 class="lp-card__title"><?php esc_html_e( 'Manage your cache', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Clean specific cache types without changing your cache configuration.', 'localizepilot' ); ?></p>
		</div>
	</header>

	<div class="lp-card__body">
		<ul class="lp-action-rows">
			<?php
			$lp_rows = array(
				array(
					'key'         => 'clear_expired',
					'title'       => __( 'Clear expired pages', 'localizepilot' ),
					'description' => __( 'Remove cache files that have passed their lifetime.', 'localizepilot' ),
					'label'       => __( 'Clear expired', 'localizepilot' ),
					'tone'        => 'warning',
				),
				array(
					'key'         => 'clear_all',
					'title'       => __( 'Clear rendered cache', 'localizepilot' ),
					'description' => __( 'Remove all generated localized HTML pages.', 'localizepilot' ),
					'label'       => __( 'Clear rendered cache', 'localizepilot' ),
					'tone'        => 'warning',
				),
				array(
					'key'         => 'clear_snapshots',
					'title'       => __( 'Clear Gutenberg snapshots', 'localizepilot' ),
					'description' => __( 'Remove stored Gutenberg translation snapshots.', 'localizepilot' ),
					'label'       => __( 'Clear snapshots', 'localizepilot' ),
					'tone'        => 'warning',
				),
				array(
					'key'         => 'purge_host',
					'title'       => __( 'Purge entire cache', 'localizepilot' ),
					'description' => __( 'Delete all LocalizePilot cache files and regenerate them when visitors request pages.', 'localizepilot' ),
					'label'       => __( 'Purge all cache', 'localizepilot' ),
					'tone'        => 'danger',
					'icon'        => 'trash',
				),
			);

			foreach ( $lp_rows as $lp_row ) :
				$lp_url = (string) ( $lp_actions[ $lp_row['key'] ] ?? '' );

				if ( '' === $lp_url ) {
					continue;
				}
				?>
				<li class="lp-action-row">
					<?php if ( ! empty( $lp_row['icon'] ) ) : ?>
						<span class="lp-action-row__icon lp-action-row__icon--<?php echo esc_attr( $lp_row['tone'] ); ?>">
							<?php Template::the_icon( (string) $lp_row['icon'], 'lp-icon' ); ?>
						</span>
					<?php endif; ?>

					<div class="lp-action-row__body">
						<strong><?php echo esc_html( $lp_row['title'] ); ?></strong>
						<p><?php echo esc_html( $lp_row['description'] ); ?></p>
					</div>

					<a class="lp-btn lp-btn--<?php echo esc_attr( $lp_row['tone'] ); ?>" href="<?php echo esc_url( $lp_url ); ?>">
						<?php echo esc_html( $lp_row['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php
Template::render(
	'parts/cache-history',
	array(
		'history'   => $lp_history,
		'filters'   => $lp_filters,
		'base_url'  => $lp_base_url,
		'eyebrow'   => __( 'Cache history', 'localizepilot' ),
		'title'     => __( 'Generated cache', 'localizepilot' ),
		'subtitle'  => __( 'Review and manage cached localized pages.', 'localizepilot' ),
		'count'     => true,
		'toolbar'   => true,
		'languages' => (array) ( $lp_data['languages'] ?? array() ),
	)
);
