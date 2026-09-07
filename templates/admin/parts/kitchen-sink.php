<?php
/**
 * Component gallery — every console component in every state.
 *
 * Reachable on any console screen by adding &lp_kitchen_sink=1 to the URL. It
 * exists so the component library can be compared against the Figma artboards
 * side by side, and so a change to one component's CSS shows up everywhere it
 * is used before a screen depends on it.
 *
 * Sample content only. Nothing here reads or writes real data.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Paywall;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Render one labelled section of the gallery.
 *
 * @param string $title Section name.
 * @param string $body  Rendered HTML.
 */
$lp_section = static function ( string $title, string $body ): void {
	echo '<section class="lp-sink__section"><h2 class="lp-sink__title">' . esc_html( $title ) . '</h2>';
	echo '<div class="lp-sink__stage">' . $body . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered partial output.
};

$lp_statuses = array(
	'reviewed'     => __( 'Reviewed', 'localizepilot' ),
	'edited'       => __( 'Edited', 'localizepilot' ),
	'needs_update' => __( 'Needs Update', 'localizepilot' ),
	'automatic'    => __( 'Automatic', 'localizepilot' ),
	'neutral'      => __( 'Neutral', 'localizepilot' ),
);

/**
 * Build one table row's cells from the smaller partials, exactly the way a
 * real screen will.
 *
 * @param array<string,mixed> $row Sample row.
 * @return array<string,string>
 */
$lp_cells = static function ( array $row ): array {
	$title = '<span class="lp-cell__title">' . esc_html( $row['title'] ) . '</span>';

	if ( ! empty( $row['note'] ) ) {
		$title .= '<span class="lp-cell__note">' . esc_html( $row['note'] ) . '</span>';
	}

	$languages = '<span class="lp-cell__pair">'
		. Template::capture( 'parts/lang-chip', array( 'code' => 'en' ) )
		. Template::icon( 'arrow-right-sm', 'lp-icon lp-cell__arrow' )
		. Template::capture( 'parts/lang-chip', array( 'code' => $row['language'], 'tone' => 'target' ) )
		. '</span>';

	return array(
		'content'  => $title,
		'type'     => '<span class="lp-cell__muted">' . esc_html__( 'Page', 'localizepilot' ) . '</span>',
		'language' => $languages,
		'progress' => Template::capture( 'parts/progress', array( 'value' => $row['progress'], 'label' => true ) ),
		'status'   => Template::capture( 'parts/badge', array( 'label' => $row['status_label'], 'tone' => $row['status'] ) ),
		'updated'  => '<span class="lp-cell__muted">' . esc_html( $row['updated'] ) . '</span>',
		'actions'  => Template::capture(
			'parts/row-actions',
			array(
				'label'   => $row['title'],
				'primary' => array(
					'label' => 'needs_update' === $row['status'] ? __( 'Update', 'localizepilot' ) : __( 'View', 'localizepilot' ),
					'style' => 'needs_update' === $row['status'] ? 'solid' : 'link',
					'url'   => '#',
				),
				'menu'    => array(
					array( 'label' => __( 'Edit translation', 'localizepilot' ), 'url' => '#' ),
					array( 'label' => __( 'Refresh from source', 'localizepilot' ), 'url' => '#' ),
					array( 'label' => __( 'Delete', 'localizepilot' ), 'url' => '#', 'destructive' => true ),
				),
			)
		),
	);
};

$lp_rows = array(
	array( 'title' => 'Homepage', 'language' => 'de', 'progress' => 100, 'status' => 'reviewed', 'status_label' => __( 'Reviewed', 'localizepilot' ), 'updated' => '2 min ago' ),
	array( 'title' => 'About', 'language' => 'fr', 'progress' => 100, 'status' => 'edited', 'status_label' => __( 'Edited', 'localizepilot' ), 'updated' => '14 min ago' ),
	array( 'title' => 'Pricing', 'language' => 'es', 'progress' => 86, 'status' => 'needs_update', 'status_label' => __( 'Needs Update', 'localizepilot' ), 'updated' => '1 hour ago', 'note' => __( 'Source content changed 1 hour ago.', 'localizepilot' ) ),
	array( 'title' => 'Careers', 'language' => 'fr', 'progress' => 92, 'status' => 'automatic', 'status_label' => __( 'Automatic', 'localizepilot' ), 'updated' => '5 hours ago' ),
);

$lp_columns = array(
	array( 'key' => 'content', 'label' => __( 'Content', 'localizepilot' ) ),
	array( 'key' => 'type', 'label' => __( 'Type', 'localizepilot' ) ),
	array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
	array( 'key' => 'progress', 'label' => __( 'Progress', 'localizepilot' ) ),
	array( 'key' => 'status', 'label' => __( 'Status', 'localizepilot' ) ),
	array( 'key' => 'updated', 'label' => __( 'Updated', 'localizepilot' ) ),
	array( 'key' => 'actions', 'label' => __( 'Action', 'localizepilot' ), 'align' => 'right' ),
);
?>
<div class="lp-sink">
	<p class="lp-sink__lede">
		<?php esc_html_e( 'Every console component in every state, built from sample content. Compare against the Figma artboards at 1440px.', 'localizepilot' ); ?>
	</p>

	<?php
	$lp_section(
		__( 'KPI cards — Overview variant', 'localizepilot' ),
		Template::capture(
			'parts/kpi-grid',
			array(
				'items' => array(
					array( 'label' => __( 'Languages', 'localizepilot' ), 'value' => '7', 'delta' => __( '+2 this month', 'localizepilot' ), 'delta_tone' => 'up', 'note' => __( '5 translated languages', 'localizepilot' ) ),
					array( 'label' => __( 'Translations', 'localizepilot' ), 'value' => '2,543', 'note' => __( '86% of content localized', 'localizepilot' ) ),
					array( 'label' => __( 'Needs Review', 'localizepilot' ), 'value' => '218', 'note' => __( '24 require attention today', 'localizepilot' ), 'note_tone' => 'warning' ),
					array( 'label' => __( 'Translation Usage', 'localizepilot' ), 'value' => '71%', 'progress' => 71, 'note' => __( '1,420 / 2,000 used', 'localizepilot' ) ),
				),
			)
		)
	);

	$lp_section(
		__( 'KPI cards — compact variant', 'localizepilot' ),
		Template::capture(
			'parts/kpi-grid',
			array(
				'variant' => 'compact',
				'items'   => array(
					array( 'label' => __( 'Translations', 'localizepilot' ), 'value' => '2,543', 'note' => __( '86% localized', 'localizepilot' ), 'note_tone' => 'brand' ),
					array( 'label' => __( 'Needs Review', 'localizepilot' ), 'value' => '218', 'note' => __( '24 today', 'localizepilot' ), 'note_tone' => 'warning' ),
					array( 'label' => __( 'Needs Update', 'localizepilot' ), 'value' => '32', 'note' => __( '8 recently changed', 'localizepilot' ), 'note_tone' => 'warning' ),
					array( 'label' => __( 'Languages', 'localizepilot' ), 'value' => '7', 'note' => __( '5 translated', 'localizepilot' ), 'note_tone' => 'muted' ),
				),
			)
		)
	);

	$lp_section(
		__( 'Status badges', 'localizepilot' ),
		'<div class="lp-sink__row">' . implode(
			'',
			array_map(
				static function ( string $tone, string $label ): string {
					return Template::capture( 'parts/badge', array( 'tone' => $tone, 'label' => $label ) );
				},
				array_keys( $lp_statuses ),
				array_values( $lp_statuses )
			)
		) . '</div>'
	);

	$lp_section(
		__( 'Language chips and progress', 'localizepilot' ),
		'<div class="lp-sink__row">'
			. Template::capture( 'parts/lang-chip', array( 'code' => 'en', 'title' => 'English' ) )
			. Template::icon( 'arrow-right-sm', 'lp-icon lp-cell__arrow' )
			. Template::capture( 'parts/lang-chip', array( 'code' => 'de', 'tone' => 'target', 'title' => 'German' ) )
			. Template::capture( 'parts/progress', array( 'value' => 100, 'label' => true ) )
			. Template::capture( 'parts/progress', array( 'value' => 67, 'label' => true ) )
			. Template::capture( 'parts/progress', array( 'value' => 31, 'label' => true, 'tone' => 'warning' ) )
		. '</div>'
	);

	$lp_section(
		__( 'Buttons', 'localizepilot' ),
		'<div class="lp-sink__row">'
			. '<a class="lp-btn lp-btn--ghost" href="#"><span>' . esc_html__( 'View Site', 'localizepilot' ) . '</span>' . Template::icon( 'external-link' ) . '</a>'
			. '<button type="button" class="lp-btn lp-btn--primary"><span>' . esc_html__( 'Translate Content', 'localizepilot' ) . '</span>' . Template::icon( 'arrow-right' ) . '</button>'
			. '<a class="lp-row-action" href="#">' . esc_html__( 'View', 'localizepilot' ) . '</a>'
			. '<a class="lp-row-action lp-row-action--solid" href="#">' . esc_html__( 'Update', 'localizepilot' ) . '</a>'
		. '</div>'
	);

	$lp_section(
		__( 'Table, toolbar, bulk bar and pagination', 'localizepilot' ),
		Template::capture(
			'parts/card',
			array(
				'flush' => true,
				'body'  =>
					Template::capture(
						'parts/toolbar',
						array(
							'action'  => '',
							'search'  => array( 'name' => 's', 'placeholder' => __( 'Search content, URL, or translation…', 'localizepilot' ) ),
							'filters' => array(
								array( 'name' => 'lang', 'label' => __( 'Language', 'localizepilot' ), 'options' => array( '' => __( 'All Languages', 'localizepilot' ), 'de' => 'German' ) ),
								array( 'name' => 'status', 'label' => __( 'Status', 'localizepilot' ), 'options' => array( '' => __( 'All Status', 'localizepilot' ), 'reviewed' => __( 'Reviewed', 'localizepilot' ) ) ),
								array( 'name' => 'type', 'label' => __( 'Type', 'localizepilot' ), 'value' => 'page', 'options' => array( '' => __( 'All Types', 'localizepilot' ), 'page' => __( 'Page', 'localizepilot' ) ) ),
								array( 'name' => 'order', 'label' => __( 'Sort', 'localizepilot' ), 'value' => 'old', 'divider_before' => true, 'options' => array( '' => __( 'Newest first', 'localizepilot' ), 'old' => __( 'Oldest first', 'localizepilot' ) ) ),
							),
						)
					)
					. Template::capture(
						'parts/bulk-bar',
						array(
							'actions' => array(
								array( 'action' => 'review', 'label' => __( 'Review', 'localizepilot' ) ),
								array( 'action' => 'update', 'label' => __( 'Update', 'localizepilot' ) ),
								array( 'action' => 'more', 'label' => __( 'More', 'localizepilot' ) ),
							),
						)
					)
					. Template::capture(
						'parts/table',
						array(
							'label'      => __( 'Sample translations', 'localizepilot' ),
							'selectable' => true,
							'columns'    => $lp_columns,
							'rows'       => array_map(
								static function ( array $row ) use ( $lp_cells ): array {
									return array(
										'id'    => sanitize_title( $row['title'] ),
										'label' => $row['title'],
										'cells' => $lp_cells( $row ),
									);
								},
								$lp_rows
							),
						)
					)
					. Template::capture(
						'parts/pagination',
						array( 'total' => 2543, 'page' => 1, 'per_page' => 20, 'base_url' => '' )
					),
			)
		)
	);

	$lp_section(
		__( 'Empty state', 'localizepilot' ),
		Template::capture(
			'parts/card',
			array(
				'flush' => true,
				'body'  => Template::capture(
					'parts/empty-state',
					array(
						'icon'    => 'nav-translations',
						'title'   => __( 'No translations yet', 'localizepilot' ),
						'message' => __( 'Once a page is translated it appears here with its status and language coverage.', 'localizepilot' ),
						'action'  => array( 'label' => __( 'Translate Content', 'localizepilot' ), 'url' => '#' ),
					)
				),
			)
		)
	);

	$lp_section(
		__( 'Cards, task list and charts', 'localizepilot' ),
		'<div class="lp-cols lp-cols--wide-narrow">'
			. Template::capture(
				'parts/card',
				array(
					'title'    => __( 'Language Performance', 'localizepilot' ),
					'subtitle' => __( 'Understand how visitors interact with each language version.', 'localizepilot' ),
					'link'     => array( 'label' => __( 'View all', 'localizepilot' ), 'url' => '#' ),
					'body'     => Template::capture(
						'parts/line-chart',
						array(
							'id'     => 'sink-chart',
							'labels' => array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ),
							'series' => array(
								array( 'label' => __( 'Page views', 'localizepilot' ), 'tone' => 'brand', 'area' => true, 'points' => array( 420, 460, 505, 495, 560, 610, 690 ) ),
								array( 'label' => __( 'Unique visitors', 'localizepilot' ), 'tone' => 'ink', 'points' => array( 300, 320, 345, 360, 390, 430, 470 ) ),
							),
						)
					),
				)
			)
			. Template::capture(
				'parts/card',
				array(
					'title'    => __( 'Cache & Performance', 'localizepilot' ),
					'subtitle' => __( 'Monitor localized page caching and translation rendering.', 'localizepilot' ),
					'body'     => Template::capture( 'parts/donut', array( 'value' => 98, 'caption' => __( 'Hit Rate', 'localizepilot' ) ) ),
				)
			)
		. '</div>'
	);

	$lp_section(
		__( 'Task list', 'localizepilot' ),
		Template::capture(
			'parts/card',
			array(
				'title' => __( 'Needs Your Attention', 'localizepilot' ),
				'body'  => Template::capture(
					'parts/task-list',
					array(
						'items' => array(
							array( 'icon' => 'nav-translations', 'tone' => 'warning', 'title' => __( '24 translations need review', 'localizepilot' ), 'url' => '#' ),
							array( 'icon' => 'nav-performance', 'tone' => 'brand', 'title' => __( '8 pages need updates', 'localizepilot' ), 'url' => '#' ),
							array( 'count' => '12', 'tone' => 'success', 'title' => __( 'Localized pages have no recent activity', 'localizepilot' ), 'meta' => __( 'Review language coverage', 'localizepilot' ), 'action' => __( 'Review', 'localizepilot' ), 'url' => '#' ),
						),
					)
				),
			)
		)
		. Template::capture(
			'parts/card',
			array(
				'title' => __( 'Needs Your Attention — detail variant', 'localizepilot' ),
				'flush' => true,
				'body'  => Template::capture(
					'parts/task-list',
					array(
						'variant' => 'detail',
						'items'   => array(
							array( 'icon' => 'clock', 'tone' => 'warning', 'title' => __( '8 translated URLs need review', 'localizepilot' ), 'meta' => __( 'Source content changed recently.', 'localizepilot' ), 'action' => __( 'Review', 'localizepilot' ), 'url' => '#' ),
							array( 'icon' => 'alert-triangle', 'tone' => 'warning', 'title' => __( '3 language mappings need attention', 'localizepilot' ), 'meta' => __( 'Some translated pages are not connected.', 'localizepilot' ), 'action' => __( 'Review', 'localizepilot' ), 'url' => '#' ),
							array( 'icon' => 'link', 'tone' => 'danger', 'title' => __( '1 hreflang relationship needs attention', 'localizepilot' ), 'meta' => __( 'Check the language mapping for this page.', 'localizepilot' ), 'action' => __( 'Review', 'localizepilot' ), 'url' => '#' ),
						),
					)
				),
			)
		)
	);

	$lp_section(
		__( 'Form controls', 'localizepilot' ),
		'<div class="lp-cols lp-cols--2">'
			. Template::capture(
				'parts/card',
				array(
					'body' =>
						Template::capture( 'parts/field', array( 'name' => 'demo_text', 'label' => __( 'Source URL', 'localizepilot' ), 'value' => 'https://example.com/pricing/' ) )
						. Template::capture( 'parts/field', array( 'type' => 'select', 'name' => 'demo_select', 'label' => __( 'Translation style', 'localizepilot' ), 'value' => 'natural', 'options' => array( 'faithful' => __( 'Faithful', 'localizepilot' ), 'natural' => __( 'Natural', 'localizepilot' ) ), 'hint' => __( 'Applies to every AI provider.', 'localizepilot' ) ) )
						. Template::capture( 'parts/field', array( 'type' => 'code', 'name' => 'demo_code', 'label' => __( 'Canonical URL', 'localizepilot' ), 'value' => 'https://example.com/de/pricing/' ) ),
				)
			)
			. Template::capture(
				'parts/card',
				array(
					'body' =>
						Template::capture( 'parts/toggle', array( 'name' => 'demo_toggle_a', 'label' => __( 'Enable translation', 'localizepilot' ), 'description' => __( 'Serve translated pages to visitors.', 'localizepilot' ), 'checked' => true ) )
						. Template::capture( 'parts/toggle', array( 'name' => 'demo_toggle_b', 'label' => __( 'Translate attributes', 'localizepilot' ), 'description' => __( 'Include alt text and titles.', 'localizepilot' ) ) )
						. Template::capture( 'parts/toggle', array( 'name' => 'demo_toggle_c', 'label' => __( 'Media localization', 'localizepilot' ), 'description' => __( 'Gated behind Preview, so it renders but does nothing.', 'localizepilot' ), 'feature' => 'media_localize' ) ),
				)
			)
		. '</div>'
	);

	$lp_section(
		__( 'Paywall — locked control and full-screen upsell', 'localizepilot' ),
		'<p class="lp-sink__note">'
			. esc_html__( 'Activating either locked control opens the shared paywall modal. The catalogue entry behind them exists only in this gallery — nothing is being sold yet.', 'localizepilot' )
			. '</p>'
			. '<p class="lp-toolbar__filters">'
			. '<span class="lp-filter"' . Paywall::attributes( '__gallery' ) . '>'
			. '<select class="lp-filter__select" disabled><option>' . esc_html__( 'All Languages', 'localizepilot' ) . '</option></select>'
			. Template::icon( 'chevron-down', 'lp-icon lp-filter__chevron' )
			. '</span>'
			/*
			 * A button can stay enabled and carry the marker itself, so it
			 * needs neither the role nor the tabindex a wrapper does.
			 */
			. '<button type="button" class="lp-btn lp-btn--ghost"' . Paywall::attributes( '__gallery', false ) . '>'
			. esc_html__( 'Export report', 'localizepilot' )
			. '</button>'
			. '</p>'
			. Template::capture(
				'parts/upsell',
				array(
					'title'   => __( 'Sample locked screen', 'localizepilot' ),
					'promise' => __( 'What a screen shows when the whole thing belongs to the add-on, rather than one control on it.', 'localizepilot' ),
					'points'  => array(
						__( 'Says what the screen would do', 'localizepilot' ),
						__( 'Never renders controls that cannot be operated', 'localizepilot' ),
						__( 'One button, going one place', 'localizepilot' ),
					),
				)
			)
	);

	$lp_section(
		__( 'URL pattern bar', 'localizepilot' ),
		'<div class="lp-url-pattern"><span class="lp-url-pattern__label">'
			. esc_html__( 'Current URL structure', 'localizepilot' )
			. '</span><code class="lp-url-pattern__code">/{language}/{path}/</code></div>'
	);

	$lp_section(
		__( 'Language URL map', 'localizepilot' ),
		'<div class="lp-sink__row">'
			. '<div><span class="lp-eyebrow">' . esc_html__( 'Source language', 'localizepilot' ) . '</span>'
			. Template::capture(
				'parts/url-map',
				array(
					'rows' => array(
						array( 'code' => 'en', 'name' => 'English', 'url' => 'https://example.com/about/', 'source' => true ),
						array( 'code' => 'de', 'name' => 'German', 'url' => 'https://example.com/de/about/' ),
						array( 'code' => 'fr', 'name' => 'French', 'url' => 'https://example.com/fr/about/' ),
					),
				)
			)
			. '</div>'
			. Template::capture(
				'parts/url-map',
				array(
					'variant'  => 'stacked',
					'external' => true,
					'rows'     => array(
						array( 'code' => 'de', 'name' => 'German', 'url' => 'https://example.com/de/about/', 'tone' => 'reviewed', 'label' => __( 'Healthy', 'localizepilot' ) ),
						array( 'code' => 'es', 'name' => 'Spanish', 'url' => 'https://example.com/es/about/', 'tone' => 'needs_update', 'label' => __( 'Needs Review', 'localizepilot' ) ),
					),
				)
			)
		. '</div>'
	);

	$lp_section(
		__( 'Signal rows and note', 'localizepilot' ),
		Template::capture(
			'parts/signal-list',
			array(
				'rows' => array(
					array( 'label' => 'EN', 'value' => 'https://example.com/about/' ),
					array( 'label' => 'x-default', 'value' => 'https://example.com/about/' ),
					array( 'label' => __( 'German', 'localizepilot' ), 'value' => '/de/about/', 'badge' => array( 'label' => __( 'Active', 'localizepilot' ), 'tone' => 'success' ) ),
				),
			)
		)
		. '<p class="lp-note">' . Template::icon( 'exchange', 'lp-icon lp-note__icon' )
		. '<span>' . esc_html__( 'Language switching keeps visitors on the equivalent page whenever a translation is available.', 'localizepilot' ) . '</span></p>'
	);

	$lp_section(
		__( 'Internal links card', 'localizepilot' ),
		'<div class="lp-linkcard__head">'
			. '<span class="lp-linkcard__icon">' . Template::icon( 'link', 'lp-icon' ) . '</span>'
			. '<div><h2 class="lp-card__title">' . esc_html__( 'Localized Internal Links', 'localizepilot' ) . '</h2>'
			. '<p class="lp-card__subtitle">' . esc_html__( 'Supported internal links keep visitors inside the language they are currently browsing.', 'localizepilot' ) . '</p></div>'
		. '</div>'
	);

	$lp_section(
		__( 'Semantic badges', 'localizepilot' ),
		'<div class="lp-sink__row">'
			. Template::capture( 'parts/badge', array( 'label' => __( 'Healthy', 'localizepilot' ), 'tone' => 'success' ) )
			. Template::capture( 'parts/badge', array( 'label' => __( 'Warning', 'localizepilot' ), 'tone' => 'warning' ) )
			. Template::capture( 'parts/badge', array( 'label' => __( 'Inherited', 'localizepilot' ), 'tone' => 'info' ) )
			. Template::capture( 'parts/badge', array( 'label' => __( 'Custom', 'localizepilot' ), 'tone' => 'violet' ) )
			. Template::capture( 'parts/badge', array( 'label' => __( 'Broken', 'localizepilot' ), 'tone' => 'danger' ) )
		. '</div>'
	);

	$lp_section(
		__( 'Media cell and chip toggle', 'localizepilot' ),
		'<div class="lp-sink__row">'
			. '<span class="lp-media-cell"><span class="lp-media-cell__thumb">' . Template::icon( 'nav-media', 'lp-icon' ) . '</span>'
			. '<span class="lp-media-cell__text"><span class="lp-cell__title">Homepage Hero</span>'
			. '<span class="lp-cell__sub">homepage-hero.webp</span></span></span>'
			. '<label class="lp-chip-toggle"><input type="checkbox" class="screen-reader-text">'
			. Template::icon( 'alert-triangle', 'lp-icon' ) . '<span>' . esc_html__( 'Issues only', 'localizepilot' ) . '</span></label>'
			. '<label class="lp-chip-toggle is-active"><input type="checkbox" class="screen-reader-text" checked>'
			. Template::icon( 'alert-triangle', 'lp-icon' ) . '<span>' . esc_html__( 'Issues only', 'localizepilot' ) . '</span></label>'
		. '</div>'
	);

	$lp_section(
		__( 'Drawer', 'localizepilot' ),
		'<div class="lp-sink__row">'
			. '<button type="button" class="lp-btn lp-btn--ghost" data-lp-drawer-open="lp-sink-drawer"><span>' . esc_html__( 'Open URL Details', 'localizepilot' ) . '</span></button>'
			. '<button type="button" class="lp-btn lp-btn--ghost" data-lp-drawer-open="lp-sink-drawer-warning"><span>' . esc_html__( 'Open Issue Details', 'localizepilot' ) . '</span></button>'
		. '</div>'
	);
	?>
</div>

<?php
$lp_sample_detail = array(
	'id'           => 0,
	'title'        => 'Pricing',
	'type'         => __( 'Page', 'localizepilot' ),
	'source_name'  => 'English',
	'url'          => 'https://example.com/pricing/',
	'path'         => '/pricing/',
	'canonical'    => 'https://example.com/es/pricing/',
	'signal_tone'  => 'needs_update',
	'signal_label' => __( 'Warning', 'localizepilot' ),
	'languages'    => array(
		array( 'code' => 'en', 'name' => 'English', 'url' => 'https://example.com/pricing/', 'tone' => 'reviewed', 'label' => __( 'Healthy', 'localizepilot' ), 'source' => true ),
		array( 'code' => 'de', 'name' => 'German', 'url' => 'https://example.com/de/pricing/', 'tone' => 'reviewed', 'label' => __( 'Healthy', 'localizepilot' ) ),
		array( 'code' => 'fr', 'name' => 'French', 'url' => 'https://example.com/fr/pricing/', 'tone' => 'reviewed', 'label' => __( 'Healthy', 'localizepilot' ) ),
		array( 'code' => 'es', 'name' => 'Spanish', 'url' => 'https://example.com/es/pricing/', 'tone' => 'needs_update', 'label' => __( 'Needs Review', 'localizepilot' ) ),
	),
	'signals'      => array(
		array( 'code' => 'EN', 'url' => 'https://example.com/pricing/' ),
		array( 'code' => 'DE', 'url' => 'https://example.com/de/pricing/' ),
		array( 'code' => 'FR', 'url' => 'https://example.com/fr/pricing/' ),
		array( 'code' => 'ES', 'url' => 'https://example.com/es/pricing/' ),
		array( 'code' => 'x-default', 'url' => 'https://example.com/pricing/' ),
	),
);

$lp_drawer_body = Template::capture( 'parts/url-detail', $lp_sample_detail );

Template::render(
	'parts/drawer',
	array(
		'id'      => 'lp-sink-drawer',
		'title'   => __( 'URL Details', 'localizepilot' ),
		'body'    => $lp_drawer_body,
		'actions' => array(
			array( 'label' => __( 'View Source', 'localizepilot' ), 'icon' => 'external-link' ),
			array( 'label' => __( 'Save Changes', 'localizepilot' ), 'style' => 'primary' ),
		),
	)
);

Template::render(
	'parts/drawer',
	array(
		'id'      => 'lp-sink-drawer-warning',
		'title'   => __( 'Issue Details', 'localizepilot' ),
		'banner'  => array(
			'tone'    => 'warning',
			'title'   => __( 'Spanish URL needs review', 'localizepilot' ),
			'message' => __( 'The source content changed after the Spanish translation was created.', 'localizepilot' ),
		),
		'body'    => $lp_drawer_body,
		'actions' => array(
			array( 'label' => __( 'View Source', 'localizepilot' ), 'icon' => 'external-link' ),
			array( 'label' => __( 'Review Translation', 'localizepilot' ), 'style' => 'primary' ),
		),
	)
);
