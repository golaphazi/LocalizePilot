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
			$previous = self::HANDLE . '-rtl';
		}

		/*
		 * The Language Switcher screen previews the real switcher, rendered by
		 * the same class the front end uses. Without the front-end sheet the
		 * preview is unstyled markup and looks nothing like what visitors get,
		 * so that one screen loads it too.
		 */
		if ( 'language-switcher' === $screen_slug ) {
			wp_enqueue_style(
				self::HANDLE . '-switcher-preview',
				LOCALIZEPILOT_URL . 'assets/frontend.css',
				array( $previous ),
				self::version( 'assets/frontend.css' )
			);
			$previous = self::HANDLE . '-switcher-preview';
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

		$data = array(
				'screen'     => $screen_slug,
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( Ajax::NONCE ),
				'providerNonce' => wp_create_nonce( 'next_translate_test_api' ),
				'parentSlug' => Screen_Registry::PARENT_SLUG,
				'screens'    => $this->screen_map(),
				'i18n'       => array(
					'copied'      => __( 'Copied', 'localizepilot' ),
					'saving'      => __( 'Saving…', 'localizepilot' ),
					'saved'       => __( 'Changes saved.', 'localizepilot' ),
					'saveFailed'  => __( 'Changes could not be saved.', 'localizepilot' ),
					'searchFailed' => __( 'Search is temporarily unavailable.', 'localizepilot' ),
					/* translators: %s is the name of the console screen that just loaded. */
					'screenLoaded' => __( '%s screen loaded', 'localizepilot' ),
					'preview'     => __( 'Not connected yet — this is a preview of the interface.', 'localizepilot' ),
					'licenseEmpty' => __( 'Enter your license key first.', 'localizepilot' ),
					'licenseFormat' => __( 'That does not look like a license key. The format is XXXX-XXXX-XXXX-XXXX-XXXX.', 'localizepilot' ),
					'licenseNotConnected' => __( 'Key accepted. License activation is not connected in this build, so nothing was stored or verified.', 'localizepilot' ),
					'licenseCheck' => __( 'No license is stored for this site, and status checks are not connected in this build.', 'localizepilot' ),
					'loadFailed'  => __( 'That screen could not be loaded. Reloading the page.', 'localizepilot' ),
					'show'        => __( 'Show', 'localizepilot' ),
					'hide'        => __( 'Hide', 'localizepilot' ),
					'testing'     => __( 'Testing…', 'localizepilot' ),
					'testConnection' => __( 'Test connection', 'localizepilot' ),
					'unknownResponse' => __( 'The provider returned an unknown response.', 'localizepilot' ),
				),
		);

		/**
		 * Filter the console's client-side payload.
		 *
		 * Add-ons merge in their own nonces and strings here. Merge — the
		 * console's own keys, i18n included, are relied on by every screen, so
		 * a filter that replaces the array instead of adding to it breaks the
		 * console rather than extending it.
		 *
		 * @param array<string,mixed> $data        The payload.
		 * @param string              $screen_slug Console screen being loaded.
		 */
		$data = (array) apply_filters( 'localizepilot_console_data', $data, $screen_slug );

		wp_localize_script( self::HANDLE . '-core', 'localizePilotConsole', $data );

		$this->enqueue_screen_script( $screen_slug );

		/**
		 * Fires once the console's own assets are enqueued.
		 *
		 * Add-ons enqueue here with the standard WordPress functions, and are
		 * handed the handles to depend on so their CSS lands after the console
		 * cascade and their JavaScript after the console runtime.
		 *
		 * @param string $screen_slug  Console screen being loaded.
		 * @param string $style_handle Last console stylesheet handle.
		 * @param string $script_handle Last console script handle.
		 */
		do_action(
			'localizepilot_console_assets',
			$screen_slug,
			$previous,
			self::HANDLE . '-router'
		);
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
	 * A screen's own script, or null when that screen ships none.
	 *
	 * One resolver for both delivery paths — the enqueue on a full page load
	 * and the URL the client fetches on a navigation — so a screen cannot end
	 * up with behaviour one way and not the other. That symmetry is why the
	 * filter lives here rather than on either call site: an add-on screen gets
	 * its script in both cases, or in neither.
	 *
	 * @return array{url:string,version:string}|null
	 */
	private static function screen_script( string $screen_slug ): ?array {
		$relative = 'assets/console/js/screen-' . sanitize_key( $screen_slug ) . '.js';

		$script = is_readable( LOCALIZEPILOT_PATH . $relative )
			? array(
				'url'     => LOCALIZEPILOT_URL . $relative,
				'version' => self::version( $relative ),
			)
			: null;

		/**
		 * Filter the script backing one console screen.
		 *
		 * Add-ons return their own {url, version} pair for a screen they
		 * registered, or null to leave a screen without behaviour.
		 *
		 * @param array{url:string,version:string}|null $script      Resolved script.
		 * @param string                                $screen_slug Console screen slug.
		 */
		$script = apply_filters( 'localizepilot_screen_script', $script, $screen_slug );

		if ( ! is_array( $script ) || empty( $script['url'] ) ) {
			return null;
		}

		return array(
			'url'     => (string) $script['url'],
			'version' => (string) ( $script['version'] ?? LOCALIZEPILOT_VERSION ),
		);
	}

	/**
	 * The URL of a screen's own script, or an empty string when it has none.
	 *
	 * Client-side navigation uses this to pull in a screen's behaviour the
	 * first time that screen is visited.
	 */
	public static function screen_script_url( string $screen_slug ): string {
		$script = self::screen_script( $screen_slug );

		if ( null === $script ) {
			return '';
		}

		return $script['url']
			. ( false === strpos( $script['url'], '?' ) ? '?' : '&' )
			. 'ver=' . rawurlencode( $script['version'] );
	}

	/**
	 * Load a screen's own script only when that screen ships one.
	 */
	private function enqueue_screen_script( string $screen_slug ): void {
		$script = self::screen_script( $screen_slug );

		if ( null === $script ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE . '-screen-' . $screen_slug,
			$script['url'],
			array( self::HANDLE . '-router' ),
			$script['version'],
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
