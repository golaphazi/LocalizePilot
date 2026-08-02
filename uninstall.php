<?php
/**
 * LocalizePilot uninstall handler.
 *
 * Removes plugin settings, translation records, and generated cache files.
 *
 * @package LocalizePilot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove all LocalizePilot data.
 */
(static function (): void {
	/*
	 * The next_translate_* option names are retained for backward compatibility
	 * with earlier plugin versions.
	 */
	delete_option( 'next_translate_settings' );
	delete_option( 'next_translate_cache_version' );
	delete_option( 'next_translate_daily_usage' );
	delete_option( 'localizepilot_translation_parent_migrated' );
	delete_option( 'localizepilot_analytics_db_version' );

	wp_clear_scheduled_hook( 'localizepilot_daily_analytics_cleanup' );

	global $wpdb;
	$localizepilot_analytics_table = $wpdb->prefix . 'localizepilot_visits';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress database prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$localizepilot_analytics_table}" );

	$localizepilot_translation_ids = get_posts(
		array(
			'post_type'              => 'next_translation',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $localizepilot_translation_ids as $localizepilot_translation_id ) {
		wp_delete_post( absint( $localizepilot_translation_id ), true );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	global $wp_filesystem;

	if ( ! WP_Filesystem() || ! $wp_filesystem ) {
		return;
	}

	$localizepilot_cache_directory = trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot';
	$localizepilot_normalized_path = wp_normalize_path( untrailingslashit( $localizepilot_cache_directory ) );
	$localizepilot_expected_path   = wp_normalize_path(
		untrailingslashit( trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot' )
	);

	if (
		$localizepilot_normalized_path === $localizepilot_expected_path
		&& $wp_filesystem->is_dir( $localizepilot_cache_directory )
	) {
		$wp_filesystem->delete( $localizepilot_cache_directory, true, 'd' );
	}
})();
