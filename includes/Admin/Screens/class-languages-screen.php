<?php
/**
 * Languages console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Language_Stats;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Language_Catalog;

defined( 'ABSPATH' ) || exit;

final class Languages_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'languages';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array(
			$this->view_site_action(),
			array(
				'label' => __( 'Add Language', 'localizepilot' ),
				'url'   => Screen_Registry::url( $this->slug() ) . '#lp-available-languages',
				'style' => 'primary',
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$stats     = new Language_Stats();
		$enabled   = $stats->enabled();
		$available = $stats->available();

		return array(
			'enabled'       => $enabled,
			'available'     => $available,
			'catalog_total' => count( Language_Catalog::all() ),
			'coverage'      => $stats->average_coverage(),
			'translated'    => $stats->translated_count(),
			'urls'          => $stats->example_urls(),
			'base_url'      => Screen_Registry::url( $this->slug() ),
		);
	}
}
