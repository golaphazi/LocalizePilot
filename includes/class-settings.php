<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Settings {
	private Usage_Limiter $limiter;

	public function __construct( Usage_Limiter $limiter ) {
		$this->limiter = $limiter;
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 5 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_next_translate_test_api', array( $this, 'test_api' ) );
		add_action( 'admin_post_next_translate_cache_action', array( $this, 'cache_action' ) );
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
			$output['enabled']              = empty( $input['enabled'] ) ? 0 : 1;
			$output['translation_provider'] = in_array( $input['translation_provider'] ?? '', array( 'translatex', 'google' ), true ) ? sanitize_key( $input['translation_provider'] ) : 'translatex';
			$output['translatex_api_key']   = $this->sanitize_secret( $input, $old, 'translatex_api_key', 'clear_translatex_api_key' );
			$output['google_api_key']       = $this->sanitize_secret( $input, $old, 'google_api_key', 'clear_google_api_key' );
			$output['daily_limit_enabled']  = empty( $input['daily_limit_enabled'] ) ? 0 : 1;
			$output['daily_limit']          = min( 10000, max( 1, absint( $input['daily_limit'] ?? 10 ) ) );
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
		} elseif ( 'switcher' === $tab ) {
			$output['header_switcher']  = empty( $input['header_switcher'] ) ? 0 : 1;
			$output['menu_style']       = in_array( $input['menu_style'] ?? '', array( 'dropdown', 'inline' ), true ) ? sanitize_key( $input['menu_style'] ) : 'dropdown';
			$output['menu_position']    = in_array( $input['menu_position'] ?? '', array( 'start', 'end' ), true ) ? sanitize_key( $input['menu_position'] ) : 'end';
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

		wp_enqueue_style( 'localizepilot-admin', NEXT_TRANSLATE_URL . 'assets/admin.css', array(), NEXT_TRANSLATE_VERSION );
		if ( ! $is_settings ) {
			return;
		}

		wp_enqueue_script( 'localizepilot-admin', NEXT_TRANSLATE_URL . 'assets/admin.js', array(), NEXT_TRANSLATE_VERSION, true );
		wp_localize_script(
			'localizepilot-admin',
			'nextTranslateAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'next_translate_test_api' ),
				'testing' => __( 'Testing…', 'localizepilot' ),
				'test'    => __( 'Test connection', 'localizepilot' ),
				'show'    => __( 'Show', 'localizepilot' ),
				'hide'    => __( 'Hide', 'localizepilot' ),
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
		$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'dashboard' ) );
		$active_tab = isset( $tabs[ $active_tab ] ) ? $active_tab : 'dashboard';
		?>
		<div class="wrap next-translate-admin localizepilot-admin">
			<header class="nt-admin-hero">
				<div class="nt-brand-lockup"><span class="nt-brand-icon">LP</span><div><span class="nt-eyebrow"><?php esc_html_e( 'Multilingual Content & Media', 'localizepilot' ); ?></span><h1><?php esc_html_e( 'LocalizePilot', 'localizepilot' ); ?></h1><p><?php esc_html_e( 'Translate, review, edit, cache, and personalize every WordPress language experience.', 'localizepilot' ); ?></p></div></div>
				<div class="nt-hero-statuses"><span class="nt-pill <?php echo empty( $options['enabled'] ) ? 'is-off' : 'is-on'; ?>"><i></i><?php echo empty( $options['enabled'] ) ? esc_html__( 'Translation off', 'localizepilot' ) : esc_html__( 'Translation active', 'localizepilot' ); ?></span><span class="nt-pill"><strong><?php echo esc_html( 'google' === $provider ? 'Google' : 'TranslateX' ); ?></strong></span></div>
			</header>

			<?php settings_errors(); ?>
			<?php if ( ! empty( $_GET['localizepilot_cache_message'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['localizepilot_cache_message'] ) ) ); ?></p></div><?php endif; ?>

			<nav class="nt-tabs" aria-label="<?php esc_attr_e( 'LocalizePilot settings', 'localizepilot' ); ?>">
				<?php foreach ( $tabs as $slug => $tab ) : ?><a class="nt-tab <?php echo $slug === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"><span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span><?php echo esc_html( $tab['label'] ); ?></a><?php endforeach; ?>
			</nav>

			<div class="nt-tab-panel">
				<?php
				if ( 'dashboard' === $active_tab ) {
					$this->render_dashboard_tab( $options, $languages, $usage, $limit, $stats, $provider );
				} elseif ( 'languages' === $active_tab ) {
					$this->render_languages_tab( $options, $languages );
				} elseif ( 'translation' === $active_tab ) {
					$this->render_translation_tab( $options );
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
			'cache'       => array( 'label' => __( 'Cache Management', 'localizepilot' ), 'icon' => 'dashicons-database' ),
			'switcher'    => array( 'label' => __( 'Language Switcher', 'localizepilot' ), 'icon' => 'dashicons-menu-alt3' ),
		);
	}

	private function form_start( string $tab ): void {
		echo '<form method="post" action="options.php" class="nt-settings-form">';
		settings_fields( 'next_translate_group' );
		echo '<input type="hidden" name="' . esc_attr( Plugin::OPTION ) . '[settings_tab]" value="' . esc_attr( $tab ) . '">';
	}

	private function form_end( string $label = '' ): void {
		$label = $label ?: __( 'Save changes', 'localizepilot' );
		echo '<div class="nt-save-bar"><div><strong>' . esc_html__( 'Save your LocalizePilot settings', 'localizepilot' ) . '</strong><span>' . esc_html__( 'Updated settings invalidate rendered page cache only. Gutenberg translations remain safe.', 'localizepilot' ) . '</span></div>';
		submit_button( $label, 'primary', 'submit', false );
		echo '</div></form>';
	}

	private function render_dashboard_tab( array $options, array $languages, array $usage, int $limit, array $stats, string $provider ): void {
		$this->form_start( 'dashboard' ); ?>
		<div class="nt-stat-grid"><div class="nt-stat"><span><?php esc_html_e( 'Rendered cache', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) $stats['count'] ); ?></strong><small><?php echo esc_html( sprintf( __( '%d expired', 'localizepilot' ), $stats['expired'] ) ); ?></small></div><div class="nt-stat"><span><?php esc_html_e( 'Translation snapshots', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) ( $stats['snapshots'] ?? 0 ) ); ?></strong><small><?php esc_html_e( 'Gutenberg-generated HTML', 'localizepilot' ); ?></small></div><div class="nt-stat"><span><?php esc_html_e( 'Daily API use', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) $usage['count'] ); ?><em>/<?php echo esc_html( (string) $limit ); ?></em></strong><small><?php echo empty( $options['daily_limit_enabled'] ) ? esc_html__( 'Limit disabled', 'localizepilot' ) : esc_html( sprintf( __( '%d remaining', 'localizepilot' ), max( 0, $limit - $usage['count'] ) ) ); ?></small></div><div class="nt-stat"><span><?php esc_html_e( 'Languages', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) ( count( (array) $options['enabled_languages'] ) + 1 ) ); ?></strong><small><?php esc_html_e( 'Including English', 'localizepilot' ); ?></small></div></div>
		<div class="nt-dashboard-grid">
			<section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'System', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Translation overview', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Control the main translation service and monitor current usage.', 'localizepilot' ); ?></p></div></div><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'Enable LocalizePilot', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Enable language URLs and frontend translation.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>><i></i></label><div class="nt-quick-links"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Translation_Manager::POST_TYPE ) ); ?>"><span class="dashicons dashicons-edit-page"></span><strong><?php esc_html_e( 'Manage translations', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Edit Gutenberg language versions', 'localizepilot' ); ?></small></a><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache' ), admin_url( 'admin.php' ) ) ); ?>"><span class="dashicons dashicons-database"></span><strong><?php esc_html_e( 'Open cache history', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Review and remove HTML cache', 'localizepilot' ); ?></small></a></div></section>
			<section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'API connection', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Translation provider', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Choose TranslateX or Google Cloud Translation.', 'localizepilot' ); ?></p></div></div><div class="nt-provider-grid"><label class="nt-provider-card"><input type="radio" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translation_provider]" value="translatex" <?php checked( 'translatex', $provider ); ?>><span class="nt-provider-mark">TX</span><span><strong>TranslateX</strong><small><?php esc_html_e( 'Text batch translation API', 'localizepilot' ); ?></small></span><i></i></label><label class="nt-provider-card"><input type="radio" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translation_provider]" value="google" <?php checked( 'google', $provider ); ?>><span class="nt-provider-mark">G</span><span><strong><?php esc_html_e( 'Google Translation', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Cloud Translation Basic v2', 'localizepilot' ); ?></small></span><i></i></label></div>
			<div class="nt-provider-panel" data-provider="translatex"><div class="nt-field"><label><?php esc_html_e( 'TranslateX API key', 'localizepilot' ); ?></label><div class="nt-input-action"><input type="password" name="<?php echo esc_attr( Plugin::OPTION ); ?>[translatex_api_key]" value="" placeholder="<?php echo empty( $options['translatex_api_key'] ) ? esc_attr__( 'Paste TranslateX API key', 'localizepilot' ) : esc_attr__( 'Saved key · leave blank to keep it', 'localizepilot' ); ?>" autocomplete="new-password"><button type="button" class="button nt-reveal-key"><?php esc_html_e( 'Show', 'localizepilot' ); ?></button></div><label class="nt-mini-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[clear_translatex_api_key]" value="1"> <?php esc_html_e( 'Remove saved key', 'localizepilot' ); ?></label></div></div>
			<div class="nt-provider-panel" data-provider="google"><div class="nt-field"><label><?php esc_html_e( 'Google Translation API key', 'localizepilot' ); ?></label><div class="nt-input-action"><input type="password" name="<?php echo esc_attr( Plugin::OPTION ); ?>[google_api_key]" value="" placeholder="<?php echo empty( $options['google_api_key'] ) ? esc_attr__( 'Paste Google API key', 'localizepilot' ) : esc_attr__( 'Saved key · leave blank to keep it', 'localizepilot' ); ?>" autocomplete="new-password"><button type="button" class="button nt-reveal-key"><?php esc_html_e( 'Show', 'localizepilot' ); ?></button></div><label class="nt-mini-check"><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[clear_google_api_key]" value="1"> <?php esc_html_e( 'Remove saved key', 'localizepilot' ); ?></label></div></div>
			<div class="nt-api-actions"><button type="button" class="button button-secondary" id="next-translate-test-api"><?php esc_html_e( 'Test connection', 'localizepilot' ); ?></button><select id="next-translate-test-language"><?php foreach ( $languages as $code => $language ) : if ( 'en' !== $code ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $language['name'] ); ?></option><?php endif; endforeach; ?></select><span id="next-translate-test-result"></span></div><div class="nt-field-row"><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'Daily API limit', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Restrict new automatic translations each day.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[daily_limit_enabled]" value="1" <?php checked( ! empty( $options['daily_limit_enabled'] ) ); ?>><i></i></label><div class="nt-field"><label><?php esc_html_e( 'Translations per day', 'localizepilot' ); ?></label><input type="number" min="1" max="10000" name="<?php echo esc_attr( Plugin::OPTION ); ?>[daily_limit]" value="<?php echo esc_attr( (string) $limit ); ?>"></div></div></section>
		</div>
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

	private function render_cache_tab( array $options, File_Cache $cache, array $stats ): void {
		$cache_page = max( 1, absint( $_GET['cache_page'] ?? 1 ) );
		$cache_type = sanitize_key( wp_unslash( $_GET['cache_type'] ?? 'all' ) );
		$cache_type = in_array( $cache_type, array( 'all', 'page', 'snapshot' ), true ) ? $cache_type : 'all';
		$history    = $cache->history( $cache_page, 15, $cache_type );
		$this->form_start( 'cache' ); ?>
		<div class="nt-cache-summary"><div><span><?php esc_html_e( 'Rendered pages', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) $stats['count'] ); ?></strong></div><div><span><?php esc_html_e( 'Snapshots', 'localizepilot' ); ?></span><strong><?php echo esc_html( (string) ( $stats['snapshots'] ?? 0 ) ); ?></strong></div><div><span><?php esc_html_e( 'Total size', 'localizepilot' ); ?></span><strong><?php echo esc_html( size_format( (int) $stats['size'], 2 ) ); ?></strong></div><div><span><?php esc_html_e( 'Directory', 'localizepilot' ); ?></span><strong class="<?php echo $stats['writable'] ? 'is-good' : 'is-bad'; ?>"><?php echo $stats['writable'] ? esc_html__( 'Writable', 'localizepilot' ) : esc_html__( 'Not writable', 'localizepilot' ); ?></strong></div></div>
		<section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Cache configuration', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'HTML and object cache', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Translated pages are saved as files under wp-content/cache/localizepilot.', 'localizepilot' ); ?></p></div></div><div class="nt-option-grid"><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'HTML file cache', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Save translated output as persistent HTML files.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[cache_enabled]" value="1" <?php checked( ! empty( $options['cache_enabled'] ) ); ?>><i></i></label><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'WordPress object cache', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'Use wp_cache_get and wp_cache_set as a fast first layer.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[object_cache_enabled]" value="1" <?php checked( ! empty( $options['object_cache_enabled'] ) ); ?>><i></i></label></div><div class="nt-field nt-cache-duration"><label><?php esc_html_e( 'Cache lifetime', 'localizepilot' ); ?></label><div class="nt-number"><input type="number" min="1" max="8760" name="<?php echo esc_attr( Plugin::OPTION ); ?>[cache_hours]" value="<?php echo esc_attr( (string) $options['cache_hours'] ); ?>"><span><?php esc_html_e( 'hours', 'localizepilot' ); ?></span></div></div><div class="nt-cache-path"><span><?php esc_html_e( 'Cache directory', 'localizepilot' ); ?></span><code><?php echo esc_html( $stats['path'] ); ?></code></div></section>
		<?php $this->form_end( __( 'Save cache settings', 'localizepilot' ) ); ?>
		<section class="nt-card nt-cache-history-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Cache history', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Generated HTML history', 'localizepilot' ); ?></h2><p><?php echo esc_html( sprintf( __( '%d total HTML entries. Newest files appear first.', 'localizepilot' ), $history['total'] ) ); ?></p></div><div class="nt-cache-actions"><?php $this->cache_action_form( 'clear_expired', __( 'Clear expired pages', 'localizepilot' ), 'button', '', 'page', $cache_page, $cache_type ); ?><?php $this->cache_action_form( 'clear_all', __( 'Clear rendered cache', 'localizepilot' ), 'button button-secondary nt-danger', '', 'page', $cache_page, $cache_type ); ?><?php $this->cache_action_form( 'clear_snapshots', __( 'Clear snapshots', 'localizepilot' ), 'button button-secondary nt-danger', '', 'snapshot', $cache_page, $cache_type ); ?></div></div><div class="nt-history-filter"><a class="<?php echo 'all' === $cache_type ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => 'all' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'All', 'localizepilot' ); ?></a><a class="<?php echo 'page' === $cache_type ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => 'page' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Rendered pages', 'localizepilot' ); ?></a><a class="<?php echo 'snapshot' === $cache_type ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => 'snapshot' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Gutenberg snapshots', 'localizepilot' ); ?></a></div><?php $this->render_cache_table( $history, $cache_page, $cache_type ); ?></section>
		<?php
	}

	private function render_switcher_tab( array $options ): void {
		$this->form_start( 'switcher' ); ?>
		<div class="nt-two-column"><section class="nt-card"><div class="nt-card-head"><div><span class="nt-section-kicker"><?php esc_html_e( 'Header menu', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Language switcher', 'localizepilot' ); ?></h2><p><?php esc_html_e( 'Add the current-page language menu automatically to the site header.', 'localizepilot' ); ?></p></div></div><label class="nt-toggle-row"><span><strong><?php esc_html_e( 'Add switcher to header', 'localizepilot' ); ?></strong><small><?php esc_html_e( 'No shortcode or theme editing required.', 'localizepilot' ); ?></small></span><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[header_switcher]" value="1" <?php checked( ! empty( $options['header_switcher'] ) ); ?>><i></i></label><div class="nt-field"><label><?php esc_html_e( 'Menu style', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[menu_style]"><option value="dropdown" <?php selected( 'dropdown', $options['menu_style'] ); ?>><?php esc_html_e( 'Dropdown', 'localizepilot' ); ?></option><option value="inline" <?php selected( 'inline', $options['menu_style'] ); ?>><?php esc_html_e( 'Inline links', 'localizepilot' ); ?></option></select></div><div class="nt-field"><label><?php esc_html_e( 'Position', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[menu_position]"><option value="end" <?php selected( 'end', $options['menu_position'] ); ?>><?php esc_html_e( 'End of header', 'localizepilot' ); ?></option><option value="start" <?php selected( 'start', $options['menu_position'] ); ?>><?php esc_html_e( 'Start of header', 'localizepilot' ); ?></option></select></div><div class="nt-field"><label><?php esc_html_e( 'Language labels', 'localizepilot' ); ?></label><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[language_label]"><option value="native" <?php selected( 'native', $options['language_label'] ); ?>><?php esc_html_e( 'Native names', 'localizepilot' ); ?></option><option value="english" <?php selected( 'english', $options['language_label'] ); ?>><?php esc_html_e( 'English names', 'localizepilot' ); ?></option><option value="code" <?php selected( 'code', $options['language_label'] ); ?>><?php esc_html_e( 'Language codes', 'localizepilot' ); ?></option></select></div></section><section class="nt-card nt-preview-card"><span class="nt-section-kicker"><?php esc_html_e( 'Preview', 'localizepilot' ); ?></span><h2><?php esc_html_e( 'Header switcher preview', 'localizepilot' ); ?></h2><div class="nt-switcher-preview"><span>English</span><i></i><div><b>Deutsch</b><b>Français</b><b>Español</b><b>العربية</b></div></div><p><?php esc_html_e( 'The final style inherits your theme typography and can be customized with CSS.', 'localizepilot' ); ?></p></section></div>
		<?php $this->form_end( __( 'Save switcher settings', 'localizepilot' ) );
	}

	private function render_cache_table( array $history, int $cache_page, string $cache_type ): void {
		if ( empty( $history['items'] ) ) {
			echo '<div class="nt-empty-state"><span class="dashicons dashicons-database-remove"></span><h3>' . esc_html__( 'No cache history found', 'localizepilot' ) . '</h3><p>' . esc_html__( 'Open a translated page or save a Gutenberg translation to create HTML files.', 'localizepilot' ) . '</p></div>';
			return;
		}
		?>
		<div class="nt-table-wrap"><table class="nt-cache-table"><thead><tr><th><?php esc_html_e( 'Cache entry', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Type', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Language', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Provider / status', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Updated', 'localizepilot' ); ?></th><th><?php esc_html_e( 'Size', 'localizepilot' ); ?></th><th></th></tr></thead><tbody><?php foreach ( $history['items'] as $item ) : ?><tr class="<?php echo ! empty( $item['expired'] ) ? 'is-expired' : ''; ?>"><td><strong><?php echo esc_html( $item['url'] ?: $item['key'] . '.html' ); ?></strong><small><?php echo esc_html( $item['key'] . '.html' ); ?><?php if ( ! empty( $item['expired'] ) ) : ?> · <?php esc_html_e( 'Expired', 'localizepilot' ); ?><?php endif; ?></small></td><td><span class="nt-type-badge <?php echo 'snapshot' === $item['type'] ? 'is-snapshot' : ''; ?>"><?php echo 'snapshot' === $item['type'] ? esc_html__( 'Snapshot', 'localizepilot' ) : esc_html__( 'Page', 'localizepilot' ); ?></span></td><td><span class="nt-code-badge"><?php echo esc_html( strtoupper( (string) $item['language'] ) ); ?></span></td><td><?php echo esc_html( ucfirst( (string) ( $item['provider'] ?: $item['status'] ?: '—' ) ) ); ?></td><td><?php echo $item['modified'] ? esc_html( human_time_diff( (int) $item['modified'], time() ) . ' ' . __( 'ago', 'localizepilot' ) ) : '—'; ?></td><td><?php echo esc_html( size_format( (int) $item['bytes'], 1 ) ); ?></td><td><?php $this->cache_action_form( 'delete', __( 'Delete', 'localizepilot' ), 'button-link-delete', (string) $item['key'], (string) $item['type'], $cache_page, $cache_type ); ?></td></tr><?php endforeach; ?></tbody></table></div>
		<?php if ( $history['pages'] > 1 ) : $base = add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_type' => $cache_type, 'cache_page' => 999999999 ), admin_url( 'admin.php' ) ); ?><div class="nt-pagination"><?php echo wp_kses_post( paginate_links( array( 'base' => str_replace( '999999999', '%#%', esc_url( $base ) ), 'format' => '', 'current' => $history['page'], 'total' => $history['pages'], 'type' => 'list', 'prev_text' => '‹', 'next_text' => '›' ) ) ); ?></div><?php endif;
	}

	private function cache_action_form( string $action, string $label, string $class, string $key = '', string $item_type = 'page', int $cache_page = 1, string $filter_type = 'all' ): void {
		$args = array( 'action' => 'next_translate_cache_action', 'cache_action' => $action, 'cache_item_type' => $item_type, 'cache_page' => $cache_page, 'cache_type' => $filter_type );
		if ( '' !== $key ) { $args['cache_key'] = $key; }
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'next_translate_cache_action' );
		echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	public function test_api(): void {
		check_ajax_referer( 'next_translate_test_api', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'localizepilot' ) ), 403 ); }
		$language = sanitize_key( wp_unslash( $_POST['language'] ?? 'es' ) );
		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? 'translatex' ) );
		$key      = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		$settings = Plugin::instance()->get_settings();
		$provider = in_array( $provider, array( 'translatex', 'google' ), true ) ? $provider : 'translatex';
		$settings['translation_provider'] = $provider;
		if ( '' !== $key ) { $settings[ 'google' === $provider ? 'google_api_key' : 'translatex_api_key' ] = $key; }
		try { $client = Client_Factory::make( $settings ); $result = $client->test( Language_Catalog::exists( $language ) ? $language : 'es' ); wp_send_json_success( array( 'message' => sprintf( __( '%1$s connected: %2$s', 'localizepilot' ), 'google' === $provider ? 'Google' : 'TranslateX', $result ) ) ); } catch ( \Throwable $exception ) { wp_send_json_error( array( 'message' => $exception->getMessage() ) ); }
	}

	public function cache_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'localizepilot' ) ); }
		check_admin_referer( 'next_translate_cache_action' );
		$cache       = new File_Cache( Plugin::instance()->get_settings() );
		$action      = sanitize_key( wp_unslash( $_REQUEST['cache_action'] ?? '' ) );
		$item_type   = sanitize_key( wp_unslash( $_REQUEST['cache_item_type'] ?? 'page' ) );
		$cache_page  = max( 1, absint( $_REQUEST['cache_page'] ?? 1 ) );
		$filter_type = sanitize_key( wp_unslash( $_REQUEST['cache_type'] ?? 'all' ) );
		$message     = __( 'No cache action was performed.', 'localizepilot' );
		if ( 'clear_all' === $action ) { $message = sprintf( __( 'Cleared %d rendered cache files.', 'localizepilot' ), $cache->clear_all() ); }
		elseif ( 'clear_expired' === $action ) { $message = sprintf( __( 'Cleared %d expired cache entries.', 'localizepilot' ), $cache->clear_expired() ); }
		elseif ( 'clear_snapshots' === $action ) { $message = sprintf( __( 'Cleared %d translation snapshot files.', 'localizepilot' ), $cache->clear_snapshots() ); }
		elseif ( 'delete' === $action ) { $key = sanitize_text_field( wp_unslash( $_REQUEST['cache_key'] ?? '' ) ); $deleted = $cache->delete_history_item( $item_type, $key ); $message = $deleted ? __( 'Cache entry deleted.', 'localizepilot' ) : __( 'Cache entry was not found.', 'localizepilot' ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'localizepilot', 'tab' => 'cache', 'cache_page' => $cache_page, 'cache_type' => $filter_type, 'localizepilot_cache_message' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
