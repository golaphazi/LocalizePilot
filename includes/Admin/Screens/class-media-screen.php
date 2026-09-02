<?php
/**
 * Media console screen.
 *
 * The library is real; the localization of it is not built yet, so every
 * control on this screen is gated through Preview's "media" feature.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Media_Repository;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

final class Media_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'media';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array(
			$this->view_site_action(),
			array(
				'label'   => __( 'Upload Media', 'localizepilot' ),
				'url'     => '',
				'style'   => 'primary',
				'icon'    => 'plus',
				'feature' => 'media',
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$repository = new Media_Repository();
		$filters    = $this->filters();
		$settings   = Plugin::instance()->get_settings();
		$source     = strtolower( (string) ( $settings['source_language'] ?? 'en' ) );

		return array(
			'filters'     => $filters,
			'filtered'    => '' !== $filters['search'] || '' !== $filters['type'],
			'results'     => $repository->query( $filters ),
			'counts'      => $repository->counts(),
			'types'       => $repository->type_options(),
			'languages'   => $this->language_options(),
			'source_code' => strtoupper( $source ),
			'source_name' => Language_Catalog::label( $source, 'english' ),
			'library_url' => admin_url( 'upload.php' ),
			'base_url'    => Screen_Registry::url( $this->slug() ),
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
			'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'type'   => sanitize_key( wp_unslash( $_GET['type'] ?? '' ) ),
			'order'  => 'oldest' === sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ) ? 'oldest' : 'newest',
			'paged'  => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
