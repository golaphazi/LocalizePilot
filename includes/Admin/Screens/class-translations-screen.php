<?php
/**
 * Translations console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Translation_Repository;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Translation_Manager;

defined( 'ABSPATH' ) || exit;

final class Translations_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'translations';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array(
			$this->view_site_action(),
			array(
				'label' => __( 'Translate Content', 'localizepilot' ),
				'url'   => '',
				'style' => 'primary',
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$repository = new Translation_Repository();
		$filters    = $this->filters();
		$results    = $repository->query( $filters );
		$counts     = $repository->counts();
		$settings   = Plugin::instance()->get_settings();

		return array(
			'filters'   => $filters,
			'filtered'  => $this->is_filtered( $filters ),
			'results'   => $results,
			'counts'    => $counts,
			'share'     => $repository->localized_share(),
			'languages' => $this->language_options(),
			'statuses'  => Translation_Manager::statuses(),
			'enabled'   => count( (array) ( $settings['enabled_languages'] ?? array() ) ) + 1,
			'translated' => $counts['total'] > 0 ? count( array_unique( array_filter( array_column( $results['items'], 'language' ) ) ) ) : 0,
			'base_url'  => Screen_Registry::url( $this->slug() ),
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
			'language' => sanitize_key( wp_unslash( $_GET['language'] ?? '' ) ),
			'status'   => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'search'   => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'order'    => 'oldest' === sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ) ? 'oldest' : 'newest',
			'paged'    => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * @param array<string,mixed> $filters Current filters.
	 */
	private function is_filtered( array $filters ): bool {
		return '' !== $filters['language']
			|| '' !== $filters['status']
			|| '' !== $filters['search'];
	}

	/**
	 * Enabled languages as select options.
	 *
	 * @return array<string,string>
	 */
	private function language_options(): array {
		$settings = Plugin::instance()->get_settings();
		$options  = array( '' => __( 'All Languages', 'localizepilot' ) );

		foreach ( (array) ( $settings['enabled_languages'] ?? array() ) as $code ) {
			$code = strtolower( (string) $code );

			if ( Language_Catalog::exists( $code ) ) {
				$options[ $code ] = Language_Catalog::label( $code, 'english' );
			}
		}

		return $options;
	}
}
