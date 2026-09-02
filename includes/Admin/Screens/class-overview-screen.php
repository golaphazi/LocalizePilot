<?php
/**
 * Overview console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Overview_Model;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;

final class Overview_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'overview';
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
		$model    = new Overview_Model();
		$settings = Plugin::instance()->get_settings();

		return array(
			'active'      => ! empty( $settings['enabled'] ),
			'kpis'        => $model->kpis(),
			'attention'   => $model->attention(),
			'provider'    => $model->provider(),
			'cache'       => $model->cache(),
			'coverage'    => $model->coverage(),
			'source'      => $model->source_language(),
			'recent'      => $model->recent_translations(),
			'quick_links' => $this->quick_links(),
		);
	}

	/**
	 * The quick-action row under the hero.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function quick_links(): array {
		return array(
			array(
				'icon'  => 'nav-languages',
				'label' => __( 'Add Language', 'localizepilot' ),
				'url'   => Screen_Registry::url( 'languages' ),
			),
			array(
				'icon'  => 'nav-translations',
				'label' => __( 'Review Translations', 'localizepilot' ),
				'url'   => Screen_Registry::url( 'translations' ),
			),
			array(
				'icon'  => 'nav-providers',
				'label' => __( 'Configure Provider', 'localizepilot' ),
				'url'   => Screen_Registry::url( 'providers' ),
			),
			array(
				'icon'  => 'nav-analytics',
				'label' => __( 'View Analytics', 'localizepilot' ),
				'url'   => Screen_Registry::url( 'analytics' ),
			),
		);
	}
}
