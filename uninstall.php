<?php
/**
 * LocalizePilot uninstall handler.
 *
 * Removes plugin settings, translation records, and generated cache files.
 *
 * @package LocalizePilot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Delete plugin settings.
 *
 * The next_translate_* option names are retained for backward compatibility
 * with earlier plugin versions.
 */
delete_option( 'next_translate_settings' );
delete_option( 'next_translate_cache_version' );
delete_option( 'next_translate_daily_usage' );

/*
 * Delete stored translations.
 */
$translation_ids = get_posts(
	array(
		'post_type'              => 'next_translation',
		'post_status'            => 'any',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'suppress_filters'       => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	)
);

foreach ( $translation_ids as $translation_id ) {
	wp_delete_post( absint( $translation_id ), true );
}

/*
 * Initialize the WordPress Filesystem API.
 */
require_once ABSPATH . 'wp-admin/includes/file.php';

global $wp_filesystem;

if ( WP_Filesystem() && $wp_filesystem ) {
	$cache_directory = trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot';

	/*
	 * Safety check to ensure only the LocalizePilot cache directory is removed.
	 */
	$normalized_cache_directory = wp_normalize_path(
		untrailingslashit( $cache_directory )
	);

	$expected_cache_directory = wp_normalize_path(
		untrailingslashit(
			trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot'
		)
	);

	if (
		$normalized_cache_directory === $expected_cache_directory &&
		$wp_filesystem->is_dir( $cache_directory )
	) {
		$wp_filesystem->delete(
			$cache_directory,
			true,
			'd'
		);
	}
}