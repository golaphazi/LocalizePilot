<?php
/**
 * Performance console screen.
 *
 * LocalizePilot measures nothing over time. It publishes the three events a
 * performance report is built on — localizepilot_page_processed,
 * localizepilot_cache_lookup and localizepilot_provider_request — and keeps
 * none, because keeping them
 * means a store, a retention policy and a write on every translated page view,
 * and that is the paid add-on's work.
 *
 * So this controller gathers almost nothing: the screen it feeds says what the
 * feature does and where to get it. The add-on registers its own controller
 * over this slug, which is why the slug, the sidebar position and every
 * bookmark survive installing it.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

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
		return array(
			'urls' => array(
				'languages' => Screen_Registry::url( 'languages' ),
				'providers' => Screen_Registry::url( 'providers' ),
				'cache'     => Screen_Registry::url( 'cache-management' ),
			),
		);
	}
}
