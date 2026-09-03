<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

/**
 * Option registration and the admin endpoints behind the console.
 *
 * This class used to render the whole settings UI. The console replaced every
 * one of its tabs, so what is left is the part that has no screen of its own:
 * the registered option and its sanitiser, the provider connection test, and
 * the two admin-post actions the Performance and Settings screens submit to.
 *
 * The retired page's URLs are redirected by Admin::redirect_legacy_page().
 */
final class Settings {
	private Analytics $analytics;

	public function __construct( Analytics $analytics ) {
		$this->analytics = $analytics;
	}

	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_next_translate_test_api', array( $this, 'test_api' ) );
		add_action( 'admin_post_next_translate_cache_action', array( $this, 'cache_action' ) );
		add_action( 'admin_post_localizepilot_analytics_action', array( $this, 'analytics_action' ) );
	}

	public function register(): void {
		register_setting(
			'next_translate_group',
			Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	public function sanitize( $input ): array {
		$old      = wp_parse_args( Plugin::instance()->get_settings(), Plugin::defaults() );
		$input    = is_array( $input ) ? $input : array();
		$output   = $old;
		$tab      = sanitize_key( $input['settings_tab'] ?? 'dashboard' );
		$changed  = false;

		if ( in_array( $tab, array( 'dashboard', 'providers' ), true ) ) {
			if ( 'dashboard' === $tab ) {
				$output['enabled'] = empty( $input['enabled'] ) ? 0 : 1;
			}

			$provider = sanitize_key( (string) ( $input['translation_provider'] ?? 'translatex' ) );
			$output['translation_provider'] = Provider_Catalog::exists( $provider ) ? $provider : 'translatex';

			$fallback = sanitize_key( (string) ( $input['fallback_provider'] ?? '' ) );
			$output['fallback_provider'] = Provider_Catalog::exists( $fallback ) && $fallback !== $output['translation_provider'] ? $fallback : '';

			foreach ( Provider_Catalog::all() as $provider_id => $config ) {
				$key_field = (string) ( $config['key_field'] ?? '' );
				if ( '' !== $key_field ) {
					$output[ $key_field ] = $this->sanitize_secret( $input, $old, $key_field, 'clear_' . $key_field );
				}
				$model_field = (string) ( $config['model_field'] ?? '' );
				if ( '' !== $model_field ) {
					$model = sanitize_text_field( (string) ( $input[ $model_field ] ?? $old[ $model_field ] ?? $config['default_model'] ?? '' ) );
					$output[ $model_field ] = '' !== trim( $model ) ? $model : (string) ( $config['default_model'] ?? '' );
				}
			}

			$style = sanitize_key( (string) ( $input['ai_translation_style'] ?? 'natural' ) );
			$output['ai_translation_style']   = in_array( $style, array( 'faithful', 'natural', 'marketing', 'formal' ), true ) ? $style : 'natural';
			$output['ai_custom_instructions'] = sanitize_textarea_field( (string) ( $input['ai_custom_instructions'] ?? '' ) );
			$output['ai_temperature']         = max( 0, min( 1, (float) ( $input['ai_temperature'] ?? 0.2 ) ) );
			$output['ai_max_output_tokens']   = min( 32000, max( 512, absint( $input['ai_max_output_tokens'] ?? 8192 ) ) );

			if ( 'dashboard' === $tab ) {
				$output['daily_limit_enabled'] = empty( $input['daily_limit_enabled'] ) ? 0 : 1;
				$output['daily_limit']         = min( 10000, max( 1, absint( $input['daily_limit'] ?? 10 ) ) );
			}

			$changed = true;
		} elseif ( 'languages' === $tab ) {
			$languages = array_map( 'sanitize_key', (array) ( $input['enabled_languages'] ?? array() ) );
			$languages = array_values( array_filter( $languages, static fn( $code ) => 'en' !== $code && Language_Catalog::exists( $code ) ) );
			$output['enabled_languages'] = array_values( array_unique( $languages ) );
			$changed = true;
		} elseif ( 'translation' === $tab ) {
			$output['translate_attributes']     = empty( $input['translate_attributes'] ) ? 0 : 1;
			$output['translate_internal_links'] = empty( $input['translate_internal_links'] ) ? 0 : 1;
			$output['refresh_on_source_change'] = empty( $input['refresh_on_source_change'] ) ? 0 : 1;
			$output['stale_cache_fallback']     = empty( $input['stale_cache_fallback'] ) ? 0 : 1;
			$changed = true;
		} elseif ( in_array( $tab, array( 'cache', 'performance' ), true ) ) {
			$output['cache_enabled']        = empty( $input['cache_enabled'] ) ? 0 : 1;
			$output['object_cache_enabled'] = empty( $input['object_cache_enabled'] ) ? 0 : 1;
			$output['cache_hours']          = min( 8760, max( 1, absint( $input['cache_hours'] ?? 24 ) ) );
			$changed = true;
		} elseif ( 'analytics' === $tab ) {
			$output['analytics_enabled']        = empty( $input['analytics_enabled'] ) ? 0 : 1;
			$output['analytics_retention_days'] = min( 3650, max( 7, absint( $input['analytics_retention_days'] ?? 365 ) ) );
			$changed = true;
		} elseif ( in_array( $tab, array( 'switcher', 'language-switcher' ), true ) ) {
			$output['header_switcher']  = empty( $input['header_switcher'] ) ? 0 : 1;
			$output['menu_style']       = in_array( $input['menu_style'] ?? '', array( 'dropdown', 'inline' ), true ) ? sanitize_key( $input['menu_style'] ) : 'dropdown';
			$output['menu_position']    = in_array( $input['menu_position'] ?? '', array( 'start', 'center', 'end' ), true ) ? sanitize_key( $input['menu_position'] ) : 'end';
			$output['language_label']   = in_array( $input['language_label'] ?? '', array( 'native', 'english', 'code' ), true ) ? sanitize_key( $input['language_label'] ) : 'native';
			$changed = true;
		} elseif ( 'settings' === $tab ) {
			$output['enabled']                  = empty( $input['enabled'] ) ? 0 : 1;
			$output['daily_limit_enabled']      = empty( $input['daily_limit_enabled'] ) ? 0 : 1;
			$output['daily_limit']              = min( 10000, max( 1, absint( $input['daily_limit'] ?? 10 ) ) );
			$output['translate_attributes']     = empty( $input['translate_attributes'] ) ? 0 : 1;
			$output['translate_internal_links'] = empty( $input['translate_internal_links'] ) ? 0 : 1;
			$output['refresh_on_source_change'] = empty( $input['refresh_on_source_change'] ) ? 0 : 1;
			$output['stale_cache_fallback']     = empty( $input['stale_cache_fallback'] ) ? 0 : 1;
			$output['analytics_enabled']        = empty( $input['analytics_enabled'] ) ? 0 : 1;
			$output['analytics_retention_days'] = min( 3650, max( 7, absint( $input['analytics_retention_days'] ?? 365 ) ) );
			$changed = true;
		}

		$output['source_language'] = 'en';
		// A valid form submission is not necessarily a settings change. Avoid
		// invalidating every rendered page when an administrator clicks Save
		// without modifying anything (the AJAX UI reports that as a no-op).
		if ( $changed && $output !== $old ) {
			Plugin::instance()->bump_cache_version();
			( new File_Cache( $old ) )->clear_all();
		}
		return $output;
	}

	private function sanitize_secret( array $input, array $old, string $field, string $clear_field ): string {
		if ( ! empty( $input[ $clear_field ] ) ) {
			return '';
		}
		$value = trim( (string) ( $input[ $field ] ?? '' ) );
		return '' !== $value ? sanitize_text_field( $value ) : (string) ( $old[ $field ] ?? '' );
	}

	/**
	 * Style the LocalizePilot meta boxes and the translations list table.
	 *
	 * These are ordinary WordPress admin screens, not console screens, so they
	 * get their own small sheet. The console enqueues its own assets and never
	 * loads this one.
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		$is_translation_screen = Translation_Manager::POST_TYPE === $screen->post_type;
		$is_source_editor      = in_array( $screen->post_type, array( 'post', 'page' ), true )
			&& in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_translation_screen && ! $is_source_editor ) {
			return;
		}

		$relative = 'assets/editor.css';
		$file     = LOCALIZEPILOT_PATH . $relative;
		$mtime    = is_readable( $file ) ? filemtime( $file ) : false;
		$version  = LOCALIZEPILOT_VERSION . ( false !== $mtime ? '.' . (string) $mtime : '' );

		wp_enqueue_style(
			'localizepilot-editor',
			LOCALIZEPILOT_URL . $relative,
			array(),
			$version
		);
	}

	public function test_api(): void {
		check_ajax_referer( 'next_translate_test_api', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'localizepilot' ) ), 403 );
		}

		$language = sanitize_key( wp_unslash( $_POST['language'] ?? 'es' ) );
		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? 'translatex' ) );
		$provider = Provider_Catalog::exists( $provider ) ? $provider : 'translatex';
		$settings = Plugin::instance()->get_settings();
		$settings['translation_provider'] = $provider;
		$settings['fallback_provider']    = '';

		$key       = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		$key_field = Provider_Catalog::key_field( $provider );
		if ( '' !== $key && '' !== $key_field ) {
			$settings[ $key_field ] = $key;
		}

		$model       = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );
		$model_field = Provider_Catalog::model_field( $provider );
		if ( '' !== $model && '' !== $model_field ) {
			$settings[ $model_field ] = $model;
		}

		$settings['ai_translation_style']   = sanitize_key( wp_unslash( $_POST['style'] ?? $settings['ai_translation_style'] ?? 'natural' ) );
		$settings['ai_custom_instructions'] = sanitize_textarea_field( wp_unslash( $_POST['instructions'] ?? $settings['ai_custom_instructions'] ?? '' ) );
		$temperature                        = sanitize_text_field( wp_unslash( $_POST['temperature'] ?? $settings['ai_temperature'] ?? '0.2' ) );
		$settings['ai_temperature']         = max( 0, min( 1, (float) $temperature ) );
		$settings['ai_max_output_tokens']   = min( 32000, max( 512, absint( wp_unslash( $_POST['max_tokens'] ?? $settings['ai_max_output_tokens'] ?? 8192 ) ) ) );

		try {
			$client = Client_Factory::make( $settings, false );
			$result = $client->test( Language_Catalog::exists( $language ) ? $language : 'es' );
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: translation provider name, 2: translated test response. */
						__( '%1$s connected: %2$s', 'localizepilot' ),
						Provider_Catalog::label( $provider ),
						$result
					),
				)
			);
		} catch ( \Throwable $exception ) {
			wp_send_json_error( array( 'message' => sanitize_text_field( $exception->getMessage() ) ) );
		}
	}

	public function cache_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'localizepilot' ) ); }
		check_admin_referer( 'next_translate_cache_action' );
		$cache       = new File_Cache( Plugin::instance()->get_settings() );
		$action      = sanitize_key( wp_unslash( $_GET['cache_action'] ?? '' ) );
		$item_type   = sanitize_key( wp_unslash( $_GET['cache_item_type'] ?? 'page' ) );
		$cache_page  = max( 1, absint( wp_unslash( $_GET['cache_page'] ?? 1 ) ) );
		$filter_type = sanitize_key( wp_unslash( $_GET['cache_type'] ?? 'all' ) );
		$message     = __( 'No cache action was performed.', 'localizepilot' );

		if ( 'clear_all' === $action ) {
			/* translators: %d is the number of deleted rendered cache files. */
			$message = sprintf( __( 'Cleared %d rendered cache files.', 'localizepilot' ), $cache->clear_all() );
		} elseif ( 'clear_expired' === $action ) {
			/* translators: %d is the number of deleted expired cache entries. */
			$message = sprintf( __( 'Cleared %d expired cache entries.', 'localizepilot' ), $cache->clear_expired() );
		} elseif ( 'clear_snapshots' === $action ) {
			/* translators: %d is the number of deleted translation snapshot files. */
			$message = sprintf( __( 'Cleared %d translation snapshot files.', 'localizepilot' ), $cache->clear_snapshots() );
		} elseif ( 'purge_host' === $action ) {
			File_Cache::purge_external_caches();
			$message = __( 'Requested a purge of the SiteGround Dynamic Cache and any other detected host or plugin page cache.', 'localizepilot' );
		} elseif ( 'delete' === $action ) {
			$key     = sanitize_text_field( wp_unslash( $_GET['cache_key'] ?? '' ) );
			$deleted = $cache->delete_history_item( $item_type, $key );
			$message = $deleted ? __( 'Cache entry deleted.', 'localizepilot' ) : __( 'Cache entry was not found.', 'localizepilot' );
		}
		/*
		 * Two console screens offer these actions, so the caller says which one
		 * to return to. The value is checked against a fixed list rather than
		 * trusted, since it arrives in the URL.
		 */
		$return_screen = sanitize_key( wp_unslash( $_GET['return_screen'] ?? '' ) );

		if ( ! in_array( $return_screen, array( 'performance', 'cache-management' ), true ) ) {
			$return_screen = 'cache-management';
		}

		$redirect = add_query_arg(
			array(
				'cache_type'                  => $filter_type,
				'paged'                       => $cache_page,
				'localizepilot_cache_message' => $message,
			),
			\LocalizePilot\Admin\Screen_Registry::url( $return_screen )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public function analytics_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'localizepilot' ) );
		}

		check_admin_referer( 'localizepilot_analytics_action' );
		$action  = sanitize_key( wp_unslash( $_GET['analytics_action'] ?? '' ) );
		$message = __( 'No analytics action was performed.', 'localizepilot' );

		if ( 'clear_all' === $action ) {
			$deleted = $this->analytics->clear_all();
			/* translators: %d: Number of deleted analytics events. */
			$message = sprintf( __( 'Deleted %d analytics events.', 'localizepilot' ), $deleted );
		}

		$redirect = add_query_arg(
			'localizepilot_analytics_message',
			$message,
			\LocalizePilot\Admin\Screen_Registry::url( 'settings' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
