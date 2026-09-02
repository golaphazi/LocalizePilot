<?php
/**
 * Overview screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data     = (array) ( $args['data'] ?? array() );
$lp_provider = (array) ( $lp_data['provider'] ?? array() );
$lp_cache    = (array) ( $lp_data['cache'] ?? array() );
$lp_source   = (array) ( $lp_data['source'] ?? array() );
?>

<div class="lp-quick-links">
	<?php foreach ( (array) ( $lp_data['quick_links'] ?? array() ) as $lp_link ) : ?>
		<a class="lp-quick-link" href="<?php echo esc_url( (string) $lp_link['url'] ); ?>">
			<?php Template::the_icon( (string) $lp_link['icon'], 'lp-icon' ); ?>
			<span><?php echo esc_html( (string) $lp_link['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</div>

<?php
Template::render( 'parts/kpi-grid', array( 'items' => (array) ( $lp_data['kpis'] ?? array() ) ) );

/* Attention list and provider card. */
$lp_attention = (array) ( $lp_data['attention'] ?? array() );

$lp_provider_body = '<div class="lp-provider">'
	. '<span class="lp-provider__mark">' . esc_html( (string) ( $lp_provider['mark'] ?? '' ) ) . '</span>'
	. '<span class="lp-provider__text">'
	. '<strong>' . esc_html( (string) ( $lp_provider['label'] ?? '' ) ) . '</strong>'
	. '<span>' . esc_html(
		! empty( $lp_provider['connected'] )
			? __( 'Connected', 'localizepilot' )
			: __( 'No API key yet', 'localizepilot' )
	) . '</span>'
	. '</span>'
	. Template::capture(
		'parts/badge',
		array(
			'tone'  => ! empty( $lp_provider['connected'] ) ? 'reviewed' : 'needs_update',
			'label' => ! empty( $lp_provider['connected'] ) ? __( 'Ready', 'localizepilot' ) : __( 'Action needed', 'localizepilot' ),
		)
	)
	. '</div>';

if ( ! empty( $lp_provider['limited'] ) ) {
	$lp_provider_body .= '<div class="lp-provider__usage">'
		. '<span>' . esc_html__( 'Daily usage', 'localizepilot' ) . '</span>'
		. '<span>' . esc_html(
			sprintf(
				/* translators: 1: translations used today, 2: daily limit. */
				__( '%1$s / %2$s', 'localizepilot' ),
				number_format_i18n( (int) ( $lp_provider['usage'] ?? 0 ) ),
				number_format_i18n( (int) ( $lp_provider['limit'] ?? 0 ) )
			)
		) . '</span></div>'
		. Template::capture(
			'parts/progress',
			array(
				'value' => (int) round( 100 * min( (int) ( $lp_provider['usage'] ?? 0 ), (int) ( $lp_provider['limit'] ?? 1 ) ) / max( 1, (int) ( $lp_provider['limit'] ?? 1 ) ) ),
				'width' => '100%',
				'tone'  => 'brand',
			)
		);
}

if ( ! empty( $lp_provider['fallback'] ) ) {
	$lp_provider_body .= '<p class="lp-provider__fallback">' . esc_html(
		sprintf(
			/* translators: %s is the fallback provider name. */
			__( 'Falls back to %s', 'localizepilot' ),
			(string) $lp_provider['fallback']
		)
	) . '</p>';
}

$lp_provider_body .= '<a class="lp-btn lp-btn--ghost lp-btn--block" href="' . esc_url( Screen_Registry::url( 'providers' ) ) . '"><span>'
	. esc_html__( 'Manage Providers', 'localizepilot' ) . '</span></a>';
?>

<div class="lp-cols lp-cols--wide-narrow">
	<?php
	Template::render(
		'parts/card',
		array(
			'title'    => __( 'Needs Your Attention', 'localizepilot' ),
			'subtitle' => __( 'Everything here is worked out from your current content and settings.', 'localizepilot' ),
			'body'     => ! empty( $lp_attention )
				? Template::capture( 'parts/task-list', array( 'items' => $lp_attention ) )
				: Template::capture(
					'parts/empty-state',
					array(
						'icon'    => 'nav-overview',
						'title'   => __( 'Nothing needs attention', 'localizepilot' ),
						'message' => __( 'Every enabled language has translations, and your provider is configured.', 'localizepilot' ),
					)
				),
		)
	);

	Template::render(
		'parts/card',
		array(
			'title' => __( 'Translation Provider', 'localizepilot' ),
			'body'  => $lp_provider_body,
		)
	);
	?>
</div>

<?php
/* Coverage and cache. */
$lp_coverage = (array) ( $lp_data['coverage'] ?? array() );
$lp_coverage_body = '';

foreach ( $lp_coverage as $lp_language ) {
	$lp_coverage_body .= '<div class="lp-coverage-row">'
		. Template::capture( 'parts/lang-chip', array( 'code' => $lp_language['code'], 'tone' => 'target' ) )
		. '<span class="lp-coverage-row__name">' . esc_html( (string) $lp_language['name'] ) . '</span>'
		. '<span class="lp-coverage-row__meta">' . esc_html(
			sprintf(
				/* translators: %s is a number of translations. */
				__( '%s translated', 'localizepilot' ),
				number_format_i18n( (int) $lp_language['translated'] )
			)
		) . '</span>'
		. Template::capture( 'parts/progress', array( 'value' => (int) $lp_language['coverage'], 'label' => true, 'width' => '100%' ) )
		. '</div>';
}

$lp_cache_body = '<div class="lp-cache"' . Preview::attributes( 'cache_hit_rate' ) . '>'
	. Template::capture(
		'parts/donut',
		array(
			'value'   => (int) ( $lp_cache['hit_rate'] ?? 0 ),
			'caption' => __( 'Hit Rate', 'localizepilot' ),
		)
	)
	. '</div>'
	. '<dl class="lp-stat-list">'
	. '<div><dt>' . esc_html__( 'Rendered pages', 'localizepilot' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) ( $lp_cache['rendered'] ?? 0 ) ) ) . '</dd></div>'
	. '<div><dt>' . esc_html__( 'Expired entries', 'localizepilot' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) ( $lp_cache['expired'] ?? 0 ) ) ) . '</dd></div>'
	. '<div><dt>' . esc_html__( 'Translation snapshots', 'localizepilot' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) ( $lp_cache['snapshots'] ?? 0 ) ) ) . '</dd></div>'
	. '<div><dt>' . esc_html__( 'Object cache', 'localizepilot' ) . '</dt><dd>' . esc_html(
		! empty( $lp_cache['object_cache'] ) ? __( 'Active', 'localizepilot' ) : __( 'Off', 'localizepilot' )
	) . '</dd></div>'
	. '</dl>';

Template::render( 'parts/card', array(
	'title'    => __( 'Translation Coverage', 'localizepilot' ),
	'subtitle' => sprintf(
		/* translators: %s is the source language name. */
		__( 'Share of published content translated from %s.', 'localizepilot' ),
		(string) ( $lp_source['name'] ?? 'English' )
	),
	'link'     => array( 'label' => __( 'View all', 'localizepilot' ), 'url' => Screen_Registry::url( 'languages' ) ),
	'class'    => 'lp-coverage-card',
	'body'     => '' !== $lp_coverage_body
		? '<div class="lp-coverage">' . $lp_coverage_body . '</div>'
		: Template::capture(
			'parts/empty-state',
			array(
				'icon'    => 'nav-languages',
				'title'   => __( 'No languages enabled yet', 'localizepilot' ),
				'message' => __( 'Enable a language to start tracking translation coverage.', 'localizepilot' ),
				'action'  => array( 'label' => __( 'Add Language', 'localizepilot' ), 'url' => Screen_Registry::url( 'languages' ) ),
			)
		),
) );
?>

<div class="lp-cols lp-cols--wide-narrow">
	<?php
	/* Recent translations. */
	$lp_recent_rows = array_map(
		static function ( array $row ): array {
			$updated = (int) $row['updated'];

			return array(
				'id'    => (int) $row['id'],
				'label' => (string) $row['title'],
				'cells' => array(
					'content'  => '<span class="lp-cell__title">' . esc_html( (string) $row['title'] ) . '</span>',
					'language' => Template::capture(
						'parts/lang-chip',
						array( 'code' => (string) $row['language'], 'tone' => 'target', 'title' => (string) $row['language_name'] )
					),
					'status'   => Template::capture(
						'parts/badge',
						array( 'label' => (string) $row['status_label'], 'tone' => (string) $row['status'] )
					),
					'updated'  => '<span class="lp-cell__muted">' . esc_html(
						$updated > 0
							? sprintf(
								/* translators: %s is a human-readable time difference. */
								__( '%s ago', 'localizepilot' ),
								human_time_diff( $updated )
							)
							: __( 'Never', 'localizepilot' )
					) . '</span>',
				),
			);
		},
		(array) ( $lp_data['recent'] ?? array() )
	);

	Template::render(
		'parts/card',
		array(
			'flush' => true,
			'title' => __( 'Recent Translations', 'localizepilot' ),
			'link'  => array( 'label' => __( 'View all', 'localizepilot' ), 'url' => Screen_Registry::url( 'translations' ) ),
			'body'  => Template::capture(
				'parts/table',
				array(
					'label'   => __( 'Recent translations', 'localizepilot' ),
					'columns' => array(
						array( 'key' => 'content', 'label' => __( 'Content', 'localizepilot' ) ),
						array( 'key' => 'language', 'label' => __( 'Language', 'localizepilot' ) ),
						array( 'key' => 'status', 'label' => __( 'Status', 'localizepilot' ) ),
						array( 'key' => 'updated', 'label' => __( 'Updated', 'localizepilot' ), 'align' => 'right' ),
					),
					'rows'    => $lp_recent_rows,
					'empty'   => array(
						'icon'    => 'nav-translations',
						'title'   => __( 'No translations yet', 'localizepilot' ),
						'message' => __( 'Translate a page and it will appear here.', 'localizepilot' ),
					),
				)
			),
		)
	);

	Template::render(
		'parts/card',
		array(
			'title'    => __( 'Cache & Performance', 'localizepilot' ),
			'subtitle' => __( 'Monitor localized page caching and translation rendering.', 'localizepilot' ),
			'link'     => array( 'label' => __( 'Manage', 'localizepilot' ), 'url' => Screen_Registry::url( 'performance' ) ),
			'body'     => $lp_cache_body,
		)
	);
	?>
</div>
