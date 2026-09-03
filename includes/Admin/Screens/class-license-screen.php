<?php
/**
 * License Activation console screen.
 *
 * The plugin has no licensing backend: nothing stores a key, calls a licence
 * server, or gates a feature on one. So this screen renders the design and
 * reports the only honest state there is — inactive — with the activation form
 * gated through Preview rather than pretending to submit somewhere.
 *
 * The one real value here is the site host, which the design shows and which
 * WordPress already knows.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

defined( 'ABSPATH' ) || exit;

final class License_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'license';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array();
	}

	/**
	 * @return array{label:string,tone:string}
	 */
	public function badge(): array {
		return array(
			'label' => __( 'License inactive', 'localizepilot' ),
			'tone'  => 'warning',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		return array(
			'status'   => array(
				'active' => false,
				'label'  => __( 'License inactive', 'localizepilot' ),
				'badge'  => __( 'Inactive', 'localizepilot' ),
			),
			'details'  => array(
				array(
					'label' => __( 'Plan', 'localizepilot' ),
					'value' => __( 'Free / No active license', 'localizepilot' ),
				),
				array(
					'label' => __( 'License key', 'localizepilot' ),
					'value' => __( 'Not activated', 'localizepilot' ),
				),
				array(
					'label' => __( 'Site', 'localizepilot' ),
					'value' => $this->site_host(),
					'code'  => true,
				),
				array(
					'label' => __( 'Last checked', 'localizepilot' ),
					'value' => __( 'Never', 'localizepilot' ),
				),
			),
			'features' => array(
				__( 'Advanced translation providers', 'localizepilot' ),
				__( 'Performance optimization', 'localizepilot' ),
				__( 'Advanced analytics & reports', 'localizepilot' ),
				__( 'Priority support', 'localizepilot' ),
			),
			/*
			 * /docs/ and /help/ are the paths the sidebar already links to.
			 * The other two are the conventional siblings; they are the only
			 * invented values on this screen.
			 */
			'links'    => array(
				'plans'         => 'https://localizepilot.com/pricing/',
				'account'       => 'https://localizepilot.com/account/',
				'documentation' => 'https://localizepilot.com/docs/',
				'support'       => 'https://localizepilot.com/help/',
			),
		);
	}

	/**
	 * The host the licence would be bound to.
	 */
	private function site_host(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return '' !== $host ? $host : (string) home_url( '/' );
	}
}
