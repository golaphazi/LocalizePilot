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
			'fallback_provider'        => '',
			'translatex_api_key'       => '',
			'google_api_key'           => '',
			'openai_api_key'           => '',
			'openai_model'             => 'gpt-4.1-mini',
			'gemini_api_key'           => '',
			'gemini_model'             => 'gemini-3.6-flash',
			'anthropic_api_key'        => '',
			'anthropic_model'          => 'claude-haiku-4-5',
			'kimi_api_key'             => '',
			'kimi_model'               => 'kimi-k2.6',
			'deepseek_api_key'         => '',
			'deepseek_model'           => 'deepseek-v4-flash',
			'mistral_api_key'          => '',
			'mistral_model'            => 'mistral-small-latest',
			'groq_api_key'             => '',
			'groq_model'               => 'llama-3.3-70b-versatile',
			'openrouter_api_key'       => '',
			'openrouter_model'         => 'openai/gpt-4.1-mini',
			'ai_translation_style'     => 'natural',
			'ai_custom_instructions'   => '',
			'ai_temperature'           => 0.2,
			'ai_max_output_tokens'     => 8192,
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

		add_shortcode( 'localizepilot_switcher', array( $this, 'language_switcher_shortcode' ) );
		add_action( 'init', array( $this, 'register_language_switcher_block' ) );
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

	/**
	 * Register the dynamic Gutenberg language-switcher block.
	 */
	public function register_language_switcher_block(): void {
		$block_dir    = NEXT_TRANSLATE_PATH . 'blocks/language-switcher';
		$script_path  = $block_dir . '/index.js';
		$editor_style = $block_dir . '/editor.css';

		wp_register_script(
			'localizepilot-language-switcher-block',
			NEXT_TRANSLATE_URL . 'blocks/language-switcher/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ),
			is_file( $script_path ) ? (string) filemtime( $script_path ) : NEXT_TRANSLATE_VERSION,
			true
		);

		wp_register_style(
			'localizepilot-language-switcher-block-editor',
			NEXT_TRANSLATE_URL . 'blocks/language-switcher/editor.css',
			array( 'wp-edit-blocks' ),
			is_file( $editor_style ) ? (string) filemtime( $editor_style ) : NEXT_TRANSLATE_VERSION
		);

		$settings       = $this->get_settings();
		$enabled_codes  = array_values(
			array_unique(
				array_merge(
					array( (string) ( $settings['source_language'] ?? 'en' ) ),
					(array) ( $settings['enabled_languages'] ?? array() )
				)
			)
		);
		$languages = array();
		foreach ( $enabled_codes as $code ) {
			$code = strtolower( (string) $code );
			if ( ! Language_Catalog::exists( $code ) ) {
				continue;
			}
			$languages[] = array(
				'code'    => $code,
				'native'  => Language_Catalog::label( $code, 'native' ),
				'english' => Language_Catalog::label( $code, 'english' ),
			);
		}

		wp_localize_script(
			'localizepilot-language-switcher-block',
			'LocalizePilotSwitcherBlock',
			array(
				'languages' => $languages,
				'defaults'  => array(
					'style'     => (string) ( $settings['menu_style'] ?? 'dropdown' ),
					'labels'    => (string) ( $settings['language_label'] ?? 'native' ),
					'alignment' => (string) ( $settings['menu_position'] ?? 'end' ),
				),
			)
		);

		register_block_type(
			$block_dir,
			array(
				'render_callback' => array( $this, 'render_language_switcher_block' ),
			)
		);
	}

	/**
	 * Render [localizepilot_switcher].
	 *
	 * @param array<string,mixed>|string $attributes Shortcode attributes.
	 */
	public function language_switcher_shortcode( $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'style'     => 'inherit',
				'labels'    => 'inherit',
				'alignment' => 'inherit',
				'class'     => '',
			),
			is_array( $attributes ) ? $attributes : array(),
			'localizepilot_switcher'
		);

		return $this->render_language_switcher( $attributes );
	}

	/**
	 * Render the dynamic Gutenberg block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 */
	public function render_language_switcher_block( array $attributes, string $content = '', $block = null ): string {
		$switcher = $this->render_language_switcher(
			array(
				'style'     => (string) ( $attributes['style'] ?? 'inherit' ),
				'labels'    => (string) ( $attributes['labels'] ?? 'inherit' ),
				'alignment' => (string) ( $attributes['alignment'] ?? 'inherit' ),
				'class'     => (string) ( $attributes['className'] ?? '' ),
			)
		);

		if ( '' === $switcher ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array( 'class' => 'localizepilot-switcher-block' )
		);

		return '<div ' . $wrapper_attributes . '>' . $switcher . '</div>';
	}

	/**
	 * Shared shortcode and block renderer.
	 *
	 * @param array<string,mixed> $attributes Render overrides.
	 */
	private function render_language_switcher( array $attributes = array() ): string {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return '';
		}

		$switcher = new Language_Switcher( $this->router, $settings );
		return $switcher->render(
			array(
				'style'     => sanitize_key( (string) ( $attributes['style'] ?? 'inherit' ) ),
				'labels'    => sanitize_key( (string) ( $attributes['labels'] ?? 'inherit' ) ),
				'alignment' => sanitize_key( (string) ( $attributes['alignment'] ?? 'inherit' ) ),
				'class'     => sanitize_text_field( (string) ( $attributes['class'] ?? '' ) ),
			)
		);
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
		$key_field = Provider_Catalog::key_field( $provider );
		$key       = '' !== $key_field ? (string) ( $options[ $key_field ] ?? '' ) : '';
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
					'fallback'   => (string) ( $this->settings['fallback_provider'] ?? '' ),
					'model'      => (string) ( $this->settings[ Provider_Catalog::model_field( (string) ( $this->settings['translation_provider'] ?? 'translatex' ) ) ] ?? '' ),
					'ai_style'   => (string) ( $this->settings['ai_translation_style'] ?? 'natural' ),
					'ai_prompt'  => (string) ( $this->settings['ai_custom_instructions'] ?? '' ),
					'ai_temp'    => (float) ( $this->settings['ai_temperature'] ?? 0.2 ),
					'attributes' => ! empty( $this->settings['translate_attributes'] ),
					'links'      => ! empty( $this->settings['translate_internal_links'] ),
					'source'     => (string) ( $this->settings['source_language'] ?? 'en' ),
				)
			)
		);
	}
}
