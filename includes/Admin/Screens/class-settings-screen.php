<?php
/**
 * Settings console screen.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Data\Settings_Model;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Translation_Manager;

defined( 'ABSPATH' ) || exit;

final class Settings_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'settings';
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message set by the verified admin-post redirect.
		$message = sanitize_text_field( wp_unslash( $_GET['localizepilot_analytics_message'] ?? '' ) );

		$clear_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'           => 'localizepilot_analytics_action',
					'analytics_action' => 'clear_all',
					'return_screen'    => 'settings',
				),
				admin_url( 'admin-post.php' )
			),
			'localizepilot_analytics_action'
		);

		return array(
			'settings'          => $model->settings(),
			'content'           => $model->content(),
			'usage'             => $model->usage(),
			'analytics_message' => $message,
			'clear_analytics'    => $clear_url,
			'translations_url'  => admin_url( 'edit.php?post_type=' . Translation_Manager::POST_TYPE ),
			'base_url'          => Screen_Registry::url( $this->slug() ),
		);
	}
}
