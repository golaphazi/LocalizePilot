<?php
/**
 * Performance and cache screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;
use LocalizePilot\Analytics;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Provider_Catalog;

defined( 'ABSPATH' ) || exit;

$lp_data     = (array) ( $args['data'] ?? array() );
$lp_settings = (array) ( $lp_data['settings'] ?? array() );
$lp_stats    = (array) ( $lp_data['stats'] ?? array() );
$lp_history  = (array) ( $lp_data['history'] ?? array() );
$lp_filters  = (array) ( $lp_data['filters'] ?? array() );
$lp_actions  = (array) ( $lp_data['actions'] ?? array() );
$lp_base_url = (string) ( $lp_data['base_url'] ?? '' );

if ( '' !== (string) ( $lp_data['message'] ?? '' ) ) :
	?>
	<div class="lp-banner lp-banner--info">
		<strong><?php esc_html_e( 'Cache updated', 'localizepilot' ); ?></strong>
		<p><?php echo esc_html( (string) $lp_data['message'] ); ?></p>
	</div>
	<?php
endif;

if ( '' !== (string) ( $lp_data['host_cache'] ?? '' ) ) :
	?>
	<div class="lp-banner lp-banner--info">
		<strong>
			<?php
			printf(
				/* translators: %s: Detected page-cache product. */
				esc_html__( '%s detected', 'localizepilot' ),
				esc_html( (string) $lp_data['host_cache'] )
			);
			?>
		</strong>
		<p><?php esc_html_e( 'LocalizePilot requests a purge when translations or cache settings change. You can also purge it manually below.', 'localizepilot' ); ?></p>
	</div>
	<?php
endif;

Template::render(
	'parts/kpi-grid',
	array(
		'variant' => 'compact',
		'items'   => array(
			array(
				'label' => __( 'Rendered pages', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_stats['count'] ?? 0 ) ),
				'note'  => ! empty( $lp_settings['cache_enabled'] ) ? __( 'File cache on', 'localizepilot' ) : __( 'File cache off', 'localizepilot' ),
				'note_tone' => ! empty( $lp_settings['cache_enabled'] ) ? 'success' : 'muted',
			),
			array(
				'label' => __( 'Snapshots', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_stats['snapshots'] ?? 0 ) ),
				'note'  => __( 'Gutenberg HTML', 'localizepilot' ),
				'note_tone' => 'violet',
			),
			array(
				'label' => __( 'Cache size', 'localizepilot' ),
				'value' => size_format( (int) ( $lp_stats['size'] ?? 0 ), 2 ),
				'note'  => ! empty( $lp_stats['writable'] ) ? __( 'Directory writable', 'localizepilot' ) : __( 'Not writable', 'localizepilot' ),
				'note_tone' => ! empty( $lp_stats['writable'] ) ? 'success' : 'warning',
			),
			array(
				'label' => __( 'Expired entries', 'localizepilot' ),
				'value' => number_format_i18n( (int) ( $lp_stats['expired'] ?? 0 ) ),
				'note'  => sprintf(
					/* translators: %s: Cache lifetime in hours. */
					__( '%s-hour lifetime', 'localizepilot' ),
					number_format_i18n( (int) ( $lp_settings['cache_hours'] ?? 24 ) )
				),
				'note_tone' => ! empty( $lp_stats['expired'] ) ? 'warning' : 'muted',
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
			'tab'      => 'performance',
			'redirect' => $lp_base_url,
		)
	);
	?>

	<div class="lp-cols lp-cols--wide-narrow">
		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'HTML and object cache', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Translated output is stored under wp-content/cache/localizepilot.', 'localizepilot' ); ?></p>
				</div>
			</header>
			<div class="lp-card__body">
				<div class="lp-form-grid">
					<div>
						<?php
						Template::render(
							'parts/toggle',
							array(
								'name'        => Plugin::OPTION . '[cache_enabled]',
								'label'       => __( 'HTML file cache', 'localizepilot' ),
								'description' => __( 'Save translated output as persistent HTML files.', 'localizepilot' ),
								'checked'     => ! empty( $lp_settings['cache_enabled'] ),
							)
						);
						?>
					</div>
					<div>
						<?php
						Template::render(
							'parts/toggle',
							array(
								'name'        => Plugin::OPTION . '[object_cache_enabled]',
								'label'       => __( 'WordPress object cache', 'localizepilot' ),
								'description' => __( 'Use wp_cache_get and wp_cache_set as the fast first layer.', 'localizepilot' ),
								'checked'     => ! empty( $lp_settings['object_cache_enabled'] ),
							)
						);
						?>
					</div>
				</div>

				<?php
				Template::render(
					'parts/field',
					array(
						'type'  => 'number',
						'name'  => Plugin::OPTION . '[cache_hours]',
						'label' => __( 'Cache lifetime in hours', 'localizepilot' ),
						'value' => (string) ( $lp_settings['cache_hours'] ?? 24 ),
						'min'   => '1',
						'max'   => '8760',
						'step'  => '1',
						'hint'  => __( 'Expired pages can be removed manually without touching Gutenberg snapshots.', 'localizepilot' ),
					)
				);
				?>

				<div class="lp-path-row">
					<span><?php esc_html_e( 'Cache directory', 'localizepilot' ); ?></span>
					<code><?php echo esc_html( (string) ( $lp_stats['path'] ?? '' ) ); ?></code>
					<?php
					Template::render(
						'parts/badge',
						array(
							'label' => ! empty( $lp_stats['writable'] ) ? __( 'Writable', 'localizepilot' ) : __( 'Not writable', 'localizepilot' ),
							'tone'  => ! empty( $lp_stats['writable'] ) ? 'success' : 'danger',
						)
					);
					?>
				</div>
			</div>
		</section>

		<section class="lp-card lp-settings-card">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title"><?php esc_html_e( 'Cache effectiveness', 'localizepilot' ); ?></h2>
					<p class="lp-card__subtitle"><?php esc_html_e( 'Request-level counters are not recorded by the current cache engine.', 'localizepilot' ); ?></p>
				</div>
				<?php Template::render( 'parts/badge', array( 'label' => __( 'Preview', 'localizepilot' ), 'tone' => 'neutral' ) ); ?>
			</header>
			<div class="lp-card__body">
				<div class="lp-hit-rate" <?php echo Preview::attributes( 'cache_hit_rate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals. ?>>
					<span><?php esc_html_e( 'Hit rate', 'localizepilot' ); ?></span>
					<strong aria-label="<?php esc_attr_e( 'Not available', 'localizepilot' ); ?>">—</strong>
					<small><?php esc_html_e( 'UI ready; counters not connected', 'localizepilot' ); ?></small>
				</div>
				<dl class="lp-stat-list">
					<div><dt><?php esc_html_e( 'File cache', 'localizepilot' ); ?></dt><dd><?php echo ! empty( $lp_settings['cache_enabled'] ) ? esc_html__( 'Active', 'localizepilot' ) : esc_html__( 'Off', 'localizepilot' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Object cache', 'localizepilot' ); ?></dt><dd><?php echo ! empty( $lp_settings['object_cache_enabled'] ) ? esc_html__( 'Active', 'localizepilot' ) : esc_html__( 'Off', 'localizepilot' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Host cache', 'localizepilot' ); ?></dt><dd><?php echo '' !== (string) ( $lp_data['host_cache'] ?? '' ) ? esc_html( (string) $lp_data['host_cache'] ) : esc_html__( 'Not detected', 'localizepilot' ); ?></dd></div>
				</dl>
			</div>
		</section>
	</div>

	<?php
	Template::render(
		'parts/save-bar',
		array(
			'title'   => __( 'Save cache configuration', 'localizepilot' ),
			'message' => __( 'Changing cache layers or lifetime clears rendered page files safely.', 'localizepilot' ),
			'label'   => __( 'Save performance', 'localizepilot' ),
		)
	);
	?>
</form>

<section class="lp-card lp-card--flush lp-cache-history">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title"><?php esc_html_e( 'Generated HTML history', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle">
				<?php
				printf(
					/* translators: %s: Total cache entries. */
					esc_html__( '%s total entries. Newest files appear first.', 'localizepilot' ),
					esc_html( number_format_i18n( (int) ( $lp_history['total'] ?? 0 ) ) )
				);
				?>
			</p>
		</div>
		<div class="lp-cache-actions">
			<a class="lp-btn lp-btn--ghost" href="<?php echo esc_url( (string) ( $lp_actions['clear_expired'] ?? '' ) ); ?>"><?php esc_html_e( 'Clear expired', 'localizepilot' ); ?></a>
			<a class="lp-btn lp-btn--ghost" href="<?php echo esc_url( (string) ( $lp_actions['purge_host'] ?? '' ) ); ?>"><?php esc_html_e( 'Purge host cache', 'localizepilot' ); ?></a>
			<a
				class="lp-btn lp-btn--danger"
				href="<?php echo esc_url( (string) ( $lp_actions['clear_all'] ?? '' ) ); ?>"
				data-lp-confirm="<?php esc_attr_e( 'Clear every rendered LocalizePilot cache file?', 'localizepilot' ); ?>"
			><?php esc_html_e( 'Clear rendered', 'localizepilot' ); ?></a>
			<a
				class="lp-btn lp-btn--danger"
				href="<?php echo esc_url( (string) ( $lp_actions['clear_snapshots'] ?? '' ) ); ?>"
				data-lp-confirm="<?php esc_attr_e( 'Clear every generated Gutenberg translation snapshot?', 'localizepilot' ); ?>"
			><?php esc_html_e( 'Clear snapshots', 'localizepilot' ); ?></a>
		</div>
	</header>

	<nav class="lp-subnav" aria-label="<?php esc_attr_e( 'Cache history type', 'localizepilot' ); ?>">
		<?php
		$lp_filter_labels = array(
			'all'      => __( 'All', 'localizepilot' ),
			'page'     => __( 'Rendered pages', 'localizepilot' ),
			'snapshot' => __( 'Gutenberg snapshots', 'localizepilot' ),
		);
		foreach ( $lp_filter_labels as $lp_type => $lp_label ) :
			$lp_url = add_query_arg( 'cache_type', $lp_type, $lp_base_url );
			?>
			<a
				class="lp-subnav__item<?php echo $lp_type === ( $lp_filters['type'] ?? 'all' ) ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( $lp_url ); ?>"
				<?php echo $lp_type === ( $lp_filters['type'] ?? 'all' ) ? ' aria-current="page"' : ''; ?>
			><?php echo esc_html( $lp_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php
	$lp_rows = array();

	foreach ( (array) ( $lp_history['items'] ?? array() ) as $lp_item ) {
		$lp_key      = (string) ( $lp_item['key'] ?? '' );
		$lp_type     = (string) ( $lp_item['type'] ?? 'page' );
		$lp_url      = (string) ( $lp_item['url'] ?? '' );
		$lp_language = sanitize_key( (string) ( $lp_item['language'] ?? '' ) );
		$lp_provider = sanitize_key( (string) ( $lp_item['provider'] ?? '' ) );
		$lp_status   = (string) ( $lp_item['status'] ?? '' );
		$lp_modified = (int) ( $lp_item['modified'] ?? 0 );
		$lp_expired  = ! empty( $lp_item['expired'] );

		$lp_name = '' !== $lp_url ? Analytics::display_url( $lp_url ) : $lp_key . '.html';
		$lp_provider_status = '' !== $lp_provider && Provider_Catalog::exists( $lp_provider )
			? Provider_Catalog::label( $lp_provider )
			: ( '' !== $lp_status ? ucfirst( str_replace( '_', ' ', $lp_status ) ) : '—' );

		$lp_entry = '<span class="lp-cell__title">' . esc_html( $lp_name ) . '</span>'
			. '<span class="lp-cell__muted">' . esc_html( $lp_key . '.html' ) . '</span>';

		if ( $lp_expired ) {
			$lp_entry .= Template::capture( 'parts/badge', array( 'label' => __( 'Expired', 'localizepilot' ), 'tone' => 'warning' ) );
		}

		$lp_language_cell = Language_Catalog::exists( $lp_language )
			? Template::capture(
				'parts/lang-chip',
				array(
					'code'  => $lp_language,
					'tone'  => 'target',
					'title' => Language_Catalog::label( $lp_language, 'english' ),
				)
			)
			: '<span class="lp-cell__muted">—</span>';

		$lp_rows[] = array(
			'id'    => $lp_key,
			'label' => $lp_name,
			'class' => $lp_expired ? 'is-expired' : '',
			'cells' => array(
				'entry'    => '<div class="lp-cache-entry">' . $lp_entry . '</div>',
				'type'     => Template::capture(
					'parts/badge',
					array(
						'label' => 'snapshot' === $lp_type ? __( 'Snapshot', 'localizepilot' ) : __( 'Rendered', 'localizepilot' ),
						'tone'  => 'snapshot' === $lp_type ? 'violet' : 'info',
					)
				),
				'language' => $lp_language_cell,
				'traffic'  => '<span class="lp-cell__title">' . esc_html( number_format_i18n( (int) ( $lp_item['visitors'] ?? 0 ) ) ) . '</span>'
					. '<span class="lp-cell__muted">' . esc_html(
						sprintf(
							/* translators: %s: Page views. */
							__( '%s views', 'localizepilot' ),
							number_format_i18n( (int) ( $lp_item['views'] ?? 0 ) )
						)
					) . '</span>',
				'provider' => '<span class="lp-cell__muted">' . esc_html( $lp_provider_status ) . '</span>',
				'updated'  => '<span class="lp-cell__muted">' . esc_html(
					$lp_modified > 0
						? sprintf(
							/* translators: %s: Human-readable time difference. */
							__( '%s ago', 'localizepilot' ),
							human_time_diff( $lp_modified, current_time( 'timestamp' ) )
						)
						: __( 'Unknown', 'localizepilot' )
				) . '</span>',
				'size'     => '<span class="lp-cell__muted">' . esc_html( size_format( (int) ( $lp_item['bytes'] ?? 0 ), 1 ) ) . '</span>',
				'actions'  => '<a class="lp-text-action lp-text-action--danger" href="' . esc_url( (string) ( $lp_item['delete_url'] ?? '' ) ) . '" data-lp-confirm="' . esc_attr__( 'Delete this cache entry?', 'localizepilot' ) . '">' . esc_html__( 'Delete', 'localizepilot' ) . '</a>',
			),
		);
	}

	Template::render(
		'parts/table',
		array(
			'label'   => __( 'Generated HTML cache history', 'localizepilot' ),
			'columns' => array(
				array( 'key' => 'entry', 'label' => __( 'Cache entry', 'localizepilot' ), 'width' => '28%' ),
				array( 'key' => 'type', 'label' => __( 'Type', 'localizepilot' ) ),
				array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
				array( 'key' => 'traffic', 'label' => __( 'Visitors', 'localizepilot' ) ),
				array( 'key' => 'provider', 'label' => __( 'Provider / status', 'localizepilot' ) ),
				array( 'key' => 'updated', 'label' => __( 'Updated', 'localizepilot' ) ),
				array( 'key' => 'size', 'label' => __( 'Size', 'localizepilot' ) ),
				array( 'key' => 'actions', 'label' => __( 'Actions', 'localizepilot' ), 'align' => 'right' ),
			),
			'rows'    => $lp_rows,
			'empty'   => array(
				'icon'    => 'nav-performance',
				'title'   => __( 'No cache history found', 'localizepilot' ),
				'message' => __( 'Open a translated page or save a Gutenberg translation to create generated HTML.', 'localizepilot' ),
			),
		)
	);

	$lp_pagination_url = add_query_arg( 'cache_type', (string) ( $lp_filters['type'] ?? 'all' ), $lp_base_url );
	Template::render(
		'parts/pagination',
		array(
			'total'    => (int) ( $lp_history['total'] ?? 0 ),
			'page'     => (int) ( $lp_history['page'] ?? 1 ),
			'per_page' => (int) ( $lp_history['per_page'] ?? 15 ),
			'base_url' => $lp_pagination_url,
		)
	);
	?>
</section>
