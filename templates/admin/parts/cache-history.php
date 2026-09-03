<?php
/**
 * Generated cache history: type tabs, an optional toolbar, the table and its
 * pagination.
 *
 * Shared by Performance and Cache Management, which show the same list with
 * different chrome around it. Performance keeps its header action buttons;
 * Cache Management shows the design's entry count and filter toolbar.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $history  {items, total, pages, page, per_page}.
 *     @type array  $filters  {type, paged}.
 *     @type string $base_url Screen URL the tabs and pagination build on.
 *     @type string $title    Card heading.
 *     @type string $subtitle Line under the heading. Ignored when $count is true.
 *     @type string $eyebrow  Small label above the heading.
 *     @type bool   $count    Show the "N cached entries" pill from the design.
 *     @type bool   $toolbar  Show the search and filter toolbar.
 *     @type array  $languages Enabled languages for the filter, code => label.
 *     @type string $header   Extra markup for the card header, already escaped.
 * }
 */

use LocalizePilot\Admin\Template;
use LocalizePilot\Analytics;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Provider_Catalog;

defined( 'ABSPATH' ) || exit;

$lp_history  = (array) ( $args['history'] ?? array() );
$lp_filters  = (array) ( $args['filters'] ?? array() );
$lp_base_url = (string) ( $args['base_url'] ?? '' );
$lp_type     = (string) ( $lp_filters['type'] ?? 'all' );
$lp_total    = (int) ( $lp_history['total'] ?? 0 );
$lp_show_all = ! empty( $args['count'] );
?>
<section class="lp-card lp-card--flush lp-cache-history">
	<header class="lp-card__head">
		<div>
			<?php if ( ! empty( $args['eyebrow'] ) ) : ?>
				<span class="lp-card__eyebrow"><?php echo esc_html( (string) $args['eyebrow'] ); ?></span>
			<?php endif; ?>

			<h2 class="lp-card__title">
				<?php echo esc_html( (string) ( $args['title'] ?? __( 'Generated cache', 'localizepilot' ) ) ); ?>
			</h2>

			<p class="lp-card__subtitle">
				<?php echo esc_html( (string) ( $args['subtitle'] ?? __( 'Review and manage cached localized pages.', 'localizepilot' ) ) ); ?>
			</p>
		</div>

		<?php if ( $lp_show_all ) : ?>
			<span class="lp-pill">
				<?php
				printf(
					/* translators: %s: Number of cached entries. */
					esc_html( _n( '%s cached entry', '%s cached entries', $lp_total, 'localizepilot' ) ),
					esc_html( number_format_i18n( $lp_total ) )
				);
				?>
			</span>
		<?php endif; ?>

		<?php
		if ( ! empty( $args['header'] ) ) {
			echo wp_kses_post( (string) $args['header'] );
		}
		?>
	</header>

	<nav class="lp-subnav" aria-label="<?php esc_attr_e( 'Cache history type', 'localizepilot' ); ?>">
		<?php
		$lp_tabs = array(
			'all'      => __( 'All', 'localizepilot' ),
			'page'     => __( 'Rendered pages', 'localizepilot' ),
			'snapshot' => __( 'Gutenberg snapshots', 'localizepilot' ),
		);

		foreach ( $lp_tabs as $lp_tab => $lp_label ) :
			$lp_active = $lp_tab === $lp_type;
			?>
			<a
				class="lp-subnav__item<?php echo $lp_active ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( add_query_arg( 'cache_type', $lp_tab, $lp_base_url ) ); ?>"
				<?php echo $lp_active ? ' aria-current="page"' : ''; ?>
			><?php echo esc_html( $lp_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php
	if ( ! empty( $args['toolbar'] ) ) {
		Template::render(
			'parts/toolbar',
			array(
				'action'  => $lp_base_url,
				'hidden'  => array( 'page' => 'localizepilot-cache-management' ),
				'search'  => array(
					'name'        => 'cache_search',
					'value'       => '',
					'placeholder' => __( 'Search URL or cache entry…', 'localizepilot' ),
				),
				'filters' => array(
					array(
						'type'    => 'select',
						'name'    => 'cache_type',
						'label'   => __( 'All types', 'localizepilot' ),
						'value'   => $lp_type,
						'options' => array(
							'all'      => __( 'All types', 'localizepilot' ),
							'page'     => __( 'Rendered pages', 'localizepilot' ),
							'snapshot' => __( 'Gutenberg snapshots', 'localizepilot' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'cache_language',
						'label'   => __( 'All languages', 'localizepilot' ),
						'value'   => '',
						'options' => array( '' => __( 'All languages', 'localizepilot' ) ) + (array) ( $args['languages'] ?? array() ),
						'feature' => 'cache_filters',
					),
					array(
						'type'    => 'select',
						'name'    => 'cache_status',
						'label'   => __( 'All status', 'localizepilot' ),
						'value'   => '',
						'options' => array(
							''        => __( 'All status', 'localizepilot' ),
							'active'  => __( 'Active', 'localizepilot' ),
							'expired' => __( 'Expired', 'localizepilot' ),
						),
						'feature' => 'cache_filters',
					),
				),
			)
		);
	}

	$lp_rows = array();

	foreach ( (array) ( $lp_history['items'] ?? array() ) as $lp_item ) {
		$lp_key      = (string) ( $lp_item['key'] ?? '' );
		$lp_item_type = (string) ( $lp_item['type'] ?? 'page' );
		$lp_url      = (string) ( $lp_item['url'] ?? '' );
		$lp_language = sanitize_key( (string) ( $lp_item['language'] ?? '' ) );
		$lp_provider = sanitize_key( (string) ( $lp_item['provider'] ?? '' ) );
		$lp_modified = (int) ( $lp_item['modified'] ?? 0 );
		$lp_expired  = ! empty( $lp_item['expired'] );

		$lp_name = '' !== $lp_url ? Analytics::display_url( $lp_url ) : $lp_key . '.html';

		$lp_entry = '<span class="lp-cell__title">' . esc_html( $lp_name ) . '</span>'
			. '<span class="lp-cell__muted">' . esc_html(
				sprintf(
					/* translators: 1: Cache type label, 2: Truncated cache key. */
					__( '%1$s · %2$s', 'localizepilot' ),
					'snapshot' === $lp_item_type ? __( 'Snapshot', 'localizepilot' ) : __( 'Generated HTML', 'localizepilot' ),
					substr( $lp_key, 0, 8 )
				)
			) . '</span>';

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

		/*
		 * Three states are knowable from the files themselves. The design also
		 * shows Generating and Error, which need a job queue the plugin does
		 * not have, so they are not invented here.
		 */
		if ( 'snapshot' === $lp_item_type ) {
			$lp_status_cell = Template::capture( 'parts/badge', array( 'label' => __( 'Snapshot', 'localizepilot' ), 'tone' => 'violet' ) );
		} elseif ( $lp_expired ) {
			$lp_status_cell = Template::capture( 'parts/badge', array( 'label' => __( 'Expired', 'localizepilot' ), 'tone' => 'warning' ) );
		} else {
			$lp_status_cell = Template::capture( 'parts/badge', array( 'label' => __( 'Active', 'localizepilot' ), 'tone' => 'success' ) );
		}

		$lp_rows[] = array(
			'id'    => $lp_key,
			'label' => $lp_name,
			'class' => $lp_expired ? 'is-expired' : '',
			'cells' => array(
				'entry'    => '<div class="lp-cache-entry">' . $lp_entry . '</div>',
				'type'     => Template::capture(
					'parts/badge',
					array(
						'label' => 'snapshot' === $lp_item_type ? __( 'Post', 'localizepilot' ) : __( 'Page', 'localizepilot' ),
						'tone'  => 'neutral',
					)
				),
				'language' => $lp_language_cell,
				'traffic'  => '<span class="lp-cell__title">' . esc_html( number_format_i18n( (int) ( $lp_item['visitors'] ?? 0 ) ) ) . '</span>',
				'status'   => $lp_status_cell,
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
			'label'   => __( 'Generated cache history', 'localizepilot' ),
			'columns' => array(
				array( 'key' => 'entry', 'label' => __( 'Cache entry', 'localizepilot' ), 'width' => '32%' ),
				array( 'key' => 'type', 'label' => __( 'Type', 'localizepilot' ) ),
				array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
				array( 'key' => 'traffic', 'label' => __( 'Visitors', 'localizepilot' ) ),
				array( 'key' => 'status', 'label' => __( 'Status', 'localizepilot' ) ),
				array( 'key' => 'updated', 'label' => __( 'Updated', 'localizepilot' ) ),
				array( 'key' => 'size', 'label' => __( 'Size', 'localizepilot' ) ),
				array( 'key' => 'actions', 'label' => __( 'Action', 'localizepilot' ), 'align' => 'right' ),
			),
			'rows'    => $lp_rows,
			'empty'   => array(
				'icon'    => 'nav-cache-management',
				'title'   => __( 'No cache history found', 'localizepilot' ),
				'message' => __( 'Open a translated page or save a Gutenberg translation to create generated HTML.', 'localizepilot' ),
			),
		)
	);

	Template::render(
		'parts/pagination',
		array(
			'total'    => $lp_total,
			'page'     => (int) ( $lp_history['page'] ?? 1 ),
			'per_page' => (int) ( $lp_history['per_page'] ?? 15 ),
			'base_url' => add_query_arg( 'cache_type', $lp_type, $lp_base_url ),
		)
	);
	?>
</section>
