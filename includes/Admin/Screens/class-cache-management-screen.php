<?php
/**
 * Cache Management console screen.
 *
 * The design splits what used to be one screen in two: Performance reports on
 * caching, and this screen configures it and operates on the generated files.
 * Both read the same model, and both post to the same long-standing cache
 * endpoint, telling it which of them to return to.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Performance_Model;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

final class Cache_Management_Screen extends Abstract_Screen {
	/**
	 * Bulk actions offered by the "Manage Your Cache" card, in display order.
	 */
	private const BULK_ACTIONS = array( 'clear_expired', 'clear_all', 'clear_snapshots', 'purge_host' );

	public function slug(): string {
		return 'cache-management';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array(
			$this->view_site_action(),
			array(
				'label' => __( 'Clear Cache', 'localizepilot' ),
				'url'   => $this->cache_action_url( 'clear_all', $this->filters() ),
				'style' => 'warning',
				'icon'  => 'trash',
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$model   = new Performance_Model();
		$filters = $this->filters();
		$stats   = $model->stats();
		$history = $model->history(
			$filters['paged'],
			$filters['type'],
			array(
				'language' => $filters['language'],
				'status'   => $filters['status'],
			)
		);

		foreach ( $history['items'] as &$item ) {
			$item['delete_url'] = $this->cache_action_url(
				'delete',
				$filters,
				(string) ( $item['key'] ?? '' ),
				(string) ( $item['type'] ?? 'page' )
			);
		}
		unset( $item );

		$actions = array();
		foreach ( self::BULK_ACTIONS as $action ) {
			$actions[ $action ] = $this->cache_action_url( $action, $filters );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message set by the verified admin-post redirect.
		$message = sanitize_text_field( wp_unslash( $_GET['localizepilot_cache_message'] ?? '' ) );

		return array(
			'settings'  => $model->settings(),
			'stats'     => $stats,
			'health'    => $this->health( $stats ),
			'history'   => $history,
			'filters'   => $filters,
			'actions'   => $actions,
			'languages' => $this->languages(),
			'message'   => $message,
			'base_url'  => Screen_Registry::url( $this->slug() ),
		);
	}

	/**
	 * Enabled languages for the history filter, code => English label.
	 *
	 * @return array<string,string>
	 */
	private function languages(): array {
		$settings = wp_parse_args( Plugin::instance()->get_settings(), Plugin::defaults() );
		$options  = array();

		foreach ( (array) ( $settings['enabled_languages'] ?? array() ) as $code ) {
			$code = sanitize_key( (string) $code );

			if ( '' !== $code && Language_Catalog::exists( $code ) ) {
				$options[ $code ] = Language_Catalog::label( $code, 'english' );
			}
		}

		return $options;
	}

	/**
	 * The status card and the banner above the configuration.
	 *
	 * The design shows one healthy state; the two unhealthy ones are the
	 * conditions the plugin can actually detect, so they are reported rather
	 * than assumed away.
	 *
	 * @param array<string,mixed> $stats Cache summary.
	 *
	 * @return array{tone:string,label:string,title:string,message:string}
	 */
	private function health( array $stats ): array {
		if ( ! (bool) ( $stats['writable'] ?? false ) ) {
			return array(
				'tone'    => 'danger',
				'label'   => __( 'Not writable', 'localizepilot' ),
				'title'   => __( 'Cache directory is not writable', 'localizepilot' ),
				'message' => __( 'Localized pages cannot be cached until the directory below is writable by WordPress.', 'localizepilot' ),
			);
		}

		if ( (int) ( $stats['expired'] ?? 0 ) > 0 ) {
			return array(
				'tone'    => 'warning',
				'label'   => __( 'Needs cleaning', 'localizepilot' ),
				'title'   => __( 'Expired cache is waiting to be cleared', 'localizepilot' ),
				'message' => __( 'Expired entries are still being served as a fallback until they are regenerated or removed.', 'localizepilot' ),
			);
		}

		return array(
			'tone'    => 'success',
			'label'   => __( 'Healthy', 'localizepilot' ),
			'title'   => __( 'Cache is working normally', 'localizepilot' ),
			'message' => __( 'Localized pages are being served from the generated cache. No cache errors detected.', 'localizepilot' ),
		);
	}

	/**
	 * @return array{type:string,paged:int}
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only history filters with no side effects.
		$type = sanitize_key( wp_unslash( $_GET['cache_type'] ?? 'all' ) );
		$type = in_array( $type, array( 'all', 'page', 'snapshot' ), true ) ? $type : 'all';

		$language = sanitize_key( wp_unslash( is_scalar( $_GET['cache_language'] ?? '' ) ? (string) ( $_GET['cache_language'] ?? '' ) : '' ) );
		$status   = sanitize_key( wp_unslash( is_scalar( $_GET['cache_status'] ?? '' ) ? (string) ( $_GET['cache_status'] ?? '' ) : '' ) );

		return array(
			'type'     => $type,
			'paged'    => max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ),
			'language' => Language_Catalog::exists( $language ) ? $language : '',
			'status'   => in_array( $status, array( 'active', 'expired' ), true ) ? $status : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * URL for one of the existing cache-management actions.
	 *
	 * @param array{type:string,paged:int,language:string,status:string} $filters Current list state.
	 */
	private function cache_action_url( string $action, array $filters, string $key = '', string $type = 'page' ): string {
		$args = array(
			'action'          => 'next_translate_cache_action',
			'cache_action'    => sanitize_key( $action ),
			'cache_item_type' => sanitize_key( $type ),
			'cache_page'      => max( 1, (int) $filters['paged'] ),
			'cache_type'      => sanitize_key( $filters['type'] ),
			'return_screen'   => $this->slug(),
		);

		if ( '' !== $key ) {
			$args['cache_key'] = sanitize_file_name( $key );
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'next_translate_cache_action'
		);
	}
}
