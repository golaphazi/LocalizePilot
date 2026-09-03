<?php
/**
 * Translations screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data    = (array) ( $args['data'] ?? array() );
$lp_counts  = (array) ( $lp_data['counts'] ?? array() );
$lp_results = (array) ( $lp_data['results'] ?? array() );
$lp_filters = (array) ( $lp_data['filters'] ?? array() );
$lp_items   = (array) ( $lp_results['items'] ?? array() );

Template::render(
	'parts/kpi-grid',
	array(
		'variant' => 'compact',
		'items'   => array(
			array(
				'label'     => __( 'Translations', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['total'] ?? 0 ) ),
				'note'      => sprintf(
					/* translators: %s is a percentage. */
					__( '%s%% localized', 'localizepilot' ),
					number_format_i18n( (int) ( $lp_data['share'] ?? 0 ) )
				),
				'note_tone' => 'brand',
			),
			array(
				'label'     => __( 'Needs Review', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['automatic'] ?? 0 ) ),
				'note'      => __( 'not yet reviewed', 'localizepilot' ),
				'note_tone' => ( $lp_counts['automatic'] ?? 0 ) > 0 ? 'warning' : 'muted',
			),
			array(
				'label'     => __( 'Needs Update', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['needs_update'] ?? 0 ) ),
				'note'      => __( 'source changed', 'localizepilot' ),
				'note_tone' => ( $lp_counts['needs_update'] ?? 0 ) > 0 ? 'warning' : 'muted',
			),
			array(
				'label'     => __( 'Languages', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_data['enabled'] ?? 0 ) ),
				'note'      => __( 'enabled', 'localizepilot' ),
				'note_tone' => 'muted',
			),
		),
	)
);

/**
 * Build one table row from a repository record.
 *
 * @param array<string,mixed> $row Repository row.
 * @return array<string,string>
 */
$lp_cell = static function ( array $row ): array {
	$title = '<span class="lp-cell__title">' . esc_html( (string) $row['title'] ) . '</span>';

	if ( ! empty( $row['stale'] ) ) {
		$title .= '<span class="lp-cell__note">' . esc_html__( 'Source content changed after this translation.', 'localizepilot' ) . '</span>';
	}

	$languages = '<span class="lp-cell__pair">'
		. Template::capture( 'parts/lang-chip', array( 'code' => $row['source_code'] ) )
		. Template::icon( 'arrow-right-sm', 'lp-icon lp-cell__arrow' )
		. Template::capture(
			'parts/lang-chip',
			array(
				'code'  => $row['language'],
				'tone'  => 'target',
				'title' => $row['language_name'],
			)
		)
		. '</span>';

	$updated = (int) $row['updated'];

	return array(
		'content'  => $title,
		'type'     => '<span class="lp-cell__muted">' . esc_html( (string) $row['type'] ) . '</span>',
		'language' => $languages,
		'progress' => Template::capture( 'parts/progress', array( 'value' => (int) $row['progress'], 'label' => true ) ),
		'status'   => Template::capture( 'parts/badge', array( 'label' => (string) $row['status_label'], 'tone' => (string) $row['status'] ) ),
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
					'label' => ! empty( $row['stale'] ) ? __( 'Update', 'localizepilot' ) : __( 'View', 'localizepilot' ),
					'style' => ! empty( $row['stale'] ) ? 'solid' : 'link',
					'url'   => ! empty( $row['stale'] ) ? (string) $row['edit_url'] : (string) $row['view_url'],
					'external' => empty( $row['stale'] ),
				),
				'menu'    => array(
					array( 'label' => __( 'Edit translation', 'localizepilot' ), 'url' => (string) $row['edit_url'] ),
					array( 'label' => __( 'View on site', 'localizepilot' ), 'url' => (string) $row['view_url'], 'external' => true ),
				),
			)
		),
	);
};

$lp_table = Template::capture(
	'parts/toolbar',
	array(
		'action'  => admin_url( 'admin.php' ),
		'hidden'  => array( 'page' => 'localizepilot-translations' ),
		'search'  => array(
			'name'        => 's',
			'value'       => (string) ( $lp_filters['search'] ?? '' ),
			'placeholder' => __( 'Search content, URL, or translation…', 'localizepilot' ),
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
				'options' => array( '' => __( 'All Status', 'localizepilot' ) ) + (array) ( $lp_data['statuses'] ?? array() ),
			),
			array(
				'name'           => 'order',
				'label'          => __( 'Sort', 'localizepilot' ),
				'value'          => (string) ( $lp_filters['order'] ?? 'newest' ),
				'divider_before' => true,
				'options'        => array(
					'newest' => __( 'Newest first', 'localizepilot' ),
					'oldest' => __( 'Oldest first', 'localizepilot' ),
				),
			),
		),
	)
);

$lp_table .= Template::capture(
	'parts/bulk-bar',
	array(
		'feature' => 'bulk_actions',
		'actions' => array(
			array( 'action' => 'review', 'label' => __( 'Review', 'localizepilot' ) ),
			array( 'action' => 'update', 'label' => __( 'Update', 'localizepilot' ) ),
			array( 'action' => 'more', 'label' => __( 'More', 'localizepilot' ) ),
		),
	)
);

$lp_table .= Template::capture(
	'parts/table',
	array(
		'label'      => __( 'Translations', 'localizepilot' ),
		'selectable' => true,
		'columns'    => array(
			array( 'key' => 'content', 'label' => __( 'Content', 'localizepilot' ) ),
			array( 'key' => 'type', 'label' => __( 'Type', 'localizepilot' ) ),
			array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
			array( 'key' => 'progress', 'label' => __( 'Progress', 'localizepilot' ) ),
			array( 'key' => 'status', 'label' => __( 'Status', 'localizepilot' ) ),
			array( 'key' => 'updated', 'label' => __( 'Updated', 'localizepilot' ) ),
			array( 'key' => 'actions', 'label' => __( 'Action', 'localizepilot' ), 'align' => 'right' ),
		),
		'rows'       => array_map(
			static function ( array $row ) use ( $lp_cell ): array {
				return array(
					'id'    => (int) $row['id'],
					'label' => (string) $row['title'],
					'cells' => $lp_cell( $row ),
				);
			},
			$lp_items
		),
		'empty'      => array(
			// This card has no title, so the empty state is the section heading.
			'level'   => 2,
			'icon'    => 'nav-translations',
			'title'   => ! empty( $lp_data['filtered'] )
				? __( 'No translations match these filters', 'localizepilot' )
				: __( 'No translations yet', 'localizepilot' ),
			'message' => ! empty( $lp_data['filtered'] )
				? __( 'Clear the filters to see everything that has been translated.', 'localizepilot' )
				: __( 'Once a page is translated it appears here with its status and language.', 'localizepilot' ),
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
			'base_url' => add_query_arg( array_filter( array( 'language' => $lp_filters['language'] ?? '', 'status' => $lp_filters['status'] ?? '', 's' => $lp_filters['search'] ?? '' ) ), (string) ( $lp_data['base_url'] ?? '' ) ),
		)
	);
}

Template::render( 'parts/card', array( 'flush' => true, 'body' => $lp_table ) );
?>

<?php if ( ! empty( $lp_data['filtered'] ) ) : ?>
	<p class="lp-filtered-note">
		<?php esc_html_e( 'Showing filtered results', 'localizepilot' ); ?>
		<span aria-hidden="true">·</span>
		<a href="<?php echo esc_url( (string) ( $lp_data['base_url'] ?? '' ) ); ?>"><?php esc_html_e( 'Clear filters', 'localizepilot' ); ?></a>
	</p>
<?php endif; ?>
