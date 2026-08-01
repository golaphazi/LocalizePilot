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

$directories = array(
	trailingslashit( WP_CONTENT_DIR ) . 'cache/localizepilot',
	trailingslashit( WP_CONTENT_DIR ) . 'cache/next-translate',
);

foreach ( $directories as $directory ) {
if ( is_dir( $directory ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getPathname() );
		} else {
			@unlink( $item->getPathname() );
		}
	}
	@rmdir( $directory );
}

}
