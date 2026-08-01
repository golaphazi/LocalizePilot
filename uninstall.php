<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'next_translate_settings' );
delete_option( 'next_translate_cache_version' );
delete_option( 'next_translate_daily_usage' );

$translation_ids = get_posts(
	array(
		'post_type'      => 'next_translation',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $translation_ids as $translation_id ) {
	wp_delete_post( $translation_id, true );
}

$cache_directory = trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot';

if ( is_dir( $cache_directory ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cache_directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		$path = $item->getPathname();

		if ( $item->isDir() ) {
			if ( is_dir( $path ) ) {
				rmdir( $path );
			}
		} elseif ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	if ( is_dir( $cache_directory ) ) {
		rmdir( $cache_directory );
	}
}
