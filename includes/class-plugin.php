<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION = 'next_translate_settings';
	private const CACHE_VERSION_OPTION = 'next_translate_cache_version';
	private static ?Plugin $instance = null;
	private Router $router;
	private Usage_Limiter $limiter;
	private Translation_Manager $translations;
	private array $settings = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function defaults(): array {
		return array(
			'enabled'                  => 1,
			'source_language'          => 'en',
			'enabled_languages'        => array( 'de', 'fr', 'es', 'pt', 'ar', 'da' ),
			'translation_provider'     => 'translatex',
			'translatex_api_key'       => '',
			'google_api_key'           => '',
			'daily_limit_enabled'      => 1,
			'daily_limit'              => 10,
			'cache_enabled'            => 1,
			'object_cache_enabled'     => 1,
			'cache_hours'              => 24,
			'refresh_on_source_change' => 0,
			'stale_cache_fallback'     => 1,
			'header_switcher'          => 1,
			'menu_style'               => 'dropdown',
			'menu_position'            => 'end',
			'language_label'           => 'native',
			'translate_attributes'     => 1,
			'translate_internal_links' => 1,
		);
	}

	public function boot(): void {
		$this->limiter      = new Usage_Limiter();
		$this->router       = new Router();
		$this->translations = new Translation_Manager( $this->router, $this->limiter );
		$this->settings     = $this->get_settings();

		$this->translations->hooks();
		add_filter( 'do_parse_request', array( $this->router, 'before_parse_request' ), 0, 3 );
		( new Settings( $this->limiter ) )->hooks();

		add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_head', array( $this, 'output_hreflang' ), 2 );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ), 10, 2 );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical_url' ) );
		add_filter( 'wpseo_canonical', array( $this, 'filter_seo_url' ) );
		add_filter( 'wpseo_opengraph_url', array( $this, 'filter_seo_url' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_seo_url' ) );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public static function activate(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
		if ( false === get_option( self::CACHE_VERSION_OPTION, false ) ) {
			add_option( self::CACHE_VERSION_OPTION, 1, '', false );
		}

		$cache = new File_Cache( self::defaults() );
		$cache->is_writable();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public function get_settings(): array {
		$options = get_option( self::OPTION, array() );
		$options = is_array( $options ) ? $options : array();

		// Migrate keys from versions 1.0.x.
		if ( empty( $options['translatex_api_key'] ) ) {
			$options['translatex_api_key'] = (string) ( $options['custom_api_key'] ?? $options['limited_api_key'] ?? '' );
		}
		if ( empty( $options['translation_provider'] ) ) {
			$options['translation_provider'] = 'translatex';
		}

		return wp_parse_args( $options, self::defaults() );
	}

	public function bump_cache_version(): void {
		$version = max( 1, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) );
		update_option( self::CACHE_VERSION_OPTION, $version + 1, false );
	}

	public function enqueue_frontend_assets(): void {
		if ( empty( $this->get_settings()['enabled'] ) ) {
			return;
		}
		wp_enqueue_style( 'localizepilot', NEXT_TRANSLATE_URL . 'assets/frontend.css', array(), NEXT_TRANSLATE_VERSION );
	}

	public function start_buffer(): void {
		$this->settings = $this->get_settings();
		if ( ! $this->should_process_request() ) {
			return;
		}
		ob_start( array( $this, 'process_output' ) );
	}

	public function process_output( string $html ): string {
		if ( '' === trim( $html ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$current   = $this->router->current_language();
		$source    = $this->router->source_language();
		$processed = $html;

		if ( $current !== $source ) {
			$cache       = new File_Cache( $this->settings );
			$identity    = remove_query_arg( 'next_translate_refresh', $this->router->language_url( $source ) );
			$fingerprint = $this->cache_fingerprint();
			$source_hash = hash( 'sha256', $html . '|' . $fingerprint );
			$post_id     = is_singular( array( 'post', 'page' ) ) ? get_queried_object_id() : 0;
			$cache_key   = $post_id
				? $cache->make_post_key( $post_id, $current )
				: $cache->make_key( $identity, $current, $fingerprint );
			$force       = isset( $_GET['next_translate_refresh'] ) && current_user_can( 'manage_options' );
			$cached      = $force ? null : $cache->get( $cache_key, $source_hash );

			if ( is_string( $cached ) && '' !== $cached ) {
				$processed = $cached;
			} else {
				$limit_enabled = ! empty( $this->settings['daily_limit_enabled'] );
				$daily_limit   = max( 1, absint( $this->settings['daily_limit'] ?? 10 ) );

				if ( $limit_enabled && ! $this->limiter->can_translate( $daily_limit ) ) {
					$stale = ! empty( $this->settings['stale_cache_fallback'] ) ? $cache->get_stale( $cache_key ) : null;
					$processed = is_string( $stale ) ? $stale : $html . "\n<!-- LocalizePilot daily limit reached. -->";
				} else {
					try {
						$client     = Client_Factory::make( $this->settings );
						$protected  = $this->translations->protected_strings_for_current_request();
						$translator = new HTML_Translator( $client, $this->router, $this->settings, $protected );
						$processed  = $translator->translate_document( $html, $current );

						$cache->set(
							$cache_key,
							$processed,
							array(
								'url'         => $identity,
								'language'    => $current,
								'provider'    => $client->provider(),
								'source_hash' => $source_hash,
								'post_id'     => $post_id,
								'cache_file'  => $post_id ? $cache_key . '.html' : '',
							)
						);

						if ( $limit_enabled ) {
							$this->limiter->increment( $daily_limit );
						}
					} catch ( \Throwable $exception ) {
						error_log( '[LocalizePilot] ' . $exception->getMessage() );
						$stale = ! empty( $this->settings['stale_cache_fallback'] ) ? $cache->get_stale( $cache_key ) : null;
						$processed = is_string( $stale ) ? $stale : $html . "\n<!-- LocalizePilot error: " . esc_html( $exception->getMessage() ) . " -->";
					}
				}
			}
		}

		$switcher = new Language_Switcher( $this->router, $this->settings );
		return $switcher->inject( $processed );
	}

	public function output_hreflang(): void {
		if ( ! $this->should_process_request() ) {
			return;
		}
		foreach ( $this->router->enabled_languages() as $language ) {
			printf( "<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n", esc_attr( $language ), esc_url( $this->router->language_url( $language ) ) );
		}
		printf( "<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n", esc_url( $this->router->language_url( $this->router->source_language() ) ) );
	}

	public function disable_canonical_redirect( $redirect_url, $requested_url ) {
		return $this->router->is_translated_request() ? false : $redirect_url;
	}

	public function filter_canonical_url( $url ) {
		return $this->router->is_translated_request() ? $this->router->language_url( $this->router->current_language() ) : $url;
	}

	public function filter_seo_url( $url ) {
		return $this->router->is_translated_request() ? $this->router->language_url( $this->router->current_language() ) : $url;
	}

	public function filter_language_attributes( string $output ): string {
		if ( empty( $this->settings['enabled'] ) ) {
			return $output;
		}
		$language  = $this->router->current_language();
		$direction = Language_Catalog::is_rtl( $language ) ? 'rtl' : 'ltr';
		$output    = preg_replace( '/lang=("|\')[^"\']*("|\')/i', 'lang="' . esc_attr( $language ) . '"', $output ) ?: $output;
		if ( preg_match( '/dir=("|\')[^"\']*("|\')/i', $output ) ) {
			$output = preg_replace( '/dir=("|\')[^"\']*("|\')/i', 'dir="' . esc_attr( $direction ) . '"', $output ) ?: $output;
		} else {
			$output .= ' dir="' . esc_attr( $direction ) . '"';
		}
		return $output;
	}

	public function admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['next_translate_cache_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['next_translate_cache_message'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		$options = $this->get_settings();
		if ( empty( get_option( 'permalink_structure', '' ) ) ) {
			echo '<div class="notice notice-error"><p>' . wp_kses_post( sprintf( __( 'LocalizePilot language URLs require pretty permalinks. <a href="%s">Open Permalink Settings</a> and click Save Changes.', 'localizepilot' ), esc_url( admin_url( 'options-permalink.php' ) ) ) ) . '</p></div>';
		}

		$provider = (string) ( $options['translation_provider'] ?? 'translatex' );
		$key      = 'google' === $provider ? (string) ( $options['google_api_key'] ?? '' ) : (string) ( $options['translatex_api_key'] ?? '' );
		if ( ! empty( $options['enabled'] ) && '' === trim( $key ) ) {
			echo '<div class="notice notice-warning"><p>' . wp_kses_post( sprintf( __( 'LocalizePilot is active, but the selected translation API key is missing. <a href="%s">Open settings</a>.', 'localizepilot' ), esc_url( admin_url( 'admin.php?page=localizepilot' ) ) ) ) . '</p></div>';
		}
	}

	private function should_process_request(): bool {
		if ( empty( $this->settings['enabled'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_trackback() || is_preview() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}
		return true;
	}

	private function cache_fingerprint(): string {
		$version = max( 1, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) );
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'version'    => $version,
					'provider'   => (string) ( $this->settings['translation_provider'] ?? 'translatex' ),
					'attributes' => ! empty( $this->settings['translate_attributes'] ),
					'links'      => ! empty( $this->settings['translate_internal_links'] ),
					'source'     => (string) ( $this->settings['source_language'] ?? 'en' ),
				)
			)
		);
	}
}
