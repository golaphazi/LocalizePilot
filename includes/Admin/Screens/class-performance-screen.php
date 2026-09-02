<?php
/**
 * Performance console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Performance_Model;
use LocalizePilot\Admin\Screen_Registry;

defined( 'ABSPATH' ) || exit;

final class Performance_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'performance';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array( $this->view_site_action() );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$model   = new Performance_Model();
		$filters = $this->filters();
		$history = $model->history( $filters['paged'], $filters['type'] );

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
		foreach ( array( 'clear_expired', 'clear_all', 'clear_snapshots', 'purge_host' ) as $action ) {
			$actions[ $action ] = $this->cache_action_url( $action, $filters );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message set by the verified admin-post redirect.
		$message = sanitize_text_field( wp_unslash( $_GET['localizepilot_cache_message'] ?? '' ) );

		return array(
			'settings'   => $model->settings(),
			'stats'      => $model->stats(),
			'history'    => $history,
			'host_cache' => $model->host_cache(),
			'filters'    => $filters,
			'actions'    => $actions,
			'message'    => $message,
			'base_url'   => Screen_Registry::url( $this->slug() ),
		);
	}

	/**
	 * @return array{type:string,paged:int}
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only history filters with no side effects.
		$type = sanitize_key( wp_unslash( $_GET['cache_type'] ?? 'all' ) );
		$type = in_array( $type, array( 'all', 'page', 'snapshot' ), true ) ? $type : 'all';

		return array(
			'type'  => $type,
			'paged' => max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * URL for one of the existing cache-management actions.
	 *
	 * @param array{type:string,paged:int} $filters Current list state.
	 */
	private function cache_action_url( string $action, array $filters, string $key = '', string $type = 'page' ): string {
		$args = array(
			'action'          => 'next_translate_cache_action',
			'cache_action'    => sanitize_key( $action ),
			'cache_item_type' => sanitize_key( $type ),
			'cache_page'      => max( 1, (int) $filters['paged'] ),
			'cache_type'      => sanitize_key( $filters['type'] ),
			'return_screen'   => 'performance',
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
