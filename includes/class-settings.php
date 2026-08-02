<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Settings {
	private Usage_Limiter $limiter;
	private Analytics $analytics;

	public function __construct( Usage_Limiter $limiter, Analytics $analytics ) {
		$this->limiter   = $limiter;
		$this->analytics = $analytics;
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 5 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_next_translate_test_api', array( $this, 'test_api' ) );
		add_action( 'admin_post_next_translate_cache_action', array( $this, 'cache_action' ) );
		add_action( 'admin_post_localizepilot_analytics_action', array( $this, 'analytics_action' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'LocalizePilot', 'localizepilot' ),
			__( 'LocalizePilot', 'localizepilot' ),
			'manage_options',
			'localizepilot',
			array( $this, 'render_page' ),
			'dashicons-translation',
			58
		);
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

		if ( 'dashboard' === $tab ) {
			$output['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

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
			$output['daily_limit_enabled']    = empty( $input['daily_limit_enabled'] ) ? 0 : 1;
			$output['daily_limit']            = min( 10000, max( 1, absint( $input['daily_limit'] ?? 10 ) ) );
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
		} elseif ( 'cache' === $tab ) {
			$output['cache_enabled']        = empty( $input['cache_enabled'] ) ? 0 : 1;
			$output['object_cache_enabled'] = empty( $input['object_cache_enabled'] ) ? 0 : 1;
			$output['cache_hours']          = min( 8760, max( 1, absint( $input['cache_hours'] ?? 24 ) ) );
			$changed = true;
		} elseif ( 'analytics' === $tab ) {
			$output['analytics_enabled']        = empty( $input['analytics_enabled'] ) ? 0 : 1;
			$output['analytics_retention_days'] = min( 3650, max( 7, absint( $input['analytics_retention_days'] ?? 365 ) ) );
		} elseif ( 'switcher' === $tab ) {
			$output['header_switcher']  = empty( $input['header_switcher'] ) ? 0 : 1;
			$output['menu_style']       = in_array( $input['menu_style'] ?? '', array( 'dropdown', 'inline' ), true ) ? sanitize_key( $input['menu_style'] ) : 'dropdown';
			$output['menu_position']    = in_array( $input['menu_position'] ?? '', array( 'start', 'center', 'end' ), true ) ? sanitize_key( $input['menu_position'] ) : 'end';
			$output['language_label']   = in_array( $input['language_label'] ?? '', array( 'native', 'english', 'code' ), true ) ? sanitize_key( $input['language_label'] ) : 'native';
			$changed = true;
		}

		$output['source_language'] = 'en';
		if ( $changed ) {
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

	public function enqueue_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_settings = 'toplevel_page_localizepilot' === $hook;
		$is_translation_screen = $screen && Translation_Manager::POST_TYPE === $screen->post_type;
		$is_source_editor = $screen && in_array( $screen->post_type, array( 'post', 'page' ), true ) && in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_settings && ! $is_translation_screen && ! $is_source_editor ) {
			return;
		}

		wp_enqueue_style( 'localizepilot-admin', LOCALIZEPILOT_URL . 'assets/admin.css', array(), LOCALIZEPILOT_VERSION );
		if ( ! $is_settings ) {
			return;
		}

		wp_enqueue_script( 'localizepilot-admin', LOCALIZEPILOT_URL . 'assets/admin.js', array(), LOCALIZEPILOT_VERSION, true );
		wp_localize_script(
			'localizepilot-admin',
			'localizePilotAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'next_translate_test_api' ),
				'testing' => __( 'Testing…', 'localizepilot' ),
				'test'    => __( 'Test connection', 'localizepilot' ),
				'show'    => __( 'Show', 'localizepilot' ),
				'hide'    => __( 'Hide', 'localizepilot' ),
				'copied'  => __( 'Copied!', 'localizepilot' ),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options    = Plugin::instance()->get_settings();
		$languages  = Language_Catalog::all();
		$usage      = $this->limiter->get_usage();
		$limit      = max( 1, absint( $options['daily_limit'] ?? 10 ) );
		$cache      = new File_Cache( $options );
		$stats      = $cache->stats();
		$provider   = (string) ( $options['translation_provider'] ?? 'translatex' );
		$tabs       = $this->tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation parameter.
		$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'dashboard' ) );
		$active_tab = isset( $tabs[ $active_tab ] ) ? $active_tab : 'dashboard';
		?>
		<div class="wrap next-translate-admin localizepilot-admin">
			<header class="nt-admin-hero">
				<div class="nt-brand-lockup"><span class="nt-brand-icon">LP</span><div><span class="nt-eyebrow"><?php esc_html_e( 'Multilingual Content & Media', 'localizepilot' ); ?></span><h1><?php esc_html_e( 'LocalizePilot', 'localizepilot' ); ?></h1><p><?php esc_html_e( 'Translate, review, edit, cache, and personalize every WordPress language experience.', 'localizepilot' ); ?></p></div></div>
				<div class="nt-hero-statuses"><span class="nt-pill <?php echo esc_attr( empty( $options['enabled'] ) ? 'is-off' : 'is-on' ); ?>"><i></i><?php echo empty( $options['enabled'] ) ? esc_html__( 'Translation off', 'localizepilot' ) : esc_html__( 'Translation active', 'localizepilot' ); ?></span><span class="nt-pill"><strong><?php echo esc_html( Provider_Catalog::label( $provider ) ); ?></strong></span></div>
			</header>

			<nav class="nt-tabs" aria-label="<?php esc_attr_e( 'LocalizePilot settings', 'localizepilot' ); ?>">
				<?php foreach ( $tabs as $slug => $tab ) : ?><a class="nt-tab <?php echo esc_attr( $slug === $active_tab ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"><span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span><?php echo esc_html( $tab['label'] ); ?></a><?php endforeach; ?>
			</nav>


			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message added by a verified admin-post redirect.
			$cache_message = sanitize_text_field( wp_unslash( $_GET['localizepilot_cache_message'] ?? '' ) );
			if ( '' !== $cache_message ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $cache_message ); ?></p></div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message added by a verified admin-post redirect.
			$analytics_message = sanitize_text_field( wp_unslash( $_GET['localizepilot_analytics_message'] ?? '' ) );
			if ( '' !== $analytics_message ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $analytics_message ); ?></p></div>
			<?php endif; ?>

			<div class="nt-tab-panel">
				<?php
				if ( 'dashboard' === $active_tab ) {
					$this->render_dashboard_tab( $options, $languages, $usage, $limit, $stats, $provider );
				} elseif ( 'languages' === $active_tab ) {
					$this->render_languages_tab( $options, $languages );
				} elseif ( 'translation' === $active_tab ) {
					$this->render_translation_tab( $options );
				} elseif ( 'analytics' === $active_tab ) {
					$this->render_analytics_tab( $options );
				} elseif ( 'cache' === $active_tab ) {
					$this->render_cache_tab( $options, $cache, $stats );
				} else {
					$this->render_switcher_tab( $options );
				}
				?>
			</div>
		</div>
		<?php
	}

	private function tabs(): array {
		return array(
			'dashboard'   => array( 'label' => __( 'Dashboard & API', 'localizepilot' ), 'icon' => 'dashicons-dashboard' ),
			'languages'   => array( 'label' => __( 'Languages', 'localizepilot' ), 'icon' => 'dashicons-translation' ),
			'translation' => array( 'label' => __( 'Translation', 'localizepilot' ), 'icon' => 'dashicons-edit-page' ),
			'analytics'   => array( 'label' => __( 'Analytics', 'localizepilot' ), 'icon' => 'dashicons-chart-area' ),
			'cache'       => array( 'label' => __( 'Cache Management', 'localizepilot' ), 'icon' => 'dashicons-database' ),
			'switcher'    => array( 'label' => __( 'Language Switcher', 'localizepilot' ), 'icon' => 'dashicons-menu-alt3' ),
		);
	}

	private function form_start( string $tab ): void {
		echo ('<form method="post" action="options.php" class="nt-settings-form">');
		settings_fields( 'next_translate_group' );
		echo ('<input type="hidden" name="' . esc_attr( Plugin::OPTION ) . '[settings_tab]" value="' . esc_attr( $tab ) . '">');
	}

	private function form_end( string $label = '' ): void {
		$label = $label ?: __( 'Save changes', 'localizepilot' );
		echo ('<div class="nt-save-bar"><div><strong>') . esc_html__( 'Save your LocalizePilot settings', 'localizepilot' ) . '</strong><span>' . esc_html__( 'Updated settings invalidate rendered page cache only. Gutenberg translations remain safe.', 'localizepilot' ) . '</span></div>';
		submit_button( $label, 'primary', 'submit', false );
		echo ('</div></form>');
	}

	private function render_dashboard_tab( array $options, array $languages, array $usage, int $limit, array $stats, string $provider ): void {
		$providers = Provider_Catalog::all();
		$this->form_start( 'dashboard' ); ?>
		<div class="nt-stat-grid">
			<div class="nt-stat"><span><?php esc_html_e( 'Rendered cache', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) $stats['count'] ); ?></strong><small><?php /* translators: %d is the number of expired cache entries. */ echo esc_html( sprintf( __( '%d expired', 'localizepilot' ), $stats['expired'] ) ); ?></small></div>
			<div class="nt-stat"><span><?php esc_html_e( 'Translation snapshots', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) ( $stats['snapshots'] ?? 0 ) ); ?></strong><small><?php esc_html_e( 'Gutenberg-generated HTML', 'localizepilot' ); ?></small></div>
			<div class="nt-stat"><span><?php esc_html_e( 'Daily API use', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) $usage['count'] ); ?><em>/<?php echo esc_html( (string) $limit ); ?></em></strong><small><?php echo empty( $options['daily_limit_enabled'] ) ? esc_html__( 'Limit disabled', 'localizepilot' ) : /* translators: %d is the remaining number of allowed translations today. */ esc_html( sprintf( __( '%d remaining', 'localizepilot' ), max( 0, $limit - $usage['count'] ) ) ); ?></small></div>
			<div class="nt-stat"><span><?php esc_html_e( 'Languages', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) ( count( (array) $options['enabled_languages'] ) + 1 ) ); ?></strong><small><?php esc_html_e( 'Including English', 'localizepilot' ); ?></small></div>
		</div>

		<div class="nt-dashboard-grid">
			<section class="nt-card">
				<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'System', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Translation overview', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Enable multilingual URLs and manage translated Gutenberg content.', 'localizepilot' ); ?></p></div></div>
				<label class="nt-toggle-row"><span><strong><?php esc_html_e( 'Enable LocalizePilot', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Enable language URLs and frontend translation.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>><i></i></label>
				<div class="nt-quick-links"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Translation_Manager::POST_TYPE ) ); ?>"><span class="dashicons dashicons-edit-page"></span><strong><?php esc_html_e( 'Manage translations', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Edit Gutenberg language versions', 'localizepilot' ); ?></small></a><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache' ), admin_url( 'admin.php' ) ) ); ?>"><span class="dashicons dashicons-database"></span><strong><?php esc_html_e( 'Open cache history', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Review and remove HTML cache', 'localizepilot' ); ?></small></a></div>
			</section>

			<section class="nt-card">
				<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Reliability', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Limits and fallback', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Control API usage and optionally retry with another provider.', 'localizepilot' ); ?></p></div></div>
				<label class="nt-toggle-row"><span><strong><?php esc_html_e( 'Daily API limit', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Restrict new automatic translations each day.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[daily_limit_enabled]" value="1" <?php checked( ! empty( $options['daily_limit_enabled'] ) ); ?>><i></i></label>
				<div class="nt-field-row"><div class="nt-field"><label><?php esc_html_e( 'Fallback provider', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[fallback_provider]"><option value=""><?php esc_html_e( 'No fallback', 'localizepilot' ); ?></option><?php foreach ( $providers as $provider_id => $config ) : if ( $provider_id === $provider ) { continue; } ?><option value="<?php echo esc_attr( $provider_id ); ?>" <?php selected( (string) ( $options['fallback_provider'] ?? '' ), $provider_id ); ?>><?php echo esc_html( (string) $config['label'] ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Used only when the primary provider returns an error.', 'localizepilot' ); ?></small></div><div class="nt-field"><label><?php esc_html_e( 'Translations per day', 'localizepilot' ); ?></label><input type="number" min="1" max="10000" name="<?php echo esc_attr( Plugin::OPTION ); ?>[daily_limit]" value="<?php echo esc_attr( (string) $limit ); ?>"></div></div>
			</section>
		</div>

		<section class="nt-card nt-card-full nt-provider-section">
			<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'API connection', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Choose a translation or AI provider', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Use a dedicated translation API or an AI model for context-aware localization.', 'localizepilot' ); ?></p></div><span class="nt-count-badge"><?php echo esc_html( (string) count( $providers ) ); ?> <?php esc_html_e( 'providers', 'localizepilot' ); ?></span></div>
			<div class="nt-provider-grid nt-provider-grid-many">
				<?php foreach ( $providers as $provider_id => $config ) : ?>
					<label class="nt-provider-card"><input type="radio" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translation_provider]" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( $provider, $provider_id ); ?>><span class="nt-provider-mark"><?php echo esc_html( (string) $config['mark'] ); ?></span><span><strong><?php echo esc_html( (string) $config['label'] ); ?></strong><small><?php echo esc_html( (string) $config['description'] ); ?></small></span><i></i></label>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $providers as $provider_id => $config ) :
				$key_field   = (string) ( $config['key_field'] ?? '' );
				$model_field = (string) ( $config['model_field'] ?? '' );
			?>
				<div class="nt-provider-panel" data-provider="<?php echo esc_attr( $provider_id ); ?>">
					<div class="nt-provider-panel-grid">
						<div class="nt-field"><label><?php /* translators: %s is the translation provider name. */ echo esc_html( sprintf( __( '%s API key', 'localizepilot' ), (string) $config['label'] ) ); ?></label><div class="nt-input-action"><input type="password" name="<?php echo esc_attr( Plugin::OPTION ); ?>[<?php echo esc_attr( $key_field ); ?>]" value="" placeholder="<?php echo empty( $options[ $key_field ] ) ? esc_attr__( 'Paste API key', 'localizepilot' ) : esc_attr__( 'Saved key · leave blank to keep it', 'localizepilot' ); ?>" autocomplete="new-password"><button type="button" class="button nt-reveal-key"><?php esc_html_e( 'Show', 'localizepilot' ); ?></button></div><label class="nt-mini-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[clear_<?php echo esc_attr( $key_field ); ?>]" value="1"> <?php esc_html_e( 'Remove saved key', 'localizepilot' ); ?></label></div>
						<?php if ( '' !== $model_field ) : ?><div class="nt-field"><label><?php esc_html_e( 'Model name', 'localizepilot' ); ?></label><input type="text" class="nt-provider-model" name="<?php echo esc_attr( Plugin::OPTION ); ?>[<?php echo esc_attr( $model_field ); ?>]" value="<?php echo esc_attr( (string) ( $options[ $model_field ] ?? $config['default_model'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) ( $config['default_model'] ?? '' ) ); ?>"><small><?php esc_html_e( 'Enter a model ID supported by your provider account.', 'localizepilot' ); ?></small></div><?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="nt-api-actions"><button type="button" class="button button-secondary" id="next-translate-test-api"><?php esc_html_e( 'Test selected provider', 'localizepilot' ); ?></button><select id="next-translate-test-language"><?php foreach ( $languages as $code => $language ) : if ( 'en' !== $code ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $language['name'] ); ?></option><?php endif; endforeach; ?></select><span id="next-translate-test-result"></span></div>
		</section>

		<section class="nt-card nt-card-full nt-ai-options-card">
			<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'AI localization', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'AI translation behavior', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'These controls are used by OpenAI, Gemini, Claude, Kimi, DeepSeek, Mistral, Groq, and OpenRouter.', 'localizepilot' ); ?></p></div></div>
			<div class="nt-ai-option-grid">
				<div class="nt-field"><label><?php esc_html_e( 'Translation style', 'localizepilot' ); ?></label><select id="localizepilot-ai-style" name="<?php echo esc_attr( Plugin::OPTION ); ?>[ai_translation_style]"><option value="faithful" <?php selected( (string) $options['ai_translation_style'], 'faithful' ); ?>><?php esc_html_e( 'Faithful', 'localizepilot' ); ?></option><option value="natural" <?php selected( (string) $options['ai_translation_style'], 'natural' ); ?>><?php esc_html_e( 'Natural', 'localizepilot' ); ?></option><option value="marketing" <?php selected( (string) $options['ai_translation_style'], 'marketing' ); ?>><?php esc_html_e( 'Marketing', 'localizepilot' ); ?></option><option value="formal" <?php selected( (string) $options['ai_translation_style'], 'formal' ); ?>><?php esc_html_e( 'Formal', 'localizepilot' ); ?></option></select></div>
				<div class="nt-field"><label><?php esc_html_e( 'Temperature', 'localizepilot' ); ?></label><input id="localizepilot-ai-temperature" type="number" min="0" max="1" step="0.1" name="<?php echo esc_attr( Plugin::OPTION ); ?>[ai_temperature]" value="<?php echo esc_attr( (string) $options['ai_temperature'] ); ?>"><small><?php esc_html_e( 'Lower values produce more consistent translations.', 'localizepilot' ); ?></small></div>
				<div class="nt-field"><label><?php esc_html_e( 'Maximum output tokens', 'localizepilot' ); ?></label><input id="localizepilot-ai-max-tokens" type="number" min="512" max="32000" step="128" name="<?php echo esc_attr( Plugin::OPTION ); ?>[ai_max_output_tokens]" value="<?php echo esc_attr( (string) $options['ai_max_output_tokens'] ); ?>"></div>
			</div>
			<div class="nt-field"><label><?php esc_html_e( 'Custom translation instructions', 'localizepilot' ); ?></label><textarea id="localizepilot-ai-instructions" name="<?php echo esc_attr( Plugin::OPTION ); ?>[ai_custom_instructions]" rows="5" placeholder="<?php esc_attr_e( 'Example: Keep product names in English. Use informal Spanish. Never translate LocalizePilot.', 'localizepilot' ); ?>"><?php echo esc_textarea( (string) $options['ai_custom_instructions'] ); ?></textarea><small><?php esc_html_e( 'Applied to every AI translation request. Do not include secrets here.', 'localizepilot' ); ?></small></div>
		</section>

		<?php $this->form_end( __( 'Save dashboard & API', 'localizepilot' ) ); ?>
		<?php
	}

	private function render_languages_tab( array $options, array $languages ): void {
		$this->form_start( 'languages' ); ?>
		<section class="nt-card nt-card-full"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Language settings', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Choose available languages', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'English remains the source language. Select the language versions visitors can open.', 'localizepilot' ); ?></p></div><span class="nt-count-badge"><?php echo esc_html( (string) count( (array) $options['enabled_languages'] ) ); ?> <?php esc_html_e( 'enabled', 'localizepilot' ); ?></span></div><div class="nt-language-grid"><?php foreach ( $languages as $code => $language ) : if ( 'en' === $code ) { continue; } ?><label><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[enabled_languages][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, (array) $options['enabled_languages'], true ) ); ?>><span><strong><?php echo esc_html( $language['native'] ); ?></strong><small><?php echo esc_html( strtoupper( $code ) . ' · ' . $language['name'] ); ?></small></span></label><?php endforeach; ?></div></section>
		<section class="nt-card nt-url-card"><h2><?php esc_html_e( 'Same-page language URLs', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'LocalizePilot changes only the language prefix and keeps the current post or page path.', 'localizepilot' ); ?></p><code><?php echo esc_html( home_url( '/blog/example-post/' ) ); ?></code><code><?php echo esc_html( home_url( '/de/blog/example-post/' ) ); ?></code><code><?php echo esc_html( home_url( '/ar/blog/example-post/' ) ); ?></code></section>
		<?php $this->form_end( __( 'Save languages', 'localizepilot' ) );
	}

	private function render_translation_tab( array $options ): void {
		$this->form_start( 'translation' ); ?>
		<div class="nt-two-column"><section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Content behavior', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Translation rules', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Control attributes, links, and source-change behavior.', 'localizepilot' ); ?></p></div></div><label class="nt-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translate_attributes]" value="1" <?php checked( ! empty( $options['translate_attributes'] ) ); ?>><span><strong><?php esc_html_e( 'Translate attributes', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Alt, title, placeholder, ARIA labels, and supported SEO descriptions.', 'localizepilot' ); ?></small></span></label><label class="nt-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translate_internal_links]" value="1" <?php checked( ! empty( $options['translate_internal_links'] ) ); ?>><span><strong><?php esc_html_e( 'Localize internal links', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Keep internal links inside the selected language path.', 'localizepilot' ); ?></small></span></label><label class="nt-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[refresh_on_source_change]" value="1" <?php checked( ! empty( $options['refresh_on_source_change'] ) ); ?>><span><strong><?php esc_html_e( 'Refresh when source changes', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Invalidate rendered cache when the English source HTML changes.', 'localizepilot' ); ?></small></span></label><label class="nt-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[stale_cache_fallback]" value="1" <?php checked( ! empty( $options['stale_cache_fallback'] ) ); ?>><span><strong><?php esc_html_e( 'Use stale cache on API failure', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Serve the last translated page if the translation provider is unavailable.', 'localizepilot' ); ?></small></span></label></section>
		<section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Editorial workflow', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Gutenberg translation statuses', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Each language version has a persistent editorial state.', 'localizepilot' ); ?></p></div></div><div class="nt-status-cards"><div><span class="nt-translation-status nt-status-automatic"><?php esc_html_e( 'Automatic', 'localizepilot' ); ?></span><p><?php esc_html_e( 'Generated by the selected API.', 'localizepilot' ); ?></p></div><div><span class="nt-translation-status nt-status-edited"><?php esc_html_e( 'Edited', 'localizepilot' ); ?></span><p><?php esc_html_e( 'Corrected manually in Gutenberg.', 'localizepilot' ); ?></p></div><div><span class="nt-translation-status nt-status-reviewed"><?php esc_html_e( 'Reviewed', 'localizepilot' ); ?></span><p><?php esc_html_e( 'Checked and approved for publishing.', 'localizepilot' ); ?></p></div><div><span class="nt-translation-status nt-status-needs_update"><?php esc_html_e( 'Needs update', 'localizepilot' ); ?></span><p><?php esc_html_e( 'The source post changed after translation.', 'localizepilot' ); ?></p></div></div><a class="button button-primary nt-wide-button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Translation_Manager::POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage Gutenberg translations', 'localizepilot' ); ?></a></section></div>
		<?php $this->form_end( __( 'Save translation settings', 'localizepilot' ) );
	}

	private function render_analytics_tab( array $options ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
		$language = sanitize_key( wp_unslash( $_GET['analytics_language'] ?? 'all' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
		$date_from = sanitize_text_field( wp_unslash( $_GET['analytics_date_from'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
		$date_to = sanitize_text_field( wp_unslash( $_GET['analytics_date_to'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
		$url_search = sanitize_text_field( wp_unslash( $_GET['analytics_url'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics pagination.
		$analytics_page = max( 1, absint( wp_unslash( $_GET['analytics_page'] ?? 1 ) ) );

		$report = $this->analytics->report(
			array(
				'language'  => $language,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'url'       => $url_search,
				'page'      => $analytics_page,
			)
		);

		$filters = (array) $report['filters'];
		$summary = (array) $report['summary'];
		$languages = array_values(
			array_unique(
				array_merge(
					array( (string) ( $options['source_language'] ?? 'en' ) ),
					(array) ( $options['enabled_languages'] ?? array() )
				)
			)
		);

		$this->form_start( 'analytics' );
		?>
		<div class="nt-two-column nt-analytics-settings">
			<section class="nt-card">
				<div class="nt-card-head">
					<div>
						<span class="nt-section-kicker"><?php esc_html_e( 'First-party reporting', 'localizepilot' ); ?></span>
						<h2><?php esc_html_e( 'Language page analytics', 'localizepilot' ); ?></h2>
						<p><?php esc_html_e( 'Store page-view events locally and report unique visitors for each language page.', 'localizepilot' ); ?></p>
					</div>
				</div>
				<label class="nt-toggle-row">
					<span>
						<strong><?php esc_html_e( 'Enable visitor analytics', 'localizepilot' ); ?></strong>
						<small><?php esc_html_e( 'No raw IP address or user-agent value is stored.', 'localizepilot' ); ?></small>
					</span>
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[analytics_enabled]" value="1" <?php checked( ! empty( $options['analytics_enabled'] ) ); ?>>
					<i></i>
				</label>
				<div class="nt-field">
					<label for="localizepilot-analytics-retention"><?php esc_html_e( 'Data retention', 'localizepilot' ); ?></label>
					<div class="nt-number">
						<input id="localizepilot-analytics-retention" type="number" min="7" max="3650" name="<?php echo esc_attr( Plugin::OPTION ); ?>[analytics_retention_days]" value="<?php echo esc_attr( (string) ( $options['analytics_retention_days'] ?? 365 ) ); ?>">
						<span><?php esc_html_e( 'days', 'localizepilot' ); ?></span>
					</div>
				</div>
			</section>
			<section class="nt-card nt-analytics-explainer">
				<span class="nt-section-kicker"><?php esc_html_e( 'Counting method', 'localizepilot' ); ?></span>
				<h2><?php esc_html_e( 'Unique visitors and repeat views', 'localizepilot' ); ?></h2>
				<p><?php esc_html_e( 'The same visitor is counted once for each language page. Every repeat visit is still stored as a separate page-view event.', 'localizepilot' ); ?></p>
				<div class="nt-analytics-formula">
					<div><strong><?php esc_html_e( 'Visitors', 'localizepilot' ); ?></strong><span><?php esc_html_e( 'Distinct visitor identifiers per page and language', 'localizepilot' ); ?></span></div>
					<div><strong><?php esc_html_e( 'Views', 'localizepilot' ); ?></strong><span><?php esc_html_e( 'Every recorded visit, including repeat visits', 'localizepilot' ); ?></span></div>
				</div>
			</section>
		</div>
		<?php
		$this->form_end( __( 'Save analytics settings', 'localizepilot' ) );
		?>

		<section class="nt-card nt-analytics-report-card">
			<div class="nt-card-head">
				<div>
					<span class="nt-section-kicker"><?php esc_html_e( 'Reports', 'localizepilot' ); ?></span>
					<h2><?php esc_html_e( 'Visitor activity by language page', 'localizepilot' ); ?></h2>
					<p><?php esc_html_e( 'Filter the graph and page report by language, date range, or a specific URL.', 'localizepilot' ); ?></p>
				</div>
				<?php $this->analytics_action_form(); ?>
			</div>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="nt-analytics-filters">
				<input type="hidden" name="page" value="localizepilot">
				<input type="hidden" name="tab" value="analytics">
				<div class="nt-field">
					<label for="localizepilot-analytics-language"><?php esc_html_e( 'Language', 'localizepilot' ); ?></label>
					<select id="localizepilot-analytics-language" name="analytics_language">
						<option value="all" <?php selected( 'all', $filters['language'] ); ?>><?php esc_html_e( 'All languages', 'localizepilot' ); ?></option>
						<?php foreach ( $languages as $language_code ) : ?>
							<?php if ( ! Language_Catalog::exists( $language_code ) ) { continue; } ?>
							<option value="<?php echo esc_attr( $language_code ); ?>" <?php selected( $language_code, $filters['language'] ); ?>><?php echo esc_html( Language_Catalog::label( $language_code, 'native' ) . ' (' . strtoupper( $language_code ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="nt-field">
					<label for="localizepilot-analytics-from"><?php esc_html_e( 'From', 'localizepilot' ); ?></label>
					<input id="localizepilot-analytics-from" type="date" name="analytics_date_from" value="<?php echo esc_attr( (string) $filters['date_from'] ); ?>">
				</div>
				<div class="nt-field">
					<label for="localizepilot-analytics-to"><?php esc_html_e( 'To', 'localizepilot' ); ?></label>
					<input id="localizepilot-analytics-to" type="date" name="analytics_date_to" value="<?php echo esc_attr( (string) $filters['date_to'] ); ?>">
				</div>
				<div class="nt-field nt-analytics-url-filter">
					<label for="localizepilot-analytics-url"><?php esc_html_e( 'Specific URL', 'localizepilot' ); ?></label>
					<input id="localizepilot-analytics-url" type="search" name="analytics_url" value="<?php echo esc_attr( (string) $filters['url'] ); ?>" placeholder="<?php esc_attr_e( 'Search part of a page URL', 'localizepilot' ); ?>">
				</div>
				<div class="nt-analytics-filter-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply filters', 'localizepilot' ); ?></button>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'analytics' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Reset', 'localizepilot' ); ?></a>
				</div>
			</form>

			<div class="nt-stat-grid nt-analytics-summary">
				<div class="nt-stat"><span><?php esc_html_e( 'Unique visitors', 'localizepilot' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $summary['visitors'] ?? 0 ) ) ); ?></strong><small><?php esc_html_e( 'Distinct identifiers in this report', 'localizepilot' ); ?></small></div>
				<div class="nt-stat"><span><?php esc_html_e( 'Page views', 'localizepilot' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $summary['views'] ?? 0 ) ) ); ?></strong><small><?php esc_html_e( 'Includes repeat visits', 'localizepilot' ); ?></small></div>
				<div class="nt-stat"><span><?php esc_html_e( 'Language pages', 'localizepilot' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $summary['pages'] ?? 0 ) ) ); ?></strong><small><?php esc_html_e( 'Unique URL and language combinations', 'localizepilot' ); ?></small></div>
				<div class="nt-stat"><span><?php esc_html_e( 'Languages viewed', 'localizepilot' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $summary['languages'] ?? 0 ) ) ); ?></strong><small><?php esc_html_e( 'Languages with recorded activity', 'localizepilot' ); ?></small></div>
			</div>

			<?php $this->render_analytics_chart( (array) $report['daily'] ); ?>
			<?php $this->render_analytics_table( $report ); ?>
		</section>
		<?php
	}

	/**
	 * @param array<int,array{date:string,views:int,visitors:int}> $daily Daily report rows.
	 */
	private function render_analytics_chart( array $daily ): void {
		$width   = 960;
		$height  = 320;
		$left    = 54;
		$right   = 22;
		$top     = 24;
		$bottom  = 48;
		$plot_w  = $width - $left - $right;
		$plot_h  = $height - $top - $bottom;
		$maximum = 1;

		foreach ( $daily as $row ) {
			$maximum = max( $maximum, absint( $row['views'] ?? 0 ), absint( $row['visitors'] ?? 0 ) );
		}

		$count          = count( $daily );
		$views_points   = array();
		$visitor_points = array();
		$point_data     = array();

		foreach ( $daily as $index => $row ) {
			$x = $left + ( $count > 1 ? ( $index * $plot_w / ( $count - 1 ) ) : $plot_w / 2 );
			$views_y = $top + $plot_h - ( absint( $row['views'] ?? 0 ) / $maximum * $plot_h );
			$visitors_y = $top + $plot_h - ( absint( $row['visitors'] ?? 0 ) / $maximum * $plot_h );
			$views_points[]   = number_format( $x, 2, '.', '' ) . ',' . number_format( $views_y, 2, '.', '' );
			$visitor_points[] = number_format( $x, 2, '.', '' ) . ',' . number_format( $visitors_y, 2, '.', '' );
			$point_data[] = array(
				'x'          => $x,
				'views_y'    => $views_y,
				'visitors_y' => $visitors_y,
				'date'       => (string) ( $row['date'] ?? '' ),
				'views'      => absint( $row['views'] ?? 0 ),
				'visitors'   => absint( $row['visitors'] ?? 0 ),
			);
		}
		?>
		<div class="nt-analytics-chart-card">
			<div class="nt-analytics-chart-head">
				<div><strong><?php esc_html_e( 'Visitors and page views over time', 'localizepilot' ); ?></strong><span><?php esc_html_e( 'Daily activity for the selected filters', 'localizepilot' ); ?></span></div>
				<div class="nt-chart-legend"><span class="is-visitors"><i></i><?php esc_html_e( 'Unique visitors', 'localizepilot' ); ?></span><span class="is-views"><i></i><?php esc_html_e( 'Page views', 'localizepilot' ); ?></span></div>
			</div>
			<div class="nt-analytics-chart-scroll">
				<svg class="nt-analytics-chart" viewBox="0 0 <?php echo esc_attr( (string) $width ); ?> <?php echo esc_attr( (string) $height ); ?>" role="img" aria-label="<?php esc_attr_e( 'Daily visitor and page-view graph', 'localizepilot' ); ?>">
					<?php for ( $grid = 0; $grid <= 4; $grid++ ) : ?>
						<?php $grid_y = $top + ( $grid * $plot_h / 4 ); $grid_value = (int) round( $maximum * ( 1 - $grid / 4 ) ); ?>
						<line class="nt-chart-grid" x1="<?php echo esc_attr( (string) $left ); ?>" y1="<?php echo esc_attr( number_format( $grid_y, 2, '.', '' ) ); ?>" x2="<?php echo esc_attr( (string) ( $width - $right ) ); ?>" y2="<?php echo esc_attr( number_format( $grid_y, 2, '.', '' ) ); ?>"></line>
						<text class="nt-chart-axis-label" x="<?php echo esc_attr( (string) ( $left - 10 ) ); ?>" y="<?php echo esc_attr( number_format( $grid_y + 4, 2, '.', '' ) ); ?>" text-anchor="end"><?php echo esc_html( number_format_i18n( $grid_value ) ); ?></text>
					<?php endfor; ?>
					<?php if ( ! empty( $views_points ) ) : ?>
						<polyline class="nt-chart-line nt-chart-views" points="<?php echo esc_attr( implode( ' ', $views_points ) ); ?>"></polyline>
						<polyline class="nt-chart-line nt-chart-visitors" points="<?php echo esc_attr( implode( ' ', $visitor_points ) ); ?>"></polyline>
						<?php foreach ( $point_data as $index => $point ) : ?>
							<circle class="nt-chart-point nt-chart-point-views" cx="<?php echo esc_attr( number_format( $point['x'], 2, '.', '' ) ); ?>" cy="<?php echo esc_attr( number_format( $point['views_y'], 2, '.', '' ) ); ?>" r="3"><title><?php echo esc_html( $point['date'] . ': ' . $point['views'] . ' ' . __( 'views', 'localizepilot' ) ); ?></title></circle>
							<circle class="nt-chart-point nt-chart-point-visitors" cx="<?php echo esc_attr( number_format( $point['x'], 2, '.', '' ) ); ?>" cy="<?php echo esc_attr( number_format( $point['visitors_y'], 2, '.', '' ) ); ?>" r="3"><title><?php echo esc_html( $point['date'] . ': ' . $point['visitors'] . ' ' . __( 'visitors', 'localizepilot' ) ); ?></title></circle>
							<?php if ( 0 === $index || $index === $count - 1 || 0 === $index % max( 1, (int) ceil( $count / 6 ) ) ) : ?>
								<text class="nt-chart-date-label" x="<?php echo esc_attr( number_format( $point['x'], 2, '.', '' ) ); ?>" y="<?php echo esc_attr( (string) ( $height - 17 ) ); ?>" text-anchor="middle"><?php echo esc_html( wp_date( 'M j', strtotime( $point['date'] ) ) ); ?></text>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</svg>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $report Analytics report.
	 */
	private function render_analytics_table( array $report ): void {
		$items = (array) ( $report['items'] ?? array() );
		?>
		<div class="nt-analytics-table-head">
			<div><strong><?php esc_html_e( 'Language page report', 'localizepilot' ); ?></strong><span><?php /* translators: %d: Number of language pages. */ echo esc_html( sprintf( __( '%d matching language pages', 'localizepilot' ), absint( $report['total'] ?? 0 ) ) ); ?></span></div>
		</div>
		<?php if ( empty( $items ) ) : ?>
			<div class="nt-empty-state"><span class="dashicons dashicons-chart-area"></span><h3><?php esc_html_e( 'No visitor data found', 'localizepilot' ); ?></h3><p><?php esc_html_e( 'Enable analytics and open language pages, or change the current filters.', 'localizepilot' ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>
		<div class="nt-table-wrap">
			<table class="nt-cache-table nt-analytics-table">
				<thead><tr><th><?php esc_html_e( 'Page URL', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Language', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Unique visitors', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Page views', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Last visit', 'localizepilot' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><strong><?php echo esc_html( Analytics::display_url( (string) ( $item['page_url'] ?? '' ) ) ); ?></strong></td>
						<td><span class="nt-code-badge"><?php echo esc_html( strtoupper( sanitize_key( (string) ( $item['language'] ?? '' ) ) ) ); ?></span></td>
						<td><strong><?php echo esc_html( number_format_i18n( absint( $item['visitors'] ?? 0 ) ) ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( absint( $item['views'] ?? 0 ) ) ); ?></td>
						<td><?php echo ! empty( $item['last_visit'] ) ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $item['last_visit'] ) ) : esc_html( '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		$total_pages = max( 1, absint( $report['pages'] ?? 1 ) );
		if ( $total_pages <= 1 ) {
			return;
		}

		$filters = (array) ( $report['filters'] ?? array() );
		$placeholder = 999999999;
		$base = add_query_arg(
			array(
				'page'                => 'localizepilot',
				'tab'                 => 'analytics',
				'analytics_language'  => (string) ( $filters['language'] ?? 'all' ),
				'analytics_date_from' => (string) ( $filters['date_from'] ?? '' ),
				'analytics_date_to'   => (string) ( $filters['date_to'] ?? '' ),
				'analytics_url'       => (string) ( $filters['url'] ?? '' ),
				'analytics_page'      => $placeholder,
			),
			admin_url( 'admin.php' )
		);
		$base = str_replace( (string) $placeholder, '%#%', esc_url_raw( $base ) );
		$pagination = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => max( 1, absint( $report['page'] ?? 1 ) ),
				'total'     => $total_pages,
				'type'      => 'list',
				'prev_text' => esc_html__( 'Previous', 'localizepilot' ),
				'next_text' => esc_html__( 'Next', 'localizepilot' ),
			)
		);
		if ( $pagination ) {
			echo '<nav class="nt-pagination" aria-label="' . esc_attr__( 'Analytics report pagination', 'localizepilot' ) . '">' . wp_kses_post( $pagination ) . '</nav>';
		}
	}

	private function analytics_action_form(): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'           => 'localizepilot_analytics_action',
					'analytics_action' => 'clear_all',
				),
				admin_url( 'admin-post.php' )
			),
			'localizepilot_analytics_action'
		);
		$confirm = "return window.confirm('" . esc_js( __( 'Delete all LocalizePilot visitor analytics data?', 'localizepilot' ) ) . "');";
		echo '<a class="button button-secondary nt-danger" href="' . esc_url( $url ) . '" onclick="' . esc_attr( $confirm ) . '">' . esc_html__( 'Clear analytics data', 'localizepilot' ) . '</a>';
	}

	private function render_cache_tab( array $options, File_Cache $cache, array $stats ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache history pagination parameter.
		$cache_page = max( 1, absint( wp_unslash( $_GET['cache_page'] ?? 1 ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache history filter parameter.
		$cache_type = sanitize_key( wp_unslash( $_GET['cache_type'] ?? 'all' ) );
		$cache_type = in_array( $cache_type, array( 'all', 'page', 'snapshot' ), true ) ? $cache_type : 'all';
		$history          = $cache->history( $cache_page, 15, $cache_type );
		$history['items'] = $this->analytics->attach_counts( (array) $history['items'] );
		$this->form_start( 'cache' ); ?>
		<div class="nt-cache-summary"><div><span><?php esc_html_e( 'Rendered pages', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) $stats['count'] ); ?></strong></div><div><span><?php esc_html_e( 'Snapshots', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) ( $stats['snapshots'] ?? 0 ) ); ?></strong></div><div><span><?php esc_html_e( 'Total size', 'localizepilot' ); ?></span><strong><?php echo esc_html( size_format( (int) $stats['size'], 2 ) ); ?></strong></div><div><span><?php esc_html_e( 'Directory', 'localizepilot' ); ?></span><strong class="<?php echo esc_attr( $stats['writable'] ? 'is-good' : 'is-bad' ); ?>"><?php echo $stats['writable'] ? esc_html__( 'Writable', 'localizepilot' ) : esc_html__( 'Not writable', 'localizepilot' ); ?></strong></div></div>
		<section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Cache configuration', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'HTML and object cache', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Translated pages are saved as files under wp-content/cache/localizepilot.', 'localizepilot' ); ?></p></div></div><div class="nt-option-grid"><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'HTML file cache', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Save translated output as persistent HTML files.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[cache_enabled]" value="1" <?php checked( ! empty( $options['cache_enabled'] ) ); ?>><i></i></label><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'WordPress object cache', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Use wp_cache_get and wp_cache_set as a fast first layer.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[object_cache_enabled]" value="1" <?php checked( ! empty( $options['object_cache_enabled'] ) ); ?>><i></i></label></div><div class="nt-field nt-cache-duration"><label><?php esc_html_e( 'Cache lifetime', 'localizepilot' ); ?></label><div class="nt-number"><input type="number" min="1" max="8760" name="<?php echo esc_attr( Plugin::OPTION ); ?>[cache_hours]" value="<?php echo esc_attr( (string) $options['cache_hours'] ); ?>"><span><?php esc_html_e( 'hours', 'localizepilot' ); ?></span></div></div><div class="nt-cache-path"><span><?php esc_html_e( 'Cache directory', 'localizepilot' ); ?></span><code><?php echo esc_html( $stats['path'] ); ?></code></div></section>
		<?php $this->form_end( __( 'Save cache settings', 'localizepilot' ) ); ?>
		<section class="nt-card nt-cache-history-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Cache history', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Generated HTML history', 'localizepilot' ); ?></h2><p><?php /* translators: %d is the total number of cached HTML entries. */ echo esc_html( sprintf( __( '%d total HTML entries. Newest files appear first.', 'localizepilot' ), $history['total'] ) ); ?></p></div><div class="nt-cache-actions"><?php $this->cache_action_form( 'clear_expired', __( 'Clear expired pages', 'localizepilot' ), 'button', '', 'page', $cache_page, $cache_type ); ?><?php $this->cache_action_form( 'clear_all', __( 'Clear rendered cache', 'localizepilot' ), 'button button-secondary nt-danger', '', 'page', $cache_page, $cache_type ); ?><?php $this->cache_action_form( 'clear_snapshots', __( 'Clear snapshots', 'localizepilot' ), 'button button-secondary nt-danger', '', 'snapshot', $cache_page, $cache_type ); ?></div></div><div class="nt-history-filter"><a class="<?php echo esc_attr( 'all' === $cache_type ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => 'all' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'All', 'localizepilot' ); ?></a><a class="<?php echo esc_attr( 'page' === $cache_type ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => 'page' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Rendered pages', 'localizepilot' ); ?></a><a class="<?php echo esc_attr( 'snapshot' === $cache_type ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => 'snapshot' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Gutenberg snapshots', 'localizepilot' ); ?></a></div><?php $this->render_cache_table( $history, $cache_page, $cache_type ); ?></section>
		<?php
	}

	private function render_switcher_tab( array $options ): void {
		$this->form_start( 'switcher' ); ?>
		<div class="nt-two-column">
			<section class="nt-card">
				<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Global design', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Language switcher', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Set the default appearance used by the automatic header menu, shortcode, and Gutenberg block.', 'localizepilot' ); ?></p></div></div>
				<label class="nt-toggle-row"><span><strong><?php esc_html_e( 'Add switcher to header', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Disable this when you place the switcher with a shortcode or block.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[header_switcher]" value="1" <?php checked( ! empty( $options['header_switcher'] ) ); ?>><i></i></label>
				<div class="nt-field"><label><?php esc_html_e( 'Default layout', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[menu_style]"><option value="dropdown" <?php selected( 'dropdown', $options['menu_style'] ); ?>><?php esc_html_e( 'Dropdown', 'localizepilot' ); ?></option><option value="inline" <?php selected( 'inline', $options['menu_style'] ); ?>><?php esc_html_e( 'Inline links', 'localizepilot' ); ?></option></select></div>
				<div class="nt-field"><label><?php esc_html_e( 'Default alignment', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[menu_position]"><option value="start" <?php selected( 'start', $options['menu_position'] ); ?>><?php esc_html_e( 'Start', 'localizepilot' ); ?></option><option value="center" <?php selected( 'center', $options['menu_position'] ); ?>><?php esc_html_e( 'Center', 'localizepilot' ); ?></option><option value="end" <?php selected( 'end', $options['menu_position'] ); ?>><?php esc_html_e( 'End', 'localizepilot' ); ?></option></select></div>
				<div class="nt-field"><label><?php esc_html_e( 'Language labels', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[language_label]"><option value="native" <?php selected( 'native', $options['language_label'] ); ?>><?php esc_html_e( 'Native names', 'localizepilot' ); ?></option><option value="english" <?php selected( 'english', $options['language_label'] ); ?>><?php esc_html_e( 'English names', 'localizepilot' ); ?></option><option value="code" <?php selected( 'code', $options['language_label'] ); ?>><?php esc_html_e( 'Language codes', 'localizepilot' ); ?></option></select></div>
			</section>
			<section class="nt-card nt-preview-card">
				<span class="nt-section-kicker"><?php esc_html_e( 'Preview', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Switcher preview', 'localizepilot' ); ?></h2>
				<div class="nt-switcher-preview"><span>English</span><i></i><div><b>Deutsch</b><b>Français</b><b>Español</b><b>العربية</b></div></div>
				<p><?php esc_html_e( 'Shortcode and block settings can override these defaults for an individual placement.', 'localizepilot' ); ?></p>
			</section>
		</div>
		<?php $this->form_end( __( 'Save switcher settings', 'localizepilot' ) ); ?>

		<div class="nt-switcher-tools">
			<section class="nt-card nt-shortcode-card">
				<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Shortcode', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Place it anywhere', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Paste the shortcode into posts, pages, widgets, templates, or supported page builders.', 'localizepilot' ); ?></p></div><span class="dashicons dashicons-shortcode"></span></div>
				<div class="nt-code-copy"><code id="localizepilot-shortcode-basic">[localizepilot_switcher]</code><button type="button" class="button nt-copy-code" data-copy-target="localizepilot-shortcode-basic"><?php esc_html_e( 'Copy', 'localizepilot' ); ?></button></div>
				<h3><?php esc_html_e( 'Example with overrides', 'localizepilot' ); ?></h3>
				<div class="nt-code-copy"><code id="localizepilot-shortcode-example">[localizepilot_switcher style="inline" labels="native" alignment="center"]</code><button type="button" class="button nt-copy-code" data-copy-target="localizepilot-shortcode-example"><?php esc_html_e( 'Copy', 'localizepilot' ); ?></button></div>
				<div class="nt-shortcode-attributes">
					<div><code>style</code><span><?php esc_html_e( 'inherit, dropdown, inline', 'localizepilot' ); ?></span></div>
					<div><code>labels</code><span><?php esc_html_e( 'inherit, native, english, code', 'localizepilot' ); ?></span></div>
					<div><code>alignment</code><span><?php esc_html_e( 'inherit, start, center, end', 'localizepilot' ); ?></span></div>
					<div><code>class</code><span><?php esc_html_e( 'Optional custom CSS class', 'localizepilot' ); ?></span></div>
				</div>
			</section>

			<section class="nt-card nt-block-card">
				<div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Gutenberg block', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'LocalizePilot Language Switcher', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Add the native dynamic block from the WordPress block inserter.', 'localizepilot' ); ?></p></div><span class="dashicons dashicons-block-default"></span></div>
				<ol class="nt-steps"><li><span>1</span><p><?php esc_html_e( 'Open a post, page, template, header, or navigation area in the block editor.', 'localizepilot' ); ?></p></li><li><span>2</span><p><?php esc_html_e( 'Search for “LocalizePilot Language Switcher”.', 'localizepilot' ); ?></p></li><li><span>3</span><p><?php esc_html_e( 'Choose layout, label format, and alignment from the block sidebar.', 'localizepilot' ); ?></p></li></ol>
				<div class="nt-block-feature-list"><span><i class="dashicons dashicons-update"></i><?php esc_html_e( 'Dynamic current-page URLs', 'localizepilot' ); ?></span><span><i class="dashicons dashicons-admin-site-alt3"></i><?php esc_html_e( 'Uses enabled languages', 'localizepilot' ); ?></span><span><i class="dashicons dashicons-admin-customizer"></i><?php esc_html_e( 'Per-block overrides', 'localizepilot' ); ?></span></div>
			</section>
		</div>
		<?php
	}

	private function render_cache_table(
		array $history,
		int $cache_page,
		string $cache_type
	): void {
		if ( empty( $history['items'] ) ) {
			?>
			<div class="nt-empty-state">
				<span class="dashicons dashicons-database-remove"></span>

				<h3>
					<?php esc_html_e( 'No cache history found', 'localizepilot' ); ?>
				</h3>

				<p>
					<?php
					esc_html_e(
						'Open a translated page or save a Gutenberg translation to create HTML files.',
						'localizepilot'
					);
					?>
				</p>
			</div>
			<?php

			return;
		}
		?>

		<div class="nt-table-wrap">
			<table class="nt-cache-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cache entry', 'localizepilot' ); ?></th>
						<th><?php esc_html_e( 'Type', 'localizepilot' ); ?></th>
						<th><?php esc_html_e( 'Language', 'localizepilot' ); ?></th>
						<th><?php esc_html_e( 'Visitors', 'localizepilot' ); ?></th>
						<th><?php esc_html_e( 'Provider / status', 'localizepilot' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'localizepilot' ); ?></th>
						<th><?php esc_html_e( 'Size', 'localizepilot' ); ?></th>
						<th>
							<span class="screen-reader-text">
								<?php esc_html_e( 'Actions', 'localizepilot' ); ?>
							</span>
						</th>
					</tr>
				</thead>

				<tbody>
					<?php foreach ( $history['items'] as $item ) : ?>
						<?php
						$item_key      = isset( $item['key'] )
							? sanitize_file_name( (string) $item['key'] )
							: '';

						$item_type     = isset( $item['type'] )
							? sanitize_key( (string) $item['type'] )
							: 'page';

						$item_url      = isset( $item['url'] )
							? (string) $item['url']
							: '';

						$item_language = isset( $item['language'] )
							? sanitize_key( (string) $item['language'] )
							: '';

						$item_provider = isset( $item['provider'] )
							? sanitize_text_field( (string) $item['provider'] )
							: '';

						$item_status   = isset( $item['status'] )
							? sanitize_text_field( (string) $item['status'] )
							: '';

						$item_modified = isset( $item['modified'] )
							? absint( $item['modified'] )
							: 0;

						$item_bytes    = isset( $item['bytes'] )
							? absint( $item['bytes'] )
							: 0;

						$item_visitors = absint( $item['visitors'] ?? 0 );
						$item_views    = absint( $item['views'] ?? 0 );

						$is_expired    = ! empty( $item['expired'] );

						$display_name = '' !== $item_url
							? Analytics::display_url( $item_url )
							: $item_key . '.html';

						$provider_status = $item_provider;

						if ( '' === $provider_status ) {
							$provider_status = $item_status;
						}

						if ( '' === $provider_status ) {
							$provider_status = '—';
						}
						?>

						<tr class="<?php echo esc_attr( $is_expired ? 'is-expired' : '' ); ?>">
							<td>
								<strong>
									<?php echo esc_html( $display_name ); ?>
								</strong>

								<small>
									<?php echo esc_html( $item_key . '.html' ); ?>

									<?php if ( $is_expired ) : ?>
										&middot;
										<?php esc_html_e( 'Expired', 'localizepilot' ); ?>
									<?php endif; ?>
								</small>
							</td>

							<td>
								<span
									class="nt-type-badge <?php echo esc_attr( 'snapshot' === $item_type ? 'is-snapshot' : '' ); ?>"
								>
									<?php
									echo esc_html(
										'snapshot' === $item_type
											? __( 'Snapshot', 'localizepilot' )
											: __( 'Page', 'localizepilot' )
									);
									?>
								</span>
							</td>

							<td>
								<span class="nt-code-badge">
									<?php echo esc_html( strtoupper( $item_language ) ); ?>
								</span>
							</td>

							<td class="nt-cache-visitors">
								<strong><?php echo esc_html( number_format_i18n( $item_visitors ) ); ?></strong>
								<small>
									<?php
									printf(
										/* translators: %s: Number of page views. */
										esc_html__( '%s views', 'localizepilot' ),
										esc_html( number_format_i18n( $item_views ) )
									);
									?>
								</small>
							</td>

							<td>
								<?php echo esc_html( ucfirst( $provider_status ) ); ?>
							</td>

							<td>
								<?php
								if ( $item_modified > 0 ) {
									printf(
										/* translators: %s: Human-readable time difference. */
										esc_html__( '%s ago', 'localizepilot' ),
										esc_html(
											human_time_diff(
												$item_modified,
												current_time( 'timestamp' )
											)
										)
									);
								} else {
									echo esc_html( '—' );
								}
								?>
							</td>

							<td>
								<?php echo esc_html( size_format( $item_bytes, 1 ) ); ?>
							</td>

							<td>
								<?php
								$this->cache_action_form(
									'delete',
									__( 'Delete', 'localizepilot' ),
									'button-link-delete',
									$item_key,
									$item_type,
									$cache_page,
									$cache_type
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
		$total_pages = isset( $history['pages'] )
			? absint( $history['pages'] )
			: 1;

		$current_page = isset( $history['page'] )
			? max( 1, absint( $history['page'] ) )
			: 1;

		if ( $total_pages <= 1 ) {
			return;
		}

		/*
		* Use a numeric placeholder because add_query_arg() encodes `%#%`.
		* Do not use esc_url() here because it converts "&" to "&#038;".
		*/
		$pagination_placeholder = 999999999;

		$pagination_base = add_query_arg(
			array(
				'page'       => 'localizepilot',
				'tab'        => 'cache',
				'cache_type' => sanitize_key( $cache_type ),
				'cache_page' => $pagination_placeholder,
			),
			admin_url( 'admin.php' )
		);

		$pagination_base = esc_url_raw( $pagination_base );

		$pagination_base = str_replace(
			(string) $pagination_placeholder,
			'%#%',
			$pagination_base
		);

		$pagination = paginate_links(
			array(
				'base'      => $pagination_base,
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'type'      => 'list',
				'prev_text' => esc_html__( 'Previous', 'localizepilot' ),
				'next_text' => esc_html__( 'Next', 'localizepilot' ),
			)
		);

		if ( $pagination ) :
			?>
			<nav
				class="nt-pagination"
				aria-label="<?php esc_attr_e( 'Cache history pagination', 'localizepilot' ); ?>"
			>
				<?php echo wp_kses_post( $pagination ); ?>
			</nav>
			<?php
		endif;
	}

	private function cache_action_form( string $action, string $label, string $class, string $key = '', string $item_type = 'page', int $cache_page = 1, string $filter_type = 'all' ): void {
		$args = array( 'action' => 'next_translate_cache_action', 'cache_action' => $action, 'cache_item_type' => $item_type, 'cache_page' => $cache_page, 'cache_type' => $filter_type );
		if ( '' !== $key ) { $args['cache_key'] = $key; }
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'next_translate_cache_action' );
		echo ('<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>');
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
		} elseif ( 'delete' === $action ) {
			$key     = sanitize_text_field( wp_unslash( $_GET['cache_key'] ?? '' ) );
			$deleted = $cache->delete_history_item( $item_type, $key );
			$message = $deleted ? __( 'Cache entry deleted.', 'localizepilot' ) : __( 'Cache entry was not found.', 'localizepilot' );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_page' => $cache_page, 'cache_type' => $filter_type, 'localizepilot_cache_message' => $message ), admin_url( 'admin.php' ) ) );
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

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                              => 'localizepilot',
					'tab'                               => 'analytics',
					'localizepilot_analytics_message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
