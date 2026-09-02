<?php
/**
 * Providers console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Settings_Model;
use LocalizePilot\Admin\Screen_Registry;

defined( 'ABSPATH' ) || exit;

final class Providers_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'providers';
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
		$model = new Settings_Model();

		return array(
			'settings'         => $model->settings(),
			'providers'        => $model->providers(),
			'provider_options' => $model->provider_options(),
			'languages'        => $model->languages(),
			'usage'            => $model->usage(),
			'base_url'         => Screen_Registry::url( $this->slug() ),
		);
	}
}
