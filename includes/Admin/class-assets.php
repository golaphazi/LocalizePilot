<?php
/**
 * Console stylesheet and script registration.
 *
 * Everything is enqueued per screen so no screen pays for another screen's
 * behaviour. There is no build step and no runtime dependency.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Assets {
	private const HANDLE = 'localizepilot-console';

	/**
	 * Stylesheets, in cascade order.
	 *
	 * @var array<int,string>
	 */
	private const STYLES = array( 'tokens', 'base', 'layout', 'components' );

	public function enqueue( string $screen_slug ): void {
		$this->enqueue_fonts();

		$previous = '';
		foreach ( self::STYLES as $style ) {
			$handle = self::HANDLE . '-' . $style;
			wp_enqueue_style(
				$handle,
				LOCALIZEPILOT_URL . 'assets/console/css/' . $style . '.css',
				'' !== $previous ? array( $previous ) : array(),
				self::version( 'assets/console/css/' . $style . '.css' )
			);
			$previous = $handle;
		}

		/*
		 * The sheets above are written with logical properties, so they mirror
		 * on their own. This adds only what logical properties cannot express —
		 * directional artwork — and loads last so it wins.
		 */
		if ( is_rtl() ) {
			wp_enqueue_style(
				self::HANDLE . '-rtl',
				LOCALIZEPILOT_URL . 'assets/console/css/rtl.css',
				array( $previous ),
				self::version( 'assets/console/css/rtl.css' )
			);
		}

		wp_enqueue_script(
			self::HANDLE . '-core',
			LOCALIZEPILOT_URL . 'assets/console/js/core.js',
			array(),
			self::version( 'assets/console/js/core.js' ),
			true
		);

		wp_enqueue_script(
			self::HANDLE . '-ui',
			LOCALIZEPILOT_URL . 'assets/console/js/ui.js',
			array( self::HANDLE . '-core' ),
			self::version( 'assets/console/js/ui.js' ),
			true
		);

		wp_enqueue_script(
			self::HANDLE . '-router',
			LOCALIZEPILOT_URL . 'assets/console/js/router.js',
			array( self::HANDLE . '-ui' ),
			self::version( 'assets/console/js/router.js' ),
			true
		);

		wp_localize_script(
			self::HANDLE . '-core',
			'localizePilotConsole',
			array(
				'screen'     => $screen_slug,
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( Ajax::NONCE ),
				'providerNonce' => wp_create_nonce( 'next_translate_test_api' ),
				'parentSlug' => Screen_Registry::PARENT_SLUG,
				'screens'    => $this->screen_map(),
				'i18n'       => array(
					'copied'      => __( 'Copied', 'localizepilot' ),
					/* translators: %s is the name of the console screen that just loaded. */
					'screenLoaded' => __( '%s screen loaded', 'localizepilot' ),
					'preview'     => __( 'Not connected yet — this is a preview of the interface.', 'localizepilot' ),
					'loadFailed'  => __( 'That screen could not be loaded. Reloading the page.', 'localizepilot' ),
					'show'        => __( 'Show', 'localizepilot' ),
					'hide'        => __( 'Hide', 'localizepilot' ),
					'testing'     => __( 'Testing…', 'localizepilot' ),
					'testConnection' => __( 'Test connection', 'localizepilot' ),
					'unknownResponse' => __( 'The provider returned an unknown response.', 'localizepilot' ),
				),
			)
		);

		$this->enqueue_screen_script( $screen_slug );
	}

	/**
	 * The routing table the client navigates with — slug, page URL, and the
	 * WordPress menu slug so the admin menu highlight can follow along.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function screen_map(): array {
		$map = array();

		foreach ( Screen_Registry::all() as $slug => $screen ) {
			if ( ! current_user_can( (string) $screen['capability'] ) ) {
				continue;
			}

			$map[ $slug ] = array(
				'label'    => (string) $screen['label'],
				'url'      => (string) $screen['url'],
				'menuSlug' => (string) $screen['menu_slug'],
			);
		}

		return $map;
	}

	/**
	 * The URL of a screen's own script, or an empty string when it has none.
	 *
	 * Client-side navigation uses this to pull in a screen's behaviour the
	 * first time that screen is visited.
	 */
	public static function screen_script_url( string $screen_slug ): string {
		$relative = 'assets/console/js/screen-' . $screen_slug . '.js';

		return is_readable( LOCALIZEPILOT_PATH . $relative )
			? LOCALIZEPILOT_URL . $relative . '?ver=' . rawurlencode( self::version( $relative ) )
			: '';
	}

	/**
	 * Load a screen's own script only when that screen ships one.
	 */
	private function enqueue_screen_script( string $screen_slug ): void {
		$relative = 'assets/console/js/screen-' . $screen_slug . '.js';

		if ( ! is_readable( LOCALIZEPILOT_PATH . $relative ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE . '-screen-' . $screen_slug,
			LOCALIZEPILOT_URL . $relative,
			array( self::HANDLE . '-router' ),
			self::version( $relative ),
			true
		);
	}

	/**
	 * Self-hosted faces only. WordPress.org guidelines rule out loading fonts
	 * from a third-party host, and the stylesheet is skipped entirely when the
	 * files are absent so a missing face never costs a 404 — the fallback stack
	 * in tokens.css takes over.
	 */
	private function enqueue_fonts(): void {
		if ( ! is_readable( LOCALIZEPILOT_PATH . 'assets/console/css/fonts.css' ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE . '-fonts',
			LOCALIZEPILOT_URL . 'assets/console/css/fonts.css',
			array(),
			self::version( 'assets/console/css/fonts.css' )
		);
	}

	/**
	 * Include the file modification time in the cache key. Console files are
	 * edited without a build step, so relying on the release version alone can
	 * leave an administrator running old CSS or JavaScript after an update.
	 */
	private static function version( string $relative ): string {
		$file  = LOCALIZEPILOT_PATH . ltrim( $relative, '/\\' );
		$mtime = is_readable( $file ) ? filemtime( $file ) : false;

		return LOCALIZEPILOT_VERSION . ( false !== $mtime ? '.' . (string) $mtime : '' );
	}
}
