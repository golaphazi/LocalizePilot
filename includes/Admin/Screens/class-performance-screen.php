<?php
/**
 * Performance console screen.
 *
 * Rebuilt to the Figma design, which reports on rendering speed, provider
 * response and cache efficiency.
 *
 * None of those are measured. The plugin times nothing — there is no render
 * timer, no provider stopwatch, and no hit/miss counter anywhere in it. So
 * every duration, rate and score this screen shows is gated through Preview
 * and rendered as "not available" rather than as an invented figure.
 *
 * What is real: the language list and its translation counts, the configured
 * providers and which one is selected, and the cache summary. Those come from
 * the existing models and are shown as themselves.
 *
 * Cache configuration and the generated-file list moved to Cache Management
 * when the design split the two screens.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Language_Stats;
use LocalizePilot\Admin\Data\Performance_Model;
use LocalizePilot\Admin\Data\Settings_Model;
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
		return array(
			$this->view_site_action(),
			array(
				/*
				 * A real refresh: the screen re-reads the cache summary on
				 * every request, so returning to its own URL genuinely
				 * re-reads what is measurable.
				 */
				'label' => __( 'Refresh Data', 'localizepilot' ),
				'url'   => Screen_Registry::url( $this->slug() ),
				'style' => 'primary',
				'icon'  => '',
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$model    = new Performance_Model();
		$stats    = $model->stats();
		$settings = new Settings_Model();

		return array(
			'settings'   => $model->settings(),
			'stats'      => $stats,
			'host_cache' => $model->host_cache(),
			'languages'  => ( new Language_Stats() )->enabled(),
			'providers'  => $this->providers( $settings ),
			'urls'       => array(
				'languages' => Screen_Registry::url( 'languages' ),
				'providers' => Screen_Registry::url( 'providers' ),
				'cache'     => Screen_Registry::url( 'cache-management' ),
			),
			'base_url'   => Screen_Registry::url( $this->slug() ),
		);
	}

	/**
	 * Providers that are actually set up, with the connection state the plugin
	 * really knows: whether a key is stored, and which one is in use.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function providers( Settings_Model $settings ): array {
		$rows = array();

		foreach ( $settings->providers() as $provider ) {
			if ( empty( $provider['configured'] ) && empty( $provider['selected'] ) ) {
				continue;
			}

			$rows[] = array(
				'id'     => (string) $provider['id'],
				'label'  => (string) $provider['label'],
				'mark'   => (string) $provider['mark'],
				'state'  => ! empty( $provider['selected'] )
					? __( 'Active', 'localizepilot' )
					: __( 'Connected', 'localizepilot' ),
				'tone'   => ! empty( $provider['selected'] ) ? 'success' : 'info',
				'ready'  => ! empty( $provider['configured'] ),
			);
		}

		return $rows;
	}
}
