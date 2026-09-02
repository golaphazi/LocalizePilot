<?php
/**
 * SEO & URLs screen.
 *
 * Every figure here describes the running site: Router builds the language
 * URLs, Plugin::output_hreflang() prints the alternates, and the translation
 * records say whether each of those alternates resolves to current content.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data      = (array) ( $args['data'] ?? array() );
$lp_counts    = (array) ( $lp_data['counts'] ?? array() );
$lp_results   = (array) ( $lp_data['results'] ?? array() );
$lp_filters   = (array) ( $lp_data['filters'] ?? array() );
$lp_structure = (array) ( $lp_data['structure'] ?? array() );
$lp_items     = (array) ( $lp_results['items'] ?? array() );
$lp_open      = (int) ( $lp_data['open_drawer'] ?? 0 );
$lp_detail    = (array) ( $lp_data['open_detail'] ?? array() );
$lp_links     = (array) ( $lp_data['internal_links'] ?? array() );

$lp_hreflang_on = ! empty( $lp_counts['hreflang_on'] );
$lp_issues      = (int) ( $lp_counts['issues'] ?? 0 );
?>
<div class="lp-stack lp-stack--cards">
	<?php

Template::render(
	'parts/kpi-grid',
	array(
		'variant' => 'compact',
		'items'   => array(
			array(
				'label'     => __( 'Multilingual URLs', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['urls'] ?? 0 ) ),
				'note'      => sprintf(
					/* translators: %s is a number of languages. */
					_n( 'across %s language', 'across %s languages', (int) ( $lp_counts['languages'] ?? 0 ), 'localizepilot' ),
					number_format_i18n( (int) ( $lp_counts['languages'] ?? 0 ) )
				),
				'note_tone' => 'brand',
			),
			array(
				'label'     => __( 'Translated URLs', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['translated'] ?? 0 ) ),
				'note'      => sprintf(
					/* translators: %s is a number of pages. */
					_n( 'from %s source page', 'from %s source pages', (int) ( $lp_counts['sources'] ?? 0 ), 'localizepilot' ),
					number_format_i18n( (int) ( $lp_counts['sources'] ?? 0 ) )
				),
				'note_tone' => 'muted',
			),
			array(
				'label'     => __( 'Hreflang Status', 'localizepilot' ),
				'value'     => $lp_hreflang_on
					? ( 0 === $lp_issues ? __( 'Healthy', 'localizepilot' ) : __( 'Partial', 'localizepilot' ) )
					: __( 'Off', 'localizepilot' ),
				'note'      => $lp_hreflang_on
					? sprintf(
						/* translators: %s is a number of languages. */
						_n( '%s language published', '%s languages published', (int) ( $lp_counts['languages'] ?? 0 ), 'localizepilot' ),
						number_format_i18n( (int) ( $lp_counts['languages'] ?? 0 ) )
					)
					: __( 'no languages enabled', 'localizepilot' ),
				'note_tone' => $lp_hreflang_on && 0 === $lp_issues ? 'success' : 'warning',
			),
			array(
				'label'     => __( 'URL Issues', 'localizepilot' ),
				'value'     => number_format_i18n( $lp_issues ),
				'note'      => ( $lp_counts['stale'] ?? 0 ) > 0
					? sprintf(
						/* translators: %s is a number of URLs. */
						_n( '%s needs review', '%s need review', (int) $lp_counts['stale'], 'localizepilot' ),
						number_format_i18n( (int) $lp_counts['stale'] )
					)
					: __( 'awaiting translation', 'localizepilot' ),
				'note_tone' => $lp_issues > 0 ? 'warning' : 'success',
			),
		),
	)
);

/* -------------------------------------------------------------------------
 * Language URL structure
 * ---------------------------------------------------------------------- */

$lp_structure_body = '<div class="lp-url-pattern">'
	. '<span class="lp-url-pattern__label">' . esc_html__( 'Current URL structure', 'localizepilot' ) . '</span>'
	. '<code class="lp-url-pattern__code">' . esc_html( (string) ( $lp_structure['pattern'] ?? '' ) ) . '</code>'
	. '</div>';

$lp_structure_body .= '<div class="lp-cols lp-cols--2 lp-url-structure__columns">'
	. '<div><span class="lp-eyebrow">' . esc_html__( 'Source language', 'localizepilot' ) . '</span>'
	. Template::capture(
		'parts/url-map',
		array( 'rows' => array( ( (array) ( $lp_structure['source'] ?? array() ) ) + array( 'source' => true ) ) )
	)
	. '</div>'
	. '<div><span class="lp-eyebrow">' . esc_html__( 'Translated languages', 'localizepilot' ) . '</span>'
	. Template::capture( 'parts/url-map', array( 'rows' => (array) ( $lp_structure['translated'] ?? array() ) ) )
	. '</div></div>';

$lp_structure_body .= '<p class="lp-note">'
	. Template::icon( 'exchange', 'lp-icon lp-note__icon' )
	. '<span>' . esc_html__( 'Language switching keeps visitors on the equivalent page whenever a translation is available.', 'localizepilot' ) . '</span>'
	. '</p>';

Template::render(
	'parts/card',
	array(
		'title'    => __( 'Language URL Structure', 'localizepilot' ),
		'subtitle' => __( 'LocalizePilot keeps translated pages connected while using language-prefixed URLs.', 'localizepilot' ),
		'body'     => $lp_structure_body,
	)
);

/* -------------------------------------------------------------------------
 * URL management
 * ---------------------------------------------------------------------- */

/**
 * Build one table row from a repository record.
 *
 * @param array<string,mixed> $row Repository row.
 * @return array<string,string>
 */
$lp_base = (string) ( $lp_data['base_url'] ?? '' );

$lp_cell = static function ( array $row ) use ( $lp_base ): array {
	$content = '<span class="lp-cell__title">' . esc_html( (string) $row['title'] ) . '</span>'
		. '<span class="lp-cell__sub">' . esc_html( (string) $row['type'] ) . '</span>';

	$languages = '<span class="lp-cell__muted">&mdash;</span>';

	if ( ! empty( $row['languages'] ) ) {
		// Four chips is what the design fits before it counts the rest.
		$shown     = array_slice( (array) $row['languages'], 0, 4 );
		$remaining = count( (array) $row['languages'] ) - count( $shown );
		$languages = '<span class="lp-chip-row">';

		foreach ( $shown as $code ) {
			$languages .= Template::capture( 'parts/lang-chip', array( 'code' => (string) $code, 'tone' => 'target' ) );
		}

		if ( $remaining > 0 ) {
			$languages .= '<span class="lp-lang-chip lp-lang-chip--source">+' . esc_html( number_format_i18n( $remaining ) ) . '</span>';
		}

		$languages .= '</span>';
	}

	$updated = (int) $row['updated'];

	return array(
		'content'  => $content,
		'source'   => '<code class="lp-cell__path">' . esc_html( (string) $row['path'] ) . '</code>',
		'language' => $languages,
		'status'   => Template::capture(
			'parts/badge',
			array( 'label' => (string) $row['url_label'], 'tone' => (string) $row['url_status'] )
		),
		'hreflang' => Template::capture(
			'parts/badge',
			array( 'label' => (string) $row['hreflang_label'], 'tone' => (string) $row['hreflang_status'] )
		),
		'updated'  => '<span class="lp-cell__muted">' . esc_html(
			$updated > 0
				? sprintf(
					/* translators: %s is a human-readable time difference, e.g. "2 hours". */
					__( '%s ago', 'localizepilot' ),
					human_time_diff( $updated )
				)
				: __( 'Never', 'localizepilot' )
		) . '</span>',
		'actions'  => Template::capture(
			'parts/row-actions',
			array(
				'label'   => (string) $row['title'],
				'primary' => array(
					'label'  => ! empty( $row['stale'] ) ? __( 'Review', 'localizepilot' ) : __( 'View', 'localizepilot' ),
					'style'  => ! empty( $row['stale'] ) ? 'solid' : 'link',
					// A real link, so the drawer still opens with no JavaScript.
					'url'    => add_query_arg( 'view', (int) $row['id'], $lp_base ),
					'drawer' => (int) $row['id'],
				),
				'menu'    => array(
					array( 'label' => __( 'Edit content', 'localizepilot' ), 'url' => (string) $row['edit_url'] ),
					array( 'label' => __( 'View on site', 'localizepilot' ), 'url' => (string) $row['url'] ),
				),
			)
		),
	);
};

$lp_table = Template::capture(
	'parts/toolbar',
	array(
		'action'  => admin_url( 'admin.php' ),
		'hidden'  => array( 'page' => 'localizepilot-seo-urls' ),
		'search'  => array(
			'name'        => 's',
			'value'       => (string) ( $lp_filters['search'] ?? '' ),
			'placeholder' => __( 'Search content or URL…', 'localizepilot' ),
		),
		'filters' => array(
			array(
				'name'    => 'language',
				'label'   => __( 'Language', 'localizepilot' ),
				'value'   => (string) ( $lp_filters['language'] ?? '' ),
				'options' => (array) ( $lp_data['languages'] ?? array() ),
			),
			array(
				'name'    => 'status',
				'label'   => __( 'Status', 'localizepilot' ),
				'value'   => (string) ( $lp_filters['status'] ?? '' ),
				'options' => (array) ( $lp_data['statuses'] ?? array() ),
			),
			array(
				'name'    => 'type',
				'label'   => __( 'Content type', 'localizepilot' ),
				'value'   => (string) ( $lp_filters['type'] ?? '' ),
				'options' => (array) ( $lp_data['types'] ?? array() ),
			),
			array(
				'type'    => 'toggle',
				'name'    => 'issues',
				'label'   => __( 'Issues only', 'localizepilot' ),
				'icon'    => 'alert-triangle',
				'checked' => ! empty( $lp_filters['issues'] ),
			),
			array(
				'name'           => 'order',
				'label'          => __( 'Sort', 'localizepilot' ),
				'value'          => (string) ( $lp_filters['order'] ?? 'newest' ),
				'divider_before' => true,
				'options'        => array(
					'newest' => __( 'Recently Updated', 'localizepilot' ),
					'oldest' => __( 'Oldest first', 'localizepilot' ),
				),
			),
		),
	)
);

$lp_table .= Template::capture(
	'parts/table',
	array(
		'label'   => __( 'Multilingual URLs', 'localizepilot' ),
		'columns' => array(
			array( 'key' => 'content', 'label' => __( 'Content', 'localizepilot' ) ),
			array( 'key' => 'source', 'label' => __( 'Source URL', 'localizepilot' ) ),
			array( 'key' => 'language', 'label' => __( 'Languages', 'localizepilot' ) ),
			array( 'key' => 'status', 'label' => __( 'URL Status', 'localizepilot' ) ),
			array( 'key' => 'hreflang', 'label' => __( 'Hreflang', 'localizepilot' ) ),
			array( 'key' => 'updated', 'label' => __( 'Updated', 'localizepilot' ) ),
			array( 'key' => 'actions', 'label' => __( 'Action', 'localizepilot' ), 'align' => 'right' ),
		),
		'rows'    => array_map(
			static function ( array $row ) use ( $lp_cell ): array {
				return array(
					'id'    => (int) $row['id'],
					'label' => (string) $row['title'],
					'cells' => $lp_cell( $row ),
				);
			},
			$lp_items
		),
		'empty'   => array(
			'icon'    => 'nav-seo-urls',
			'title'   => ! empty( $lp_data['filtered'] )
				? __( 'No URLs match these filters', 'localizepilot' )
				: __( 'Nothing published yet', 'localizepilot' ),
			'message' => ! empty( $lp_data['filtered'] )
				? __( 'Clear the filters to see every published URL.', 'localizepilot' )
				: __( 'Published posts and pages appear here with their language versions.', 'localizepilot' ),
			'action'  => ! empty( $lp_data['filtered'] )
				? array( 'label' => __( 'Clear filters', 'localizepilot' ), 'url' => (string) ( $lp_data['base_url'] ?? '' ) )
				: array(),
		),
	)
);

if ( ! empty( $lp_items ) ) {
	$lp_table .= Template::capture(
		'parts/pagination',
		array(
			'total'    => (int) ( $lp_results['total'] ?? 0 ),
			'page'     => (int) ( $lp_results['page'] ?? 1 ),
			'per_page' => (int) ( $lp_results['per_page'] ?? 20 ),
			'base_url' => add_query_arg(
				array_filter(
					array(
						'language' => $lp_filters['language'] ?? '',
						'status'   => $lp_filters['status'] ?? '',
						'type'     => $lp_filters['type'] ?? '',
						's'        => $lp_filters['search'] ?? '',
						'issues'   => ! empty( $lp_filters['issues'] ) ? '1' : '',
					)
				),
				(string) ( $lp_data['base_url'] ?? '' )
			),
		)
	);
}

Template::render(
	'parts/card',
	array(
		'title'    => __( 'URL Management', 'localizepilot' ),
		'subtitle' => __( 'Review translated URLs and their language relationships.', 'localizepilot' ),
		'link'     => array(
			'label' => __( 'View all URLs', 'localizepilot' ),
			'url'   => (string) ( $lp_data['base_url'] ?? '' ),
		),
		'flush'    => true,
		'body'     => $lp_table,
	)
);

/* -------------------------------------------------------------------------
 * Attention and internal links
 * ---------------------------------------------------------------------- */
?>
<div class="lp-cols lp-cols--2">
	<?php
	Template::render(
		'parts/card',
		array(
			'title' => __( 'Needs Your Attention', 'localizepilot' ),
			'flush' => true,
			'body'  => Template::capture(
				'parts/task-list',
				array(
					'variant' => 'detail',
					'items'   => (array) ( $lp_data['attention'] ?? array() ),
				)
			),
		)
	);

	$lp_link_rows = array(
		array(
			'label' => __( 'Source', 'localizepilot' ),
			'value' => (string) ( $lp_links['source'] ?? '' ),
		),
	);

	if ( ! empty( $lp_links['language'] ) ) {
		$lp_link_rows[] = array(
			'label' => (string) $lp_links['language'],
			'value' => (string) ( $lp_links['translated'] ?? '' ),
			'badge' => array(
				'label' => __( 'Active', 'localizepilot' ),
				'tone'  => 'success',
			),
		);
	}

	Template::render(
		'parts/card',
		array(
			'class' => 'lp-linkcard',
			'body'  => '<div class="lp-linkcard__head">'
				. '<span class="lp-linkcard__icon">' . Template::icon( 'link', 'lp-icon' ) . '</span>'
				. '<div><h2 class="lp-card__title">' . esc_html__( 'Localized Internal Links', 'localizepilot' ) . '</h2>'
				. '<p class="lp-card__subtitle">' . esc_html__( 'Supported internal links keep visitors inside the language they are currently browsing.', 'localizepilot' ) . '</p></div>'
				. '</div>'
				. Template::capture( 'parts/signal-list', array( 'rows' => $lp_link_rows ) )
				. '<a class="lp-card__link" href="' . esc_url( (string) ( $lp_data['settings_url'] ?? '' ) ) . '">'
				. esc_html__( 'Manage in Settings', 'localizepilot' )
				. Template::icon( 'arrow-right-sm', 'lp-icon lp-card__link-arrow' ) . '</a>',
		)
	);
	?>
</div>

</div>

<?php
/*
 * The drawer host. A row action fetches its panel from Ajax::url_drawer() and
 * drops it in here; a deep link renders the same panel inline, already open.
 * Either way one partial produces it.
 */
?>
<div id="lp-drawer-host" data-lp-drawer-host>
	<?php
	if ( ! empty( $lp_detail ) ) {
		Template::render( 'parts/url-drawer', $lp_detail + array( 'open' => true ) );
	}
	?>
</div>
