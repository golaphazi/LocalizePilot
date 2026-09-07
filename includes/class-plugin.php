<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION = 'next_translate_settings';
	private const CACHE_VERSION_OPTION = 'next_translate_cache_version';
	private const CACHE_WARM_HOOK = 'localizepilot_warm_page_cache';
	private static ?Plugin $instance = null;
	private Router $router;
	private Usage_Limiter $limiter;
	private Analytics $analytics;
	private Translation_Manager $translations;
	private array $settings = array();

	/**
	 * Whether the page cache answered this request: true, false, or null when
	 * the request never got as far as consulting it. Reset per request.
	 */
	private ?bool $cache_hit = null;

	/** Whether this response entered the translated-output path. */
	private bool $translated_output = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function defaults(): array {
		return array(
			'enabled'                  => 0,
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
			'kimi_model'               => 'kimi-k2.5',
			'deepseek_api_key'         => '',
			'deepseek_model'           => 'deepseek-v4-flash',
			'mistral_api_key'          => '',
			'mistral_model'            => 'mistral-small-latest',
			'groq_api_key'             => '',
			'groq_model'               => 'openai/gpt-oss-120b',
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
			'show_flags'               => 1,
			'translate_attributes'     => 1,
			'translate_internal_links' => 1,
			'analytics_enabled'         => 0,
			'analytics_retention_days'  => 365,
		);
	}

	public function boot(): void {
		$this->limiter      = new Usage_Limiter();
		$this->router       = new Router();
		$this->analytics    = new Analytics( $this->router );
		$this->translations = new Translation_Manager( $this->router, $this->limiter );
		$this->settings     = $this->get_settings();

		$this->translations->hooks();
		$this->analytics->hooks();
		add_filter( 'do_parse_request', array( $this->router, 'before_parse_request' ), 0, 3 );
		( new Settings( $this->analytics ) )->hooks();

		add_shortcode( 'localizepilot_switcher', array( $this, 'language_switcher_shortcode' ) );
		add_action( 'init', array( $this, 'register_language_switcher_block' ) );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
		add_action( 'localizepilot_schedule_cache_warm', array( $this, 'schedule_cache_warm' ), 10, 2 );
		add_action( self::CACHE_WARM_HOOK, array( $this, 'warm_page_cache' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_head', array( $this, 'output_hreflang' ), 2 );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ), 10, 2 );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical_url' ) );
		add_filter( 'wpseo_canonical', array( $this, 'filter_seo_url' ) );
		add_filter( 'wpseo_opengraph_url', array( $this, 'filter_seo_url' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_seo_url' ) );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Register the dynamic Gutenberg language-switcher block.
	 */
	public function register_language_switcher_block(): void {
		$block_dir = LOCALIZEPILOT_PATH . 'blocks/language-switcher';

		wp_register_script(
			'localizepilot-language-switcher-block',
			LOCALIZEPILOT_URL . 'blocks/language-switcher/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ),
			LOCALIZEPILOT_VERSION,
			true
		);

		wp_set_script_translations( 'localizepilot-language-switcher-block', 'localizepilot' );

		wp_register_style(
			'localizepilot-language-switcher-block-editor',
			LOCALIZEPILOT_URL . 'blocks/language-switcher/editor.css',
			array( 'wp-edit-blocks' ),
			LOCALIZEPILOT_VERSION
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
				'flags'     => 'inherit',
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
				'flags'     => (string) ( $attributes['flags'] ?? 'inherit' ),
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

		Analytics::activate();

		$cache = new File_Cache( self::defaults() );
		$cache->is_writable();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		Analytics::deactivate();

		/*
		 * unschedule, not clear: every cache-warm event is scheduled with a
		 * post id and a language, and wp_clear_scheduled_hook() only removes
		 * events whose arguments match the ones it is given — so calling it
		 * with none leaves all of them behind. Measured: two events scheduled
		 * with arguments, two still there afterwards.
		 */
		wp_unschedule_hook( self::CACHE_WARM_HOOK );
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

		/*
		 * Every previously cached translation is now stale, including any
		 * copy held by a server-level host cache (e.g. SiteGround Dynamic
		 * Cache). Purge those too so visitors do not keep seeing pages that
		 * reflect the old settings, provider, or model.
		 */
		File_Cache::purge_external_caches();
	}

	public function enqueue_frontend_assets(): void {
		if ( empty( $this->get_settings()['enabled'] ) ) {
			return;
		}
		wp_enqueue_style( 'localizepilot', LOCALIZEPILOT_URL . 'assets/frontend.css', array(), LOCALIZEPILOT_VERSION );
	}

	public function start_buffer(): void {
		$this->settings = $this->get_settings();

		/*
		 * Buffer eligible frontend HTML when the plugin is enabled. Translation
		 * is available to logged-in and logged-out visitors. Shared full-page
		 * caching remains disabled for logged-in or personalized requests.
		 */
		if ( ! $this->should_buffer_frontend() ) {
			return;
		}

		ob_start( array( $this, 'process_output' ) );
	}

	/**
	 * Time the whole output pass and report it.
	 *
	 * The measurement wraps the method rather than living inside it because
	 * that method has half a dozen early returns, and a timer threaded through
	 * all of them would eventually miss one. Wrapping measures what a visitor
	 * actually waited for.
	 *
	 * Nothing here records anything. LocalizePilot does not keep performance
	 * history — it publishes the measurement, and whatever wants to keep it
	 * listens. With no listener this costs two microtime() calls.
	 */
	public function process_output( string $html ): string {
		$this->cache_hit        = null;
		$this->translated_output = false;

		$started    = microtime( true );
		$translated = $this->translate_output( $html );
		$duration   = ( microtime( true ) - $started ) * 1000;

		/**
		 * Fires once a front-end response has been through LocalizePilot.
		 *
		 * @param array<string,mixed> $measurement {
		 *     @type float       $duration   Milliseconds spent in this pass.
		 *     @type string      $language   Language served.
		 *     @type string      $source     Source language.
		 *     @type bool        $translated Whether translation work happened.
		 *     @type bool|null   $cache_hit  Whether the page cache answered,
		 *                                   or null when it was not consulted.
		 *     @type string      $url        The request URL.
		 * }
		 */
		do_action(
			'localizepilot_page_processed',
			array(
				'duration'   => $duration,
				'language'   => $this->router->current_language(),
				'source'     => $this->router->source_language(),
				'translated' => $this->translated_output,
				'cache_hit'  => $this->cache_hit,
				// The path only. A query string on a translated page view can
				// carry anything a visitor typed, and this is handed to
				// whatever is listening.
				'url'        => $this->request_path(),
			)
		);

		return $translated;
	}

	/**
	 * The path of the current request, without its query string.
	 */
	private function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return '' !== $path ? $path : '/';
	}

	private function translate_output( string $html ): string {
		if ( '' === trim( $html ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$current   = $this->router->current_language();
		$source    = $this->router->source_language();
		$processed = $html;

		/*
		 * Translate normal frontend requests for logged-in and logged-out users.
		 * The cache decision is handled separately, so personalized responses are
		 * translated in memory and are never written to the shared page cache.
		 */
		if ( $this->should_process_request() && $current !== $source ) {
			if ( ! $this->can_translate_output( $html ) ) {
				$switcher = new Language_Switcher( $this->router, $this->settings );
				return $switcher->inject( $html );
			}

			$this->translated_output = true;

			$cache       = new File_Cache( $this->settings );
			$identity    = $this->router->language_url( $source );
			$fingerprint = $this->cache_fingerprint();
			$source_hash = hash( 'sha256', $html . '|' . $fingerprint );
			$post_id     = is_singular( array( 'post', 'page' ) ) ? get_queried_object_id() : 0;
			$cache_key   = $post_id
				? $cache->make_post_key( $post_id, $current )
				: $cache->make_key( $identity, $current, $fingerprint );
			$cacheable   = $this->can_use_page_cache( $html );
			$cached      = $cacheable
				? $cache->get(
					$cache_key,
					$source_hash,
					array(
						'language' => $current,
						'url'      => $this->request_path(),
					)
				)
				: null;

			// Null means this response was not eligible for a cache lookup.
			$this->cache_hit = $cacheable ? ( is_string( $cached ) && '' !== $cached ) : null;

			if ( $this->cache_hit ) {
				$processed = $cached;
			} else {
				$limit_enabled    = ! empty( $this->settings['daily_limit_enabled'] );
				$daily_limit      = max( 1, absint( $this->settings['daily_limit'] ?? 10 ) );
				$provider_allowed = ! $limit_enabled || $this->limiter->can_translate( $daily_limit );

				if ( ! $provider_allowed && empty( $this->settings['cache_enabled'] ) ) {
					$stale = $cacheable && ! empty( $this->settings['stale_cache_fallback'] ) ? $cache->get_stale( $cache_key ) : null;
					$processed = is_string( $stale ) ? $stale : $html;
				} else {
					try {
						$client     = Client_Factory::make( $this->settings );
						if ( ! empty( $this->settings['cache_enabled'] ) ) {
							$client = new Translation_Memory_Client( $client, $cache, $cache_key, $fingerprint, $provider_allowed );
						}
						$protected  = $this->translations->protected_strings_for_current_request();
						$translator = new HTML_Translator( $client, $this->router, $this->settings, $protected );
						$processed  = $translator->translate_document( $html, $current );

						if ( $cacheable ) {
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
						}

						$provider_used = ! $client instanceof Translation_Memory_Client || $client->used_provider();
						if ( $limit_enabled && $provider_used ) {
							$this->limiter->increment( $daily_limit );
						}

						if (
							! $cacheable
							&& is_user_logged_in()
							&& $post_id
							&& null === $cache->get( $cache_key )
						) {
							$this->schedule_cache_warm( $post_id, $current );
						}
					} catch ( \Throwable $exception ) {
						$stale = $cacheable && ! empty( $this->settings['stale_cache_fallback'] ) ? $cache->get_stale( $cache_key ) : null;
						$processed = is_string( $stale ) ? $stale : $html;
					}
				}
			}
		}

		/*
		 * URL localization is independent from API translation. This keeps all
		 * internal frontend links on the active language for logged-in previews,
		 * Gutenberg-managed translations, and older cached HTML pages.
		 */
		if ( $current !== $source && ! empty( $this->settings['translate_internal_links'] ) ) {
			$processed = $this->localize_output_links( $processed, $current );
		}

		$switcher = new Language_Switcher( $this->router, $this->settings );
		return $switcher->inject( $processed );
	}

	/** Queue one anonymous request that can safely populate shared page HTML. */
	public function schedule_cache_warm( int $post_id, string $language ): void {
		$settings = $this->get_settings();
		$language = sanitize_key( $language );

		if ( ! $this->cache_warm_post( $post_id, $language, $settings ) ) {
			return;
		}

		$args = array( absint( $post_id ), $language );
		if ( ! wp_next_scheduled( self::CACHE_WARM_HOOK, $args ) ) {
			wp_schedule_single_event( time(), self::CACHE_WARM_HOOK, $args );

			if ( ! wp_doing_cron() && function_exists( 'spawn_cron' ) ) {
				spawn_cron( time() );
			}
		}
	}

	/**
	 * Visit a translated public URL without authentication cookies.
	 *
	 * The normal frontend cache guards still decide whether the response is
	 * cacheable. This method never writes a logged-in response to shared cache.
	 */
	public function warm_page_cache( int $post_id, string $language ): void {
		$settings = $this->get_settings();
		$language = sanitize_key( $language );
		$post     = $this->cache_warm_post( $post_id, $language, $settings );

		if ( ! $post ) {
			return;
		}

		$router = new Router();
		$url    = $router->localize_url( (string) get_permalink( $post ), $language );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		if ( '' === $host || '' === $home || $host !== $home ) {
			return;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 3,
				'user-agent'  => 'LocalizePilotCacheBot/' . LOCALIZEPILOT_VERSION,
				'cookies'     => array(),
				'headers'     => array(
					'Cache-Control'              => 'no-cache',
					'X-LocalizePilot-Cache-Warm' => '1',
				),
			)
		);

		/** Fires after a cache-warm request completes for observability and tests. */
		do_action( 'localizepilot_cache_warm_complete', $post_id, $language, $url, $response );
	}

	/** Return the public post that is eligible for an anonymous cache warm. */
	private function cache_warm_post( int $post_id, string $language, array $settings ): ?\WP_Post {
		$post    = get_post( $post_id );
		$source  = sanitize_key( (string) ( $settings['source_language'] ?? 'en' ) );
		$enabled = array_map( 'sanitize_key', (array) ( $settings['enabled_languages'] ?? array() ) );

		if (
			empty( $settings['enabled'] )
			|| empty( $settings['cache_enabled'] )
			|| ! $post instanceof \WP_Post
			|| 'publish' !== $post->post_status
			|| ! in_array( $post->post_type, array( 'post', 'page' ), true )
			|| $language === $source
			|| ! in_array( $language, $enabled, true )
		) {
			return null;
		}

		return $post;
	}

	/**
	 * Localize internal anchor URLs while preserving external, asset, admin,
	 * language-switcher, and explicitly excluded links.
	 */
	private function localize_output_links( string $html, string $language ): string {
		if ( '' === trim( $html ) || false === stripos( $html, '<a' ) || ! class_exists( '\DOMDocument' ) ) {
			return $html;
		}

		$dom      = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return $html;
		}

		foreach ( iterator_to_array( $dom->childNodes ) as $child ) {
			if ( XML_PI_NODE === $child->nodeType ) {
				$dom->removeChild( $child );
			}
		}

		$links = $dom->getElementsByTagName( 'a' );
		foreach ( $links as $link ) {
			if ( ! $link instanceof \DOMElement || ! $link->hasAttribute( 'href' ) || $this->exclude_link_from_localization( $link ) ) {
				continue;
			}

			$link->setAttribute(
				'href',
				$this->router->localize_url( $link->getAttribute( 'href' ), $language )
			);
		}

		$output = $dom->saveHTML();
		return is_string( $output ) && '' !== $output ? $output : $html;
	}

	/**
	 * Determine whether an anchor belongs to an area that must not be changed.
	 */
	private function exclude_link_from_localization( \DOMElement $link ): bool {
		$current = $link;

		while ( $current instanceof \DOMElement ) {
			$id = strtolower( $current->getAttribute( 'id' ) );
			if ( 'wpadminbar' === $id || 0 === strpos( $id, 'localizepilot' ) ) {
				return true;
			}

			$classes = ' ' . strtolower( trim( $current->getAttribute( 'class' ) ) ) . ' ';
			if (
				false !== strpos( $classes, ' notranslate ' ) ||
				false !== strpos( $classes, ' next-translate-' ) ||
				false !== strpos( $classes, ' localizepilot-language-switcher ' )
			) {
				return true;
			}

			if ( 'no' === strtolower( $current->getAttribute( 'translate' ) ) ) {
				return true;
			}

			$current = $current->parentNode;
		}

		return false;
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

	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( 'When a site administrator generates, refreshes, or tests a translation, LocalizePilot sends the selected website text and language settings to the translation provider configured by the administrator.', 'localizepilot' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The provider may process titles, excerpts, block text, visible page text, supported attributes, model settings, and custom translation instructions under its own terms and privacy policy. This can include normal frontend pages viewed by logged-in visitors. Logged-in responses are not written to the shared page cache. LocalizePilot does not send data to a provider until an administrator configures and uses that provider.', 'localizepilot' ) . '</p>';
		$content .= '<p>' . esc_html__( 'When first-party language analytics is enabled, LocalizePilot stores the language, normalized page URL, visit time, and a one-way visitor identifier in the WordPress database. Anonymous visitors receive a random LocalizePilot cookie. Raw IP addresses and user-agent strings are not stored. Repeated visits are stored as separate page-view events, while unique visitor reports count the same identifier once per language page.', 'localizepilot' ) . '</p>';
		wp_add_privacy_policy_content( 'LocalizePilot', wp_kses_post( $content ) );
	}

	public function admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message added by a verified admin-post redirect.
		if ( isset( $_GET['localizepilot_cache_message'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message added by a verified admin-post redirect.
			$message = sanitize_text_field( wp_unslash( $_GET['localizepilot_cache_message'] ) );
			echo ('<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>');
		}

		if ( empty( get_option( 'permalink_structure', '' ) ) ) {
			/* translators: %s is the URL of the WordPress permalink settings page. */
			echo ('<div class="notice notice-error"><p>' . wp_kses_post( sprintf( __( 'LocalizePilot language URLs require pretty permalinks. <a href="%s">Open Permalink Settings</a> and click Save Changes.', 'localizepilot' ), esc_url( admin_url( 'options-permalink.php' ) ) ) ) . '</p></div>');
		}
	}

	/**
	 * Determine whether the frontend HTML response may be buffered for
	 * translation, link localization, and automatic switcher injection.
	 */
	private function should_buffer_frontend(): bool {
		if ( empty( $this->settings['enabled'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_trackback() || is_preview() ) {
			return false;
		}

		if ( 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return false;
		}

		return true;
	}

	private function should_process_request(): bool {
		if ( empty( $this->settings['enabled'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_trackback() || is_preview() ) {
			return false;
		}
		if ( 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}
		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return false;
		}
		if ( is_singular() && post_password_required() ) {
			return false;
		}

		/*
		 * These screens can contain customer, order, payment, or session data.
		 * They remain excluded from automatic full-page API translation.
		 */
		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			return false;
		}

		return true;
	}

	private function can_translate_output( string $html ): bool {
		/*
		 * Password forms may contain sensitive content. Nonce values and admin-bar
		 * markup do not block translation because HTML_Translator excludes scripts,
		 * unsupported attributes, and the #wpadminbar subtree.
		 */
		return ! preg_match( '/type\s*=\s*(?:"|\')password(?:"|\')/i', $html );
	}

	private function can_use_page_cache( string $html ): bool {
		if ( empty( $this->settings['cache_enabled'] ) || is_user_logged_in() || ! $this->can_translate_output( $html ) ) {
			return false;
		}

		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return false;
		}

		if ( preg_match( '/(?:_wpnonce|wp_rest|data-nonce|["\']nonce["\']\s*:|nonce=)/i', $html ) ) {
			return false;
		}

		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'Set-Cookie:' ) ) {
				if ( false !== stripos( $header, 'localizepilot_visitor_id=' ) ) {
					continue;
				}
				return false;
			}
			if ( 0 === stripos( $header, 'Cache-Control:' ) && preg_match( '/(?:no-cache|no-store|private)/i', $header ) ) {
				return false;
			}
		}

		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			$cookie_name = strtolower( sanitize_text_field( (string) $cookie_name ) );
			if ( 0 === strpos( $cookie_name, 'wordpress_logged_in_' ) || 0 === strpos( $cookie_name, 'wp_woocommerce_session_' ) || 'woocommerce_items_in_cart' === $cookie_name || 0 === strpos( $cookie_name, 'comment_author_' ) ) {
				return false;
			}
		}

		$query = (string) wp_parse_url( $this->router->language_url( $this->router->source_language() ), PHP_URL_QUERY );
		return '' === $query;
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
					'fallback_model' => (string) ( $this->settings[ Provider_Catalog::model_field( (string) ( $this->settings['fallback_provider'] ?? '' ) ) ] ?? '' ),
					'ai_style'   => (string) ( $this->settings['ai_translation_style'] ?? 'natural' ),
					'ai_prompt'  => (string) ( $this->settings['ai_custom_instructions'] ?? '' ),
					'ai_temp'    => (float) ( $this->settings['ai_temperature'] ?? 0.2 ),
					'ai_tokens'  => absint( $this->settings['ai_max_output_tokens'] ?? 8192 ),
					'attributes' => ! empty( $this->settings['translate_attributes'] ),
					'links'      => ! empty( $this->settings['translate_internal_links'] ),
					'source'     => (string) ( $this->settings['source_language'] ?? 'en' ),
				)
			)
		);
	}
}
