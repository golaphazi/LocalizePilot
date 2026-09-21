<?php

namespace LocalizePilot;

defined('ABSPATH') || exit;

final class Translation_Manager
{
	public const POST_TYPE       = 'next_translation';
	public const META_SOURCE_ID  = '_next_translate_source_id';
	public const META_LANGUAGE   = '_next_translate_language';
	public const META_STATUS     = '_next_translate_status';
	public const META_SOURCE_HASH = '_next_translate_source_hash';
	public const META_PROVIDER   = '_next_translate_provider';
	private const PARENT_MIGRATION_OPTION = 'localizepilot_translation_parent_migrated';

	private Router $router;
	private Usage_Limiter $limiter;
	private array $settings = array();
	private array $runtime_cache = array();
	private bool $programmatic_update = false;

	public function __construct(Router $router, Usage_Limiter $limiter)
	{
		$this->router  = $router;
		$this->limiter = $limiter;
	}

	public function hooks(): void
	{
		add_action('init', array($this, 'register_post_type'), 5);
		add_action('init', array($this, 'migrate_translation_parents'), 20);
		add_action('add_meta_boxes_post', array($this, 'add_source_meta_box'));
		add_action('add_meta_boxes_page', array($this, 'add_source_meta_box'));
		add_action('add_meta_boxes_product', array($this, 'add_source_meta_box'));
		add_action('add_meta_boxes_' . self::POST_TYPE, array($this, 'add_translation_meta_box'));
		add_action('admin_post_next_translate_create_translation', array($this, 'create_translation_action'));
		add_action('admin_post_next_translate_refresh_translation', array($this, 'refresh_translation_action'));
		add_action('wp_after_insert_post', array($this, 'after_insert_post'), 20, 4);
		add_action('before_delete_post', array($this, 'before_delete_post'));

		add_filter('the_content', array($this, 'filter_content'), 1);
		add_filter('the_title', array($this, 'filter_title'), 1, 2);
		add_filter('get_the_excerpt', array($this, 'filter_excerpt'), 1, 2);
		add_filter('document_title_parts', array($this, 'filter_document_title'));
		add_filter('get_post_metadata', array($this, 'filter_post_metadata'), 10, 5);

		add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'translation_columns'));
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'translation_column_content'), 10, 2);
		add_filter('post_row_actions', array($this, 'translation_row_actions'), 10, 2);
		add_action('admin_notices', array($this, 'admin_notices'));
		add_action('restrict_manage_posts', array($this, 'render_admin_filters'));
		add_action('pre_get_posts', array($this, 'filter_admin_translations'));
	}

	public function register_post_type(): void
	{
		$labels = array(
			'name'               => __('Translation Pages', 'localizepilot'),
			'singular_name'      => __('Translation Page', 'localizepilot'),
			'menu_name'          => __('Translation Pages', 'localizepilot'),
			'add_new'            => __('Add Page', 'localizepilot'),
			'add_new_item'       => __('Add Page', 'localizepilot'),
			'edit_item'          => __('Edit Page', 'localizepilot'),
			'new_item'           => __('New Page', 'localizepilot'),
			'view_item'          => __('View Pages', 'localizepilot'),
			'search_items'       => __('Search Pages', 'localizepilot'),
			'not_found'          => __('No Pages found.', 'localizepilot'),
			'not_found_in_trash' => __('No Pages found in Trash.', 'localizepilot'),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'localizepilot',
				'show_in_rest'        => true,
				'rest_base'           => 'next-translations',
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array('create_posts' => 'do_not_allow'),
				'menu_icon'           => 'dashicons-translation',
			)
		);

		$this->register_meta();
	}


	/**
	 * Migrate legacy translation records to use post_parent for source lookups.
	 *
	 * This removes repeated post-meta queries while preserving the legacy source
	 * ID metadata used by existing installations and REST responses.
	 */
	public function migrate_translation_parents(): void
	{
		if (get_option(self::PARENT_MIGRATION_OPTION, false)) {
			return;
		}

		$translation_ids = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ($translation_ids as $translation_id) {
			$translation_id = absint($translation_id);
			$source_id      = absint(get_post_meta($translation_id, self::META_SOURCE_ID, true));
			$translation    = get_post($translation_id);

			if (! $source_id || ! $translation instanceof \WP_Post || (int) $translation->post_parent === $source_id) {
				continue;
			}

			$this->programmatic_update = true;
			wp_update_post(
				wp_slash(
					array(
						'ID'          => $translation_id,
						'post_parent' => $source_id,
					)
				)
			);
			$this->programmatic_update = false;
		}

		update_option(self::PARENT_MIGRATION_OPTION, 1, false);
	}

	private function register_meta(): void
	{
		register_post_meta(
			self::POST_TYPE,
			self::META_SOURCE_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function ($allowed, $meta_key, $post_id): bool {
					return current_user_can('edit_post', (int) $post_id);
				},
			)
		);

		foreach (array(self::META_LANGUAGE, self::META_STATUS, self::META_SOURCE_HASH, self::META_PROVIDER) as $meta_key) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ($allowed, $meta_key, $post_id): bool {
						return current_user_can('edit_post', (int) $post_id);
					},
				)
			);
		}
	}

	public static function statuses(): array
	{
		return array(
			'automatic'    => __('Automatic', 'localizepilot'),
			'edited'       => __('Edited', 'localizepilot'),
			'reviewed'     => __('Reviewed', 'localizepilot'),
			'needs_update' => __('Needs update', 'localizepilot'),
		);
	}

	public function add_source_meta_box(\WP_Post $post): void
	{
		add_meta_box(
			'next-translate-languages',
			__('LocalizePilot', 'localizepilot'),
			array($this, 'render_source_meta_box'),
			$post->post_type,
			'side',
			'high',
			array('__block_editor_compatible_meta_box' => true)
		);
	}

	public function render_source_meta_box(\WP_Post $post): void
	{
		if ('auto-draft' === $post->post_status) {
			echo ('<p>' . esc_html__('Save the source post before creating translations.', 'localizepilot') . '</p>');
			return;
		}

		$this->settings = Plugin::instance()->get_settings();
		$languages      = Language_Catalog::all();
		$enabled        = (array) ($this->settings['enabled_languages'] ?? array());
		$statuses       = self::statuses();

		echo ('<div class="next-translate-source-box">');
		foreach ($enabled as $language) {
			$language = sanitize_key($language);
			if (! isset($languages[$language])) {
				continue;
			}

			$translation = $this->get_translation($post->ID, $language);
			echo ('<div class="nt-source-language-row">');
			echo ('<div><strong>') . esc_html($languages[$language]['native']) . '</strong><small>' . esc_html(strtoupper($language)) . '</small></div>';

			if ($translation) {
				$status = (string) get_post_meta($translation->ID, self::META_STATUS, true);
				$status = isset($statuses[$status]) ? $status : 'automatic';
				echo ('<span class="nt-translation-status nt-status-' . esc_attr($status) . '">' . esc_html($statuses[$status]) . '</span>');
				echo ('<a class="button button-small" href="' . esc_url(get_edit_post_link($translation->ID, '')) . '">' . esc_html__('Edit', 'localizepilot') . '</a>');
			} else {
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'action'    => 'next_translate_create_translation',
							'source_id' => $post->ID,
							'language'  => $language,
						),
						admin_url('admin-post.php')
					),
					'next_translate_create_' . $post->ID . '_' . $language
				);
				echo ('<a class="button button-small button-primary" href="' . esc_url($url) . '">' . esc_html__('Create', 'localizepilot') . '</a>');
			}
			echo ('</div>');
		}
		echo ('</div>');
	}

	public function add_translation_meta_box(\WP_Post $post): void
	{
		add_meta_box(
			'next-translate-details',
			__('Translation details', 'localizepilot'),
			array($this, 'render_translation_meta_box'),
			self::POST_TYPE,
			'side',
			'high',
			array('__block_editor_compatible_meta_box' => true)
		);
	}

	public function render_translation_meta_box(\WP_Post $translation): void
	{
		$source_id   = absint(get_post_meta($translation->ID, self::META_SOURCE_ID, true));
		$language    = sanitize_key((string) get_post_meta($translation->ID, self::META_LANGUAGE, true));
		$status      = sanitize_key((string) get_post_meta($translation->ID, self::META_STATUS, true));
		$source_hash = (string) get_post_meta($translation->ID, self::META_SOURCE_HASH, true);
		$source      = get_post($source_id);
		$statuses    = self::statuses();
		$status      = isset($statuses[$status]) ? $status : 'automatic';
		$is_outdated = $source instanceof \WP_Post && ! hash_equals($source_hash, $this->source_hash($source));
		$cache       = new File_Cache(Plugin::instance()->get_settings());

		wp_nonce_field('next_translate_translation_meta', 'next_translate_translation_nonce');
		echo ('<div class="next-translate-details-box">');
		if ($source instanceof \WP_Post) {
			echo ('<p><strong>') . esc_html__('Source', 'localizepilot') . '</strong><br><a href="' . esc_url(get_edit_post_link($source_id, '')) . '">' . esc_html(get_the_title($source_id)) . '</a></p>';
		}
		echo ('<p><strong>') . esc_html__('Language', 'localizepilot') . '</strong><br><span class="nt-code-badge">' . esc_html(strtoupper($language)) . '</span></p>';
		if ($is_outdated) {
			echo ('<div class="notice notice-warning inline"><p>') . esc_html__('The source content changed after this translation was created.', 'localizepilot') . '</p></div>';
		}
		echo ('<p><label for="next_translate_status"><strong>') . esc_html__('Status', 'localizepilot') . '</strong></label><select id="next_translate_status" name="next_translate_status" style="width:100%;margin-top:6px">';
		foreach ($statuses as $value => $label) {
			echo ('<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>');
		}
		echo ('</select></p>');

		if ($source instanceof \WP_Post && '' !== $language) {
			$refresh_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'         => 'next_translate_refresh_translation',
						'translation_id' => $translation->ID,
					),
					admin_url('admin-post.php')
				),
				'next_translate_refresh_' . $translation->ID
			);
			$view_url = $this->router->localize_url(get_permalink($source_id), $language);
			echo ('<p><a class="button button-secondary" style="width:100%;text-align:center" href="' . esc_url($view_url) . '" target="_blank">' . esc_html__('View translated page', 'localizepilot') . '</a></p>');
			echo ('<p><a class="button" style="width:100%;text-align:center" href="' . esc_url($refresh_url) . '" onclick="return confirm(\'' . esc_js(__('This will replace the current title and content with a new automatic translation. Continue?', 'localizepilot')) . '\');">' . esc_html__('Refresh from API', 'localizepilot') . '</a></p>');
		}

		echo ('<p class="description"><strong>' . esc_html__('Page cache', 'localizepilot') . '</strong><br><code>' . esc_html(basename($cache->post_page_path($source_id, $language))) . '</code></p>');
		echo ('<p class="description"><strong>' . esc_html__('Content snapshot', 'localizepilot') . '</strong><br><code>' . esc_html(basename($cache->translation_snapshot_path($source_id, $language))) . '</code></p>');
		echo ('<p class="description">' . esc_html__('Choose a translated featured image here. Images inserted inside the Gutenberg content can also be replaced per language.', 'localizepilot') . '</p>');
		echo ('</div>');
	}

	public function create_translation_action(): void
	{
		$source_id = absint(wp_unslash($_GET['source_id'] ?? 0));
		$language  = sanitize_key(wp_unslash($_GET['language'] ?? ''));

		if (! $source_id || ! current_user_can('edit_post', $source_id)) {
			wp_die(esc_html__('You are not allowed to create this translation.', 'localizepilot'));
		}
		check_admin_referer('next_translate_create_' . $source_id . '_' . $language);

		$existing = $this->get_translation($source_id, $language);
		if ($existing) {
			wp_safe_redirect(get_edit_post_link($existing->ID, ''));
			exit;
		}

		try {
			$translation_id = $this->generate_translation($source_id, $language, 0);
			wp_safe_redirect(add_query_arg('next_translate_created', '1', get_edit_post_link($translation_id, '')));
			exit;
		} catch (\Throwable $exception) {
			wp_die(esc_html($exception->getMessage()));
		}
	}

	public function refresh_translation_action(): void
	{
		$translation_id = absint(wp_unslash($_GET['translation_id'] ?? 0));
		if (! $translation_id || ! current_user_can('edit_post', $translation_id)) {
			wp_die(esc_html__('You are not allowed to refresh this translation.', 'localizepilot'));
		}
		check_admin_referer('next_translate_refresh_' . $translation_id);

		$source_id = absint(get_post_meta($translation_id, self::META_SOURCE_ID, true));
		$language  = sanitize_key((string) get_post_meta($translation_id, self::META_LANGUAGE, true));

		try {
			$this->generate_translation($source_id, $language, $translation_id);
			wp_safe_redirect(add_query_arg('next_translate_refreshed', '1', get_edit_post_link($translation_id, '')));
			exit;
		} catch (\Throwable $exception) {
			wp_die(esc_html($exception->getMessage()));
		}
	}

	private function generate_translation(int $source_id, string $language, int $translation_id = 0): int
	{
		$this->settings = Plugin::instance()->get_settings();
		$source         = get_post($source_id);

		if (! $source instanceof \WP_Post || ! in_array($source->post_type, array('post', 'page', 'product'), true)) {
			throw new \RuntimeException(esc_html__('The source post or page was not found.', 'localizepilot'));
		}
		if (! Language_Catalog::exists($language) || $language === (string) $this->settings['source_language']) {
			throw new \RuntimeException(esc_html__('The selected target language is invalid.', 'localizepilot'));
		}
		if (! in_array($language, (array) $this->settings['enabled_languages'], true)) {
			throw new \RuntimeException(esc_html__('Enable this language in LocalizePilot settings first.', 'localizepilot'));
		}

		$limit_enabled = ! empty($this->settings['daily_limit_enabled']);
		$daily_limit   = max(1, absint($this->settings['daily_limit'] ?? 10));
		if ($limit_enabled && ! $this->limiter->can_translate($daily_limit)) {
			throw new \RuntimeException(esc_html__('The LocalizePilot daily automatic translation limit has been reached.', 'localizepilot'));
		}

		$client     = Client_Factory::make($this->settings);
		$translator = new HTML_Translator($client, $this->router, $this->settings);
		$plain      = array($source->post_title);
		if ('' !== trim($source->post_excerpt)) {
			$plain[] = $source->post_excerpt;
		}
		$translated_plain = $client->translate_batch($plain, $language);
		$translated_title = (string) ($translated_plain[0] ?? $source->post_title);
		$translated_excerpt = '';
		if ('' !== trim($source->post_excerpt)) {
			$translated_excerpt = (string) ($translated_plain[1] ?? $source->post_excerpt);
		}
		$translated_content = '' !== trim($source->post_content)
			? $translator->translate_editor_content($source->post_content, $language)
			: '';

		$post_data = array(
			'ID'           => $translation_id,
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_parent'  => $source_id,
			'post_title'   => $translated_title,
			'post_content' => $translated_content,
			'post_excerpt' => $translated_excerpt,
			'post_author'  => get_current_user_id() ?: (int) $source->post_author,
		);

		$this->programmatic_update = true;
		$saved_id = wp_insert_post(wp_slash($post_data), true);
		$this->programmatic_update = false;

		if (is_wp_error($saved_id)) {
			throw new \RuntimeException(esc_html(sanitize_text_field($saved_id->get_error_message())));
		}

		update_post_meta($saved_id, self::META_SOURCE_ID, $source_id);
		update_post_meta($saved_id, self::META_LANGUAGE, $language);
		update_post_meta($saved_id, self::META_STATUS, 'automatic');
		update_post_meta($saved_id, self::META_SOURCE_HASH, $this->source_hash($source));
		update_post_meta($saved_id, self::META_PROVIDER, $client->provider());

		if (! $translation_id) {
			$thumbnail_id = get_post_thumbnail_id($source_id);
			if ($thumbnail_id) {
				set_post_thumbnail($saved_id, $thumbnail_id);
			}
		}

		$this->runtime_cache[$this->runtime_key($source_id, $language)] = get_post($saved_id);
		$this->sync_translation_files($saved_id);
		if ($limit_enabled) {
			$this->limiter->increment($daily_limit);
		}

		return (int) $saved_id;
	}

	public function after_insert_post(int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before): void
	{
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		if (self::POST_TYPE === $post->post_type) {
			if ($this->programmatic_update) {
				return;
			}

			$source_id = absint(get_post_meta($post_id, self::META_SOURCE_ID, true));
			$language  = sanitize_key((string) get_post_meta($post_id, self::META_LANGUAGE, true));
			if (! $source_id || '' === $language) {
				return;
			}

			if ((int) $post->post_parent !== $source_id) {
				$this->programmatic_update = true;
				wp_update_post(
					wp_slash(
						array(
							'ID'          => $post_id,
							'post_parent' => $source_id,
						)
					)
				);
				$this->programmatic_update = false;
			}

			$statuses = self::statuses();
			$requested_status = '';
			if (isset($_POST['next_translate_translation_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['next_translate_translation_nonce'])), 'next_translate_translation_meta')) {
				$requested_status = sanitize_key(wp_unslash($_POST['next_translate_status'] ?? ''));
			}

			$content_changed = $update && $post_before instanceof \WP_Post && (
				$post->post_title !== $post_before->post_title ||
				$post->post_content !== $post_before->post_content ||
				$post->post_excerpt !== $post_before->post_excerpt
			);

			if ($content_changed) {
				$new_status = in_array($requested_status, array('reviewed', 'needs_update'), true) ? $requested_status : 'edited';
				update_post_meta($post_id, self::META_STATUS, $new_status);
				$source = get_post($source_id);
				if ($source instanceof \WP_Post) {
					update_post_meta($post_id, self::META_SOURCE_HASH, $this->source_hash($source));
				}
			} elseif (isset($statuses[$requested_status])) {
				update_post_meta($post_id, self::META_STATUS, $requested_status);
				if ('reviewed' === $requested_status) {
					$source = get_post($source_id);
					if ($source instanceof \WP_Post) {
						update_post_meta($post_id, self::META_SOURCE_HASH, $this->source_hash($source));
					}
				}
			}

			$this->runtime_cache[$this->runtime_key($source_id, $language)] = $post;
			$this->sync_translation_files($post_id);
			return;
		}

		if (in_array($post->post_type, array('post', 'page'), true) && 'auto-draft' !== $post->post_status) {
			$this->mark_translations_for_source_change($post);
		}
	}

	private function mark_translations_for_source_change(\WP_Post $source): void
	{
		$current_hash = $this->source_hash($source);
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array('publish', 'draft', 'pending', 'private'),
				'post_parent'    => $source->ID,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$cache = new File_Cache(Plugin::instance()->get_settings());
		foreach ($query->posts as $translation_id) {
			$stored_hash = (string) get_post_meta($translation_id, self::META_SOURCE_HASH, true);
			if ('' !== $stored_hash && hash_equals($stored_hash, $current_hash)) {
				continue;
			}
			$language = sanitize_key((string) get_post_meta($translation_id, self::META_LANGUAGE, true));
			update_post_meta($translation_id, self::META_STATUS, 'needs_update');
			$cache->delete_post_cache($source->ID, $language);
			unset($this->runtime_cache[$this->runtime_key($source->ID, $language)]);
		}
	}

	private function sync_translation_files(int $translation_id): void
	{
		$translation = get_post($translation_id);
		if (! $translation instanceof \WP_Post) {
			return;
		}
		$source_id = absint(get_post_meta($translation_id, self::META_SOURCE_ID, true));
		$language  = sanitize_key((string) get_post_meta($translation_id, self::META_LANGUAGE, true));
		if (! $source_id || '' === $language) {
			return;
		}

		$cache  = new File_Cache(Plugin::instance()->get_settings());
		$source = get_post($source_id);

		// Do not expose drafts, private posts, or shortcode output in a public cache directory.
		if (! $source instanceof \WP_Post || 'publish' !== $source->post_status) {
			$cache->delete_translation_snapshot($source_id, $language);
			$cache->delete_post_cache($source_id, $language);
			return;
		}

		$cache->write_translation_snapshot(
			$source_id,
			$language,
			$translation->post_content,
			array(
				'translation_id' => $translation_id,
				'status'         => (string) get_post_meta($translation_id, self::META_STATUS, true),
				'updated_at'     => time(),
			)
		);
		$cache->delete_post_cache($source_id, $language);
		do_action('localizepilot_schedule_cache_warm', $source_id, $language);
	}

	public function before_delete_post(int $post_id): void
	{
		$post = get_post($post_id);
		if (! $post instanceof \WP_Post) {
			return;
		}

		$cache = new File_Cache(Plugin::instance()->get_settings());
		if (self::POST_TYPE === $post->post_type) {
			$source_id = absint(get_post_meta($post_id, self::META_SOURCE_ID, true));
			$language  = sanitize_key((string) get_post_meta($post_id, self::META_LANGUAGE, true));
			$cache->delete_post_cache($source_id, $language);
			$cache->delete_translation_snapshot($source_id, $language);
			unset($this->runtime_cache[$this->runtime_key($source_id, $language)]);
		} elseif (in_array($post->post_type, array('post', 'page'), true)) {
			$cache->delete_post_cache($post_id);
			$translations = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'post_parent'    => $post_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ($translations as $translation_id) {
				wp_delete_post($translation_id, true);
			}
		}
	}

	public function get_translation(int $source_id, string $language): ?\WP_Post
	{
		$language = sanitize_key($language);
		$key      = $this->runtime_key($source_id, $language);
		if (array_key_exists($key, $this->runtime_cache)) {
			return $this->runtime_cache[$key] instanceof \WP_Post ? $this->runtime_cache[$key] : null;
		}

		$post_status = is_admin() ? array('publish', 'draft', 'pending', 'private') : 'publish';
		$query       = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $post_status,
				'post_parent'    => $source_id,
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$translation = null;
		foreach ($query->posts as $candidate) {
			if ($candidate instanceof \WP_Post && $language === sanitize_key((string) get_post_meta($candidate->ID, self::META_LANGUAGE, true))) {
				$translation = $candidate;
				break;
			}
		}

		$this->runtime_cache[$key] = $translation;
		return $translation;
	}

	public function current_translation(): ?\WP_Post
	{
		if (! $this->router->is_translated_request() || ! is_singular(array('post', 'page', 'product'))) {
			return null;
		}
		$source_id = get_queried_object_id();
		return $source_id ? $this->get_translation($source_id, $this->router->current_language()) : null;
	}

	public function filter_content(string $content): string
	{
		if (is_admin() || ! $this->router->is_translated_request() || ! is_singular(array('post', 'page', 'product')) || ! in_the_loop() || ! is_main_query()) {
			return $content;
		}
		$source_id = get_the_ID();
		if ($source_id !== get_queried_object_id()) {
			return $content;
		}
		$translation = $this->get_translation($source_id, $this->router->current_language());
		return $translation ? $translation->post_content : $content;
	}

	public function filter_title(string $title, int $post_id): string
	{
		if (is_admin() || ! $this->router->is_translated_request() || ! is_singular(array('post', 'page', 'product')) || $post_id !== get_queried_object_id()) {
			return $title;
		}
		$translation = $this->get_translation($post_id, $this->router->current_language());
		return $translation ? $translation->post_title : $title;
	}

	public function filter_excerpt(string $excerpt, $post): string
	{
		$post = get_post($post);
		if (is_admin() || ! $post instanceof \WP_Post || ! $this->router->is_translated_request() || ! is_singular(array('post', 'page', 'product')) || $post->ID !== get_queried_object_id()) {
			return $excerpt;
		}
		$translation = $this->get_translation($post->ID, $this->router->current_language());
		return $translation && '' !== trim($translation->post_excerpt) ? $translation->post_excerpt : $excerpt;
	}

	public function filter_document_title(array $parts): array
	{
		$translation = $this->current_translation();
		if ($translation) {
			$parts['title'] = $translation->post_title;
		}
		return $parts;
	}

	public function filter_post_metadata($value, int $object_id, string $meta_key, bool $single, string $meta_type)
	{
		if ('post' !== $meta_type || '_thumbnail_id' !== $meta_key || is_admin() || ! $this->router->is_translated_request() || $object_id !== get_queried_object_id()) {
			return $value;
		}
		$translation = $this->get_translation($object_id, $this->router->current_language());
		if (! $translation) {
			return $value;
		}
		$thumbnail_id = get_post_thumbnail_id($translation->ID);
		if (! $thumbnail_id) {
			return $value;
		}
		return $single ? $thumbnail_id : array($thumbnail_id);
	}

	public function protected_strings_for_current_request(): array
	{
		$translation = $this->current_translation();
		if (! $translation) {
			return array();
		}

		$values = array();
		foreach (array($translation->post_title, $translation->post_excerpt) as $value) {
			$value = trim((string) $value);
			if ('' !== $value) {
				$values[$value] = true;
			}
		}

		$rendered = $translation->post_content;
		if (class_exists('\DOMDocument') && '' !== trim($rendered)) {
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$previous = libxml_use_internal_errors(true);
			$loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div id="nt-protected-root">' . $rendered . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			if ($loaded) {
				$xpath = new \DOMXPath($dom);
				$nodes = $xpath->query('//text()');
				if ($nodes) {
					foreach ($nodes as $node) {
						$value = trim((string) $node->nodeValue);
						if ('' !== $value && preg_match('/[\p{L}\p{N}]/u', $value)) {
							$values[$value] = true;
						}
					}
				}

				$elements = $xpath->query('//*[@alt or @title or @placeholder or @aria-label]');
				if ($elements) {
					foreach ($elements as $element) {
						if (! $element instanceof \DOMElement) {
							continue;
						}
						foreach (array('alt', 'title', 'placeholder', 'aria-label') as $attribute) {
							$value = trim($element->getAttribute($attribute));
							if ('' !== $value && preg_match('/[\p{L}\p{N}]/u', $value)) {
								$values[$value] = true;
							}
						}
					}
				}
			}
		}

		return array_keys($values);
	}

	public function translation_columns(array $columns): array
	{
		$new = array();
		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ('title' === $key) {
				$new['nt_source']   = __('Source', 'localizepilot');
				$new['nt_language'] = __('Language', 'localizepilot');
				$new['nt_status']   = __('Status', 'localizepilot');
			}
		}
		return $new;
	}

	public function translation_column_content(string $column, int $post_id): void
	{
		if ('nt_source' === $column) {
			$source_id = absint(get_post_meta($post_id, self::META_SOURCE_ID, true));
			if ($source_id) {
				echo '<a href="' . esc_url(get_edit_post_link($source_id, '')) . '">' . esc_html(get_the_title($source_id)) . '</a>';
			} else {
				echo esc_html('—');
			}
		} elseif ('nt_language' === $column) {
			$language = (string) get_post_meta($post_id, self::META_LANGUAGE, true);
			echo ('<span class="nt-code-badge">' . esc_html(strtoupper($language)) . '</span>');
		} elseif ('nt_status' === $column) {
			$status   = (string) get_post_meta($post_id, self::META_STATUS, true);
			$statuses = self::statuses();
			$status   = isset($statuses[$status]) ? $status : 'automatic';
			echo ('<span class="nt-translation-status nt-status-' . esc_attr($status) . '">' . esc_html($statuses[$status]) . '</span>');
		}
	}

	public function translation_row_actions(array $actions, \WP_Post $post): array
	{
		if (self::POST_TYPE !== $post->post_type) {
			return $actions;
		}
		$source_id = absint(get_post_meta($post->ID, self::META_SOURCE_ID, true));
		$language  = sanitize_key((string) get_post_meta($post->ID, self::META_LANGUAGE, true));
		if ($source_id && '' !== $language) {
			$actions['view_translation'] = '<a href="' . esc_url($this->router->localize_url(get_permalink($source_id), $language)) . '" target="_blank">' . esc_html__('View translated page', 'localizepilot') . '</a>';
		}
		return $actions;
	}

	public function render_admin_filters(string $post_type): void
	{
		if (self::POST_TYPE !== $post_type) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$current_status = sanitize_key(wp_unslash($_GET['next_translate_status_filter'] ?? ''));
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$current_language = sanitize_key(wp_unslash($_GET['next_translate_language_filter'] ?? ''));
		$languages        = Language_Catalog::all();

		echo ('<select name="next_translate_status_filter"><option value="">' . esc_html__('All translation statuses', 'localizepilot') . '</option>');
		foreach (self::statuses() as $value => $label) {
			echo ('<option value="' . esc_attr($value) . '" ' . selected($current_status, $value, false) . '>' . esc_html($label) . '</option>');
		}
		echo ('</select>');

		echo ('<select name="next_translate_language_filter"><option value="">' . esc_html__('All languages', 'localizepilot') . '</option>');
		foreach ((array) Plugin::instance()->get_settings()['enabled_languages'] as $language) {
			$language = sanitize_key($language);
			if (! isset($languages[$language])) {
				continue;
			}
			echo ('<option value="' . esc_attr($language) . '" ' . selected($current_language, $language, false) . '>' . esc_html($languages[$language]['native'] . ' (' . strtoupper($language) . ')') . '</option>');
		}
		echo ('</select>');
	}

	public function filter_admin_translations(\WP_Query $query): void
	{
		if (! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get('post_type')) {
			return;
		}

		$meta_query = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$status = sanitize_key(wp_unslash($_GET['next_translate_status_filter'] ?? ''));
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$language = sanitize_key(wp_unslash($_GET['next_translate_language_filter'] ?? ''));
		if (isset(self::statuses()[$status])) {
			$meta_query[] = array(
				'key'   => self::META_STATUS,
				'value' => $status,
			);
		}
		if (Language_Catalog::exists($language)) {
			$meta_query[] = array(
				'key'   => self::META_LANGUAGE,
				'value' => $language,
			);
		}
		if (! empty($meta_query)) {
			$query->set('meta_query', $meta_query);
		}
	}

	public function admin_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag from a verified admin-post redirect.
		if (isset($_GET['next_translate_created'])) {
			echo ('<div class="notice notice-success is-dismissible"><p>' . esc_html__('Automatic translation created. You can now correct it with the Gutenberg editor.', 'localizepilot') . '</p></div>');
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag from a verified admin-post redirect.
		if (isset($_GET['next_translate_refreshed'])) {
			echo ('<div class="notice notice-success is-dismissible"><p>' . esc_html__('The translation was refreshed from the selected API.', 'localizepilot') . '</p></div>');
		}
	}

	private function source_hash(\WP_Post $post): string
	{
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'title'   => $post->post_title,
					'content' => $post->post_content,
					'excerpt' => $post->post_excerpt,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}

	private function runtime_key(int $source_id, string $language): string
	{
		return $source_id . ':' . sanitize_key($language);
	}
}
