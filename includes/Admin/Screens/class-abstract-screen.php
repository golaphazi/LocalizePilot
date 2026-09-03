<?php
/**
 * Base class for console screens.
 *
 * A screen resolves the request, checks capability, and hands a plain array to
 * a template. It never emits HTML of its own.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

abstract class Abstract_Screen {
	/**
	 * The registry entry for this screen.
	 *
	 * @var array<string,mixed>
	 */
	protected array $config;

	/**
	 * @param array<string,mixed> $config Registry entry.
	 */
	public function __construct( array $config = array() ) {
		$this->config = $config;
	}

	abstract public function slug(): string;

	public function title(): string {
		return (string) ( $this->config['label'] ?? '' );
	}

	public function description(): string {
		return (string) ( $this->config['description'] ?? '' );
	}

	public function capability(): string {
		return (string) ( $this->config['capability'] ?? 'manage_options' );
	}

	/**
	 * Buttons shown at the right of the page header.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function actions(): array {
		return array();
	}

	/**
	 * Everything the screen template needs. Screens gather; templates render.
	 *
	 * @return array<string,mixed>
	 */
	/**
	 * Status pill shown at the end of the page header, as the License screen
	 * does. Empty on every screen that does not report one.
	 *
	 * @return array{label:string,tone:string}|array{}
	 */
	public function badge(): array {
		return array();
	}

	public function data(): array {
		return array();
	}

	/**
	 * Template name for this screen's body.
	 */
	public function template(): string {
		return $this->is_component_gallery() ? 'parts/kitchen-sink' : 'screens/' . $this->slug();
	}

	/**
	 * True when the reviewer has asked for the component gallery instead of
	 * the screen, by adding &lp_kitchen_sink=1 to a console URL.
	 *
	 * A build-time review aid, not a shipped surface: it renders sample content
	 * only, and is gated on the same capability as the screen itself.
	 */
	protected function is_component_gallery(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch with no side effects.
		return ! empty( $_GET['lp_kitchen_sink'] ) && current_user_can( $this->capability() );
	}

	/**
	 * Everything both the full page render and the client-side navigation
	 * response need.
	 *
	 * @return array<string,mixed>
	 */
	public function args(): array {
		return array(
			'screen'      => $this,
			'slug'        => $this->slug(),
			'title'       => $this->title(),
			'description' => $this->description(),
			'actions'     => $this->actions(),
			'badge'       => $this->badge(),
			'navigation'  => Screen_Registry::grouped(),
			'data'        => $this->data(),
		);
	}

	/** Render the console shell with this screen inside it. */
	public function render(): void {
		Template::render( 'layout/app', $this->args() );
	}

	/**
	 * Render only the swappable region — page header plus screen body.
	 *
	 * This is the exact markup client-side navigation drops into #lp-view, so
	 * a screen has one template rendered by one code path whether it arrives
	 * with a full page load or without one.
	 */
	public function view(): string {
		return Template::capture( 'parts/view', $this->args() );
	}

	/**
	 * The "View Site" button every designed screen carries in its header.
	 *
	 * @return array<string,mixed>
	 */
	protected function view_site_action(): array {
		return array(
			'label'    => __( 'View Site', 'localizepilot' ),
			'url'      => home_url( '/' ),
			'style'    => 'ghost',
			'icon'     => '',
			'external' => true,
		);
	}
}
