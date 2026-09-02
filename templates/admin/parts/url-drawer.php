<?php
/**
 * The URL drawer, framed.
 *
 * Both designed drawers are this one: a stale row gets the warning banner, the
 * "Issue Details" heading and the review action; everything else gets the plain
 * detail view. Keeping the framing here means the deep-link render and the
 * drawer endpoint cannot drift apart.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args A row from Url_Repository::detail(), plus
 *                                an optional `open` flag.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_stale = ! empty( $args['stale'] );
$lp_names = (array) ( $args['stale_names'] ?? array() );

Template::render(
	'parts/drawer',
	array(
		'id'      => 'lp-url-' . (int) ( $args['id'] ?? 0 ),
		'open'    => ! empty( $args['open'] ),
		'title'   => $lp_stale ? __( 'Issue Details', 'localizepilot' ) : __( 'URL Details', 'localizepilot' ),
		'banner'  => $lp_stale
			? array(
				'tone'    => 'warning',
				'title'   => sprintf(
					/* translators: %s is a comma-separated list of language names. */
					__( '%s needs review', 'localizepilot' ),
					implode( ', ', $lp_names )
				),
				'message' => __( 'The source content changed after this translation was created.', 'localizepilot' ),
			)
			: array(),
		'body'    => Template::capture( 'parts/url-detail', $args ),
		'actions' => array(
			array(
				'label'    => __( 'View Source', 'localizepilot' ),
				'url'      => (string) ( $args['url'] ?? '' ),
				'external' => true,
				'icon'     => 'external-link',
			),
			array(
				'label'   => $lp_stale ? __( 'Review Translation', 'localizepilot' ) : __( 'Save Changes', 'localizepilot' ),
				'style'   => 'primary',
				'url'     => $lp_stale ? (string) ( $args['edit_url'] ?? '' ) : '',
				'feature' => $lp_stale ? '' : 'canonical_editing',
			),
		),
	)
);
