<?php
/**
 * Analytics console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Analytics_Model;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

final class Analytics_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'analytics';
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
		$model = new Analytics_Model();

		if ( ! $model->is_enabled() ) {
			return array(
				'enabled'   => false,
				// Coverage gaps are knowable whether or not tracking is on.
				'attention' => $model->attention(),
				'settings_url' => Screen_Registry::url( 'settings' ),
				'base_url'  => Screen_Registry::url( $this->slug() ),
			);
		}

		$filters = $this->filters();
		$report  = $model->report( $filters );

		return array(
			'enabled'   => true,
			'filters'   => $filters,
			'report'    => $report,
			'languages' => $this->language_options(),
			'ranges'    => $this->range_options(),
			'by_language' => $model->by_language( $filters ),
			'insights'  => $model->insights( $filters ),
			'attention' => $model->attention( $filters ),
			'settings_url' => Screen_Registry::url( 'settings' ),
			'base_url'  => Screen_Registry::url( $this->slug() ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only report filters with no side effects.
		$range = sanitize_key( wp_unslash( $_GET['range'] ?? '7' ) );
		$range = array_key_exists( $range, $this->range_options() ) ? $range : '7';

		$filters = array(
			'language' => sanitize_key( wp_unslash( $_GET['language'] ?? '' ) ),
			'url'      => sanitize_text_field( wp_unslash( $_GET['url'] ?? '' ) ),
			'range'    => $range,
			'page'     => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$days                 = (int) $range;
		$filters['date_to']   = gmdate( 'Y-m-d' );
		$filters['date_from'] = gmdate( 'Y-m-d', time() - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS );

		return $filters;
	}

	/**
	 * @return array<string,string>
	 */
	private function range_options(): array {
		return array(
			'7'  => __( 'Last 7 days', 'localizepilot' ),
			'14' => __( 'Last 14 days', 'localizepilot' ),
			'30' => __( 'Last 30 days', 'localizepilot' ),
			'90' => __( 'Last 90 days', 'localizepilot' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private function language_options(): array {
		$settings = Plugin::instance()->get_settings();
		$source   = strtolower( (string) ( $settings['source_language'] ?? 'en' ) );
		$options  = array( '' => __( 'All Languages', 'localizepilot' ) );

		foreach ( array_merge( array( $source ), (array) ( $settings['enabled_languages'] ?? array() ) ) as $code ) {
			$code = strtolower( (string) $code );

			if ( Language_Catalog::exists( $code ) ) {
				$options[ $code ] = Language_Catalog::label( $code, 'english' );
			}
		}

		return $options;
	}
}
