<?php
/**
 * Media screen.
 *
 * The library is this site's own attachments. The language columns report
 * what LocalizePilot currently does with media, which is serve the source
 * file everywhere — see Media_Repository. Every control that would change
 * that is gated through Preview's "media" feature.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Preview;
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
				'label'     => __( 'Localized Media', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['localized'] ?? 0 ) ),
				'note'      => __( 'across your site', 'localizepilot' ),
				'note_tone' => ( $lp_counts['localized'] ?? 0 ) > 0 ? 'success' : 'muted',
			),
			array(
				'label'     => __( 'Needs Localization', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['needs'] ?? 0 ) ),
				'note'      => __( 'using source version', 'localizepilot' ),
				'note_tone' => ( $lp_counts['needs'] ?? 0 ) > 0 ? 'warning' : 'muted',
			),
			array(
				'label'     => __( 'Custom Media', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['custom'] ?? 0 ) ),
				'note'      => __( 'language-specific', 'localizepilot' ),
				'note_tone' => ( $lp_counts['custom'] ?? 0 ) > 0 ? 'brand' : 'muted',
			),
			array(
				'label'     => __( 'Total Media', 'localizepilot' ),
				'value'     => number_format_i18n( (int) ( $lp_counts['total'] ?? 0 ) ),
				'note'      => __( 'in WordPress Media', 'localizepilot' ),
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
$lp_cell = static function ( array $row ) use ( $lp_data ): array {
	$thumbnail = '' !== (string) $row['thumbnail']
		? '<img src="' . esc_url( (string) $row['thumbnail'] ) . '" alt="" loading="lazy" decoding="async" width="48" height="48">'
		: Template::icon( 'nav-media', 'lp-icon' );

	$media = '<span class="lp-media-cell">'
		. '<span class="lp-media-cell__thumb">' . $thumbnail . '</span>'
		. '<span class="lp-media-cell__text">'
		. '<span class="lp-cell__title">' . esc_html( (string) $row['title'] ) . '</span>'
		. '<span class="lp-cell__sub">' . esc_html( (string) $row['filename'] ) . '</span>'
		. '</span></span>';

	$source = '<span class="lp-cell__pair">'
		. Template::capture( 'parts/lang-chip', array( 'code' => (string) ( $lp_data['source_code'] ?? '' ) ) )
		. '<span class="lp-cell__muted">' . esc_html( (string) ( $lp_data['source_name'] ?? '' ) ) . '</span>'
		. '</span>';

	// An attachment with no language versions renders the design's em dash
	// rather than an empty cell, which reads as a missing value.
	$languages = '<span class="lp-cell__muted" aria-label="' . esc_attr__( 'No language versions', 'localizepilot' ) . '">&mdash;</span>';

	if ( ! empty( $row['languages'] ) ) {
		$languages = '<span class="lp-chip-row">';

		foreach ( (array) $row['languages'] as $code ) {
			$languages .= Template::capture( 'parts/lang-chip', array( 'code' => (string) $code, 'tone' => 'target' ) );
		}

		$languages .= '</span>';
	}

	$updated = (int) $row['updated'];

	return array(
		'media'     => $media,
		'type'      => '<span class="lp-cell__muted">' . esc_html( (string) $row['type'] ) . '</span>',
		'source'    => $source,
		'languages' => $languages,
		'status'    => Template::capture(
			'parts/badge',
			array( 'label' => (string) $row['status_label'], 'tone' => (string) $row['status'] )
		),
		'used_in'   => '' !== (string) $row['used_in']
			? '<span class="lp-cell__muted">' . esc_html( (string) $row['used_in'] ) . '</span>'
			: '<span class="lp-cell__muted">' . esc_html__( 'Unattached', 'localizepilot' ) . '</span>',
		'updated'   => '<span class="lp-cell__muted">' . esc_html(
			$updated > 0
				? sprintf(
					/* translators: %s is a human-readable time difference, e.g. "2 hours". */
					__( '%s ago', 'localizepilot' ),
					human_time_diff( $updated )
				)
				: __( 'Never', 'localizepilot' )
		) . '</span>',
		'actions'   => Template::capture(
			'parts/row-actions',
			array(
				'label'   => (string) $row['title'],
				'primary' => array(
					'label' => __( 'Localize', 'localizepilot' ),
					'style' => 'solid',
					'url'   => (string) $row['edit_url'],
				),
				'menu'    => array(
					array( 'label' => __( 'Edit in Media Library', 'localizepilot' ), 'url' => (string) $row['edit_url'] ),
					array( 'label' => __( 'Open file', 'localizepilot' ), 'url' => (string) $row['view_url'], 'external' => true ),
				),
			)
		),
	);
};

$lp_table = Template::capture(
	'parts/toolbar',
	array(
		'action'  => admin_url( 'admin.php' ),
		'hidden'  => array( 'page' => 'localizepilot-media' ),
		'search'  => array(
			'name'        => 's',
			'value'       => (string) ( $lp_filters['search'] ?? '' ),
			'placeholder' => __( 'Search media, filename, or URL…', 'localizepilot' ),
		),
		'filters' => array(
			// Nothing records a language or a localization state per
			// attachment yet, so these two render but cannot be operated.
			array(
				'name'    => 'language',
				'label'   => __( 'Language', 'localizepilot' ),
				'value'   => '',
				'feature' => 'media',
				'options' => (array) ( $lp_data['languages'] ?? array() ),
			),
			array(
				'name'    => 'status',
				'label'   => __( 'Status', 'localizepilot' ),
				'value'   => '',
				'feature' => 'media',
				'options' => array(
					''              => __( 'All Status', 'localizepilot' ),
					'localized'     => __( 'Localized', 'localizepilot' ),
					'needs_update'  => __( 'Needs Update', 'localizepilot' ),
					'not_localized' => __( 'Not Localized', 'localizepilot' ),
				),
			),
			array(
				'name'    => 'type',
				'label'   => __( 'Type', 'localizepilot' ),
				'value'   => (string) ( $lp_filters['type'] ?? '' ),
				'options' => (array) ( $lp_data['types'] ?? array() ),
			),
			array(
				'name'           => 'order',
				'label'          => __( 'Sort', 'localizepilot' ),
				'value'          => (string) ( $lp_filters['order'] ?? 'newest' ),
				'divider_before' => true,
				'options'        => array(
					'newest' => __( 'Recently updated', 'localizepilot' ),
					'oldest' => __( 'Oldest first', 'localizepilot' ),
				),
			),
		),
	)
);

$lp_table .= Template::capture(
	'parts/bulk-bar',
	array(
		'feature' => 'media',
		'actions' => array(
			array( 'action' => 'update', 'label' => __( 'Update', 'localizepilot' ) ),
			array( 'action' => 'set-language', 'label' => __( 'Set Language', 'localizepilot' ) ),
			array( 'action' => 'remove', 'label' => __( 'Remove Localization', 'localizepilot' ) ),
		),
	)
);

$lp_table .= Template::capture(
	'parts/table',
	array(
		'label'      => __( 'Media library', 'localizepilot' ),
		'selectable' => true,
		'columns'    => array(
			array( 'key' => 'media', 'label' => __( 'Media', 'localizepilot' ) ),
			array( 'key' => 'type', 'label' => __( 'Type', 'localizepilot' ) ),
			array( 'key' => 'source', 'label' => __( 'Source', 'localizepilot' ) ),
			array( 'key' => 'languages', 'label' => __( 'Languages', 'localizepilot' ) ),
			array( 'key' => 'status', 'label' => __( 'Status', 'localizepilot' ) ),
			array( 'key' => 'used_in', 'label' => __( 'Used In', 'localizepilot' ) ),
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
			'icon'    => 'nav-media',
			'title'   => ! empty( $lp_data['filtered'] )
				? __( 'No media matches these filters', 'localizepilot' )
				: __( 'No media yet', 'localizepilot' ),
			'message' => ! empty( $lp_data['filtered'] )
				? __( 'Clear the filters to see everything in the media library.', 'localizepilot' )
				: __( 'Images and files added to WordPress appear here with the pages that use them.', 'localizepilot' ),
			'action'  => ! empty( $lp_data['filtered'] )
				? array( 'label' => __( 'Clear filters', 'localizepilot' ), 'url' => (string) ( $lp_data['base_url'] ?? '' ) )
				: array( 'label' => __( 'Open Media Library', 'localizepilot' ), 'url' => (string) ( $lp_data['library_url'] ?? '' ) ),
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
						'type' => $lp_filters['type'] ?? '',
						's'    => $lp_filters['search'] ?? '',
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
		'title'    => __( 'Media Library', 'localizepilot' ),
		'subtitle' => __( 'View and manage media used across your localized content.', 'localizepilot' ),
		'link'     => array(
			'label'    => __( 'View WordPress Media', 'localizepilot' ),
			'url'      => (string) ( $lp_data['library_url'] ?? '' ),
			'external' => true,
		),
		'flush'    => true,
		'body'     => $lp_table,
	)
);
?>

<?php if ( Preview::is_preview( 'media' ) ) : ?>
	<p class="lp-filtered-note">
		<?php esc_html_e( 'Media localization is not connected yet — this library is real, its language columns describe what LocalizePilot does today.', 'localizepilot' ); ?>
	</p>
<?php endif; ?>
