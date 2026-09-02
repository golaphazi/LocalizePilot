<?php
/**
 * SEO & URLs console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Url_Repository;
use LocalizePilot\Admin\Screen_Registry;

defined( 'ABSPATH' ) || exit;

final class Seo_Urls_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'seo-urls';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array(
			$this->view_site_action(),
			array(
				'label' => __( 'URL Settings', 'localizepilot' ),
				'url'   => Screen_Registry::url( 'settings' ),
				'style' => 'primary',
				'icon'  => 'cog',
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$repository = new Url_Repository();
		$filters    = $this->filters();
		$results    = $repository->query( $filters );

		/*
		 * Only the deep-linked drawer is rendered with the page. Shipping one
		 * per row costs a few hundred kilobytes of panels almost none of which
		 * are opened; the rest are fetched from Ajax::url_drawer() on demand,
		 * through the same partial.
		 */
		$open   = $this->open_drawer();
		$row    = $open > 0 ? $repository->row( $open ) : null;
		$detail = null !== $row ? $repository->detail( $row ) : array();

		return array(
			'filters'        => $filters,
			'filtered'       => $this->is_filtered( $filters ),
			'results'        => $results,
			'open_drawer'    => null !== $row ? $open : 0,
			'open_detail'    => $detail,
			'counts'         => $repository->counts(),
			'structure'      => $repository->structure(),
			'internal_links' => $repository->internal_links(),
			'attention'      => $repository->attention(),
			'languages'      => $repository->language_options(),
			'statuses'       => $repository->status_options(),
			'types'          => $repository->type_options(),
			'settings_url'   => Screen_Registry::url( 'settings' ),
			'base_url'       => Screen_Registry::url( $this->slug() ),
		);
	}

	/**
	 * Read the list filters out of the request.
	 *
	 * @return array<string,mixed>
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters with no side effects.
		return array(
			'search'   => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'language' => sanitize_key( wp_unslash( $_GET['language'] ?? '' ) ),
			'status'   => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'type'     => sanitize_key( wp_unslash( $_GET['type'] ?? '' ) ),
			'issues'   => ! empty( $_GET['issues'] ),
			'order'    => 'oldest' === sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ) ? 'oldest' : 'newest',
			'paged'    => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The row whose drawer should already be open, from ?view=<id>.
	 *
	 * Deep-linking a drawer means the row action works as an ordinary link
	 * when JavaScript is unavailable, and makes a particular URL shareable.
	 */
	private function open_drawer(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch with no side effects.
		return max( 0, (int) ( $_GET['view'] ?? 0 ) );
	}

	/**
	 * @param array<string,mixed> $filters Current filters.
	 */
	private function is_filtered( array $filters ): bool {
		return '' !== $filters['search']
			|| '' !== $filters['language']
			|| '' !== $filters['status']
			|| '' !== $filters['type']
			|| ! empty( $filters['issues'] );
	}
}
