<?php
/**
 * Console bootstrap: menu registration, screen dispatch, assets, and notice
 * suppression within the standard WordPress admin chrome.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Admin {
	/**
	 * The page slug the settings screen used before the console replaced it.
	 *
	 * Kept only so its bookmarks can be redirected; nothing registers it.
	 */
	private const LEGACY_PAGE = 'localizepilot-legacy';

	/**
	 * Where each retired settings tab went.
	 *
	 * "Dashboard & API" mapped to the console's own dashboard rather than to
	 * Providers: it mixed read-only stats with provider configuration, and
	 * Overview is both the closer name and the safer landing for a bookmark
	 * whose intent is unknowable — its provider card links on to Providers.
	 *
	 * @var array<string,string>
	 */
	private const LEGACY_TABS = array(
		'dashboard'   => 'overview',
		'languages'   => 'languages',
		'translation' => 'settings',
		'analytics'   => 'analytics',
		'cache'       => 'performance',
		'switcher'    => 'language-switcher',
	);

	private static ?Admin $instance = null;

	private Assets $assets;

	private Ajax $ajax;

	private bool $buffering_notices = false;

	public static function instance(): Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->assets = new Assets();
		$this->ajax   = new Ajax();
	}

	public function hooks(): void {
		$this->ajax->hooks();

		/*
		 * WordPress rejects unregistered plugin pages while it builds the admin
		 * menu, before admin_init fires. Redirect at the start of admin_menu so
		 * retired bookmarks are handled before that access guard runs.
		 */
		add_action( 'admin_menu', array( $this, 'redirect_legacy_page' ), -PHP_INT_MAX );

		// Priority 5 so the parent menu exists before anything attaches a submenu.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		/* Suppress admin notices only inside LocalizePilot's console screens. */
		add_action( 'admin_notices', array( $this, 'start_notice_suppression' ), -PHP_INT_MAX );
		add_action( 'all_admin_notices', array( $this, 'finish_notice_suppression' ), 9999 );
	}

	/**
	 * Send the retired settings URLs to the console screen that replaced them.
	 *
	 * Two shapes are retired: the relocated settings page, and the old
	 * top-level page carrying a ?tab= argument. The second has to be matched on
	 * the tab, because that slug now belongs to the console's Overview and a
	 * bare visit to it is not a legacy URL at all.
	 *
	 * A 302 rather than a 301: the mapping is a judgement call, and a browser
	 * that has cached a permanent redirect is painful to correct.
	 */
	public function redirect_legacy_page(): void {
		$request_method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );

		if ( 'GET' !== $request_method || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only URL match on a GET navigation.
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( self::LEGACY_PAGE !== $page && ! ( Screen_Registry::PARENT_SLUG === $page && '' !== $tab ) ) {
			return;
		}

		// An unrecognised tab on the retired page still belongs on Settings;
		// on the parent slug it is someone else's argument, so leave it be.
		$target = self::LEGACY_TABS[ $tab ] ?? ( self::LEGACY_PAGE === $page ? 'settings' : '' );

		if ( '' === $target ) {
			return;
		}

		$screen = Screen_Registry::get( $target );

		if ( null === $screen || ! current_user_can( (string) $screen['capability'] ) ) {
			return;
		}

		wp_safe_redirect( Screen_Registry::url( $target ), 302 );
		exit;
	}

	/**
	 * Register the top-level console page plus one submenu per screen. Every
	 * screen gets a real page slug, so links are deep-linkable and each screen
	 * has its own $hook_suffix for precise asset loading.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'LocalizePilot', 'localizepilot' ),
			__( 'LocalizePilot', 'localizepilot' ),
			'manage_options',
			Screen_Registry::PARENT_SLUG,
			array( $this, 'render' ),
			$this->menu_icon(),
			58
		);

		foreach ( Screen_Registry::all() as $screen ) {
			add_submenu_page(
				Screen_Registry::PARENT_SLUG,
				(string) $screen['label'],
				(string) $screen['label'],
				(string) $screen['capability'],
				(string) $screen['menu_slug'],
				array( $this, 'render' )
			);
		}
	}

	private function menu_icon(): string {
		return is_readable( LOCALIZEPILOT_PATH . 'assets/menu-icon.png' )
			? LOCALIZEPILOT_URL . 'assets/menu-icon.png'
			: 'dashicons-translation';
	}

	/**
	 * The console screen slug for the current request, or an empty string when
	 * this is not a console screen.
	 */
	public function current_slug(): string {
		if ( ! is_admin() ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing parameter.
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );

		if ( '' === $page ) {
			return '';
		}

		return Screen_Registry::slug_from_menu( $page );
	}

	public function is_console_screen(): bool {
		return '' !== $this->current_slug();
	}

	/**
	 * Resolve the current screen controller.
	 */
	private function screen(): ?Screens\Abstract_Screen {
		$slug   = $this->current_slug();
		$config = '' !== $slug ? Screen_Registry::get( $slug ) : null;

		if ( null === $config ) {
			return null;
		}

		$class = (string) ( $config['class'] ?? '' );

		if ( '' === $class || ! class_exists( $class ) || ! is_subclass_of( $class, Screens\Abstract_Screen::class ) ) {
			return null;
		}

		return new $class( $config );
	}

	public function render(): void {
		$screen = $this->screen();

		if ( null === $screen ) {
			return;
		}

		if ( ! current_user_can( $screen->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'localizepilot' ), 403 );
		}

		$screen->render();
	}

	public function enqueue_assets(): void {
		$slug = $this->current_slug();

		if ( '' === $slug ) {
			return;
		}

		$this->assets->enqueue( $slug );
	}

	/**
	 * Marks the document so console styles remain scoped to LocalizePilot and
	 * cannot leak onto other plugins' screens.
	 *
	 * @param string $classes Space-separated body classes.
	 */
	public function body_class( $classes ): string {
		$classes = is_string( $classes ) ? $classes : '';

		if ( ! $this->is_console_screen() ) {
			return $classes;
		}

		return trim( $classes . ' localizepilot-console lp-screen-' . $this->current_slug() );
	}

	/**
	 * WordPress prints admin notices before the page callback. Buffer only the
	 * two notice actions and discard their output on LocalizePilot screens.
	 * Starting in in_admin_header would also swallow #wpbody and
	 * #wpbody-content, breaking the standard WordPress document hierarchy.
	 */
	public function start_notice_suppression(): void {
		if ( ! $this->is_console_screen() ) {
			return;
		}

		$this->buffering_notices = true;
		ob_start();
	}

	public function finish_notice_suppression(): void {
		if ( ! $this->buffering_notices ) {
			return;
		}

		$this->buffering_notices = false;
		ob_end_clean();
	}
}
