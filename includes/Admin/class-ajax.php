<?php
/**
 * Console AJAX endpoints.
 *
 * Responses carry server-rendered HTML fragments, never JSON that the client
 * has to turn into markup. A screen therefore has one template rendered by one
 * code path, whether it arrives with a full page load or through client-side
 * navigation.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Ajax {
	public const NONCE = 'localizepilot_console';

	public function hooks(): void {
		add_action( 'wp_ajax_localizepilot_screen', array( $this, 'screen' ) );
		add_action( 'wp_ajax_localizepilot_url_drawer', array( $this, 'url_drawer' ) );
		add_action( 'wp_ajax_localizepilot_search', array( $this, 'search' ) );
		add_action( 'wp_ajax_localizepilot_save_settings', array( $this, 'save_settings' ) );
	}

	/** Return the command-palette result list. */
	public function search(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to search that.', 'localizepilot' ) ), 403 );
		}

		$query   = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$results = ( new Data\Global_Search() )->results( $query );

		wp_send_json_success(
			array(
				'html' => Template::capture(
					'parts/global-search-results',
					array( 'query' => $query, 'results' => $results )
				),
			)
		);
	}

	/** Save any console Settings API form without reloading the admin page. */
	public function save_settings(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to change those settings.', 'localizepilot' ) ), 403 );
		}

		$input = wp_unslash( $_POST[ \LocalizePilot\Plugin::OPTION ] ?? array() );

		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'message' => __( 'The settings payload was invalid.', 'localizepilot' ) ), 400 );
		}

		$tab = sanitize_key( (string) ( $input['settings_tab'] ?? '' ) );

		/**
		 * Filter the settings sections this endpoint will save.
		 *
		 * The allowlist is what stops an arbitrary payload being written to
		 * the settings option, so an add-on adds its own section name here
		 * rather than the endpoint accepting anything.
		 *
		 * @param array<int,string> $tabs Section names.
		 */
		$tabs = (array) apply_filters(
			'localizepilot_settings_tabs',
			array( 'providers', 'languages', 'translation', 'cache', 'performance', 'analytics', 'switcher', 'language-switcher', 'settings', 'dashboard' )
		);

		if ( ! in_array( $tab, array_map( 'sanitize_key', $tabs ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'That settings section could not be saved.', 'localizepilot' ) ), 400 );
		}

		$input['settings_tab'] = $tab;
		$before                = get_option( \LocalizePilot\Plugin::OPTION, array() );

		// register_setting() attaches Settings::sanitize() to update_option(), so
		// AJAX and options.php pass through exactly the same validation path.
		update_option( \LocalizePilot\Plugin::OPTION, $input );
		$after = get_option( \LocalizePilot\Plugin::OPTION, array() );

		wp_send_json_success(
			array(
				'changed' => $before !== $after,
				'message' => $before !== $after
					? __( 'Changes saved.', 'localizepilot' )
					: __( 'Settings are already up to date.', 'localizepilot' ),
			)
		);
	}

	/**
	 * Return the URL drawer for one page.
	 *
	 * The drawer is fetched rather than shipped with the table: rendering one
	 * per row costs several hundred kilobytes of markup for panels almost none
	 * of which are opened. The same partial renders it either way, and the row
	 * action stays a real link, so with no JavaScript the browser follows it
	 * and the screen renders the same drawer already open.
	 */
	public function url_drawer(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to view that.', 'localizepilot' ) ),
				403
			);
		}

		$id         = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		$repository = new Data\Url_Repository();
		$row        = $id > 0 ? $repository->row( $id ) : null;

		if ( null === $row ) {
			wp_send_json_error(
				array( 'message' => __( 'That URL could not be found.', 'localizepilot' ) ),
				404
			);
		}

		wp_send_json_success(
			array(
				'id'   => $id,
				'html' => Template::capture(
					'parts/url-drawer',
					$repository->detail( $row ) + array( 'open' => true )
				),
			)
		);
	}

	/**
	 * Return one console screen's swappable region.
	 */
	public function screen(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		$slug   = sanitize_key( wp_unslash( $_POST['screen'] ?? '' ) );
		$config = '' !== $slug ? Screen_Registry::get( $slug ) : null;

		if ( null === $config ) {
			wp_send_json_error(
				array( 'message' => __( 'That screen does not exist.', 'localizepilot' ) ),
				404
			);
		}

		if ( ! current_user_can( (string) $config['capability'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to view that screen.', 'localizepilot' ) ),
				403
			);
		}

		$this->apply_screen_query();

		$class = (string) $config['class'];

		if ( ! class_exists( $class ) || ! is_subclass_of( $class, Screens\Abstract_Screen::class ) ) {
			wp_send_json_error(
				array( 'message' => __( 'That screen could not be loaded.', 'localizepilot' ) ),
				500
			);
		}

		$screen = new $class( $config );

		wp_send_json_success(
			array(
				'slug'          => $slug,
				'title'         => $screen->title(),
				'documentTitle' => sprintf(
					/* translators: 1: console screen name, 2: site name. */
					__( '%1$s ‹ LocalizePilot — %2$s', 'localizepilot' ),
					$screen->title(),
					get_bloginfo( 'name' )
				),
				'url'           => (string) $config['url'],
				'menuSlug'      => (string) $config['menu_slug'],
				'script'        => Assets::screen_script_url( $slug ),
				'html'          => $screen->view(),
			)
		);
	}

	/**
	 * Rehydrate a filtered screen's GET values from the SPA request.
	 *
	 * Only read-only screen arguments are accepted. The screen controllers still
	 * apply their own type-specific sanitization before a repository sees them.
	 */
	private function apply_screen_query(): void {
		$query = (string) wp_unslash( $_POST['query'] ?? '' );

		if ( '' === $query || strlen( $query ) > 2048 ) {
			return;
		}

		parse_str( ltrim( $query, '?' ), $values );

		/**
		 * Filter the GET arguments a navigation request may carry through.
		 *
		 * These are rehydrated into $_GET for the screen controller to read,
		 * so the list must stay read-only view state: a filter, a sort, a page
		 * number. Anything that acts belongs behind its own nonce-checked
		 * endpoint, not here.
		 *
		 * @param array<int,string> $allowed Argument names.
		 */
		$allowed = (array) apply_filters(
			'localizepilot_screen_query_args',
			array(
				's', 'language', 'status', 'type', 'issues', 'order', 'paged',
				'range', 'url', 'view', 'cache_type', 'cache_page', 'cache_item_type',
			)
		);

		foreach ( array_map( 'sanitize_key', $allowed ) as $key ) {
			if ( ! isset( $values[ $key ] ) || is_array( $values[ $key ] ) ) {
				continue;
			}

			$_GET[ $key ] = sanitize_text_field( (string) $values[ $key ] );
		}
	}
}
