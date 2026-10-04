<?php
/**
 * Bricks.
 *
 * The page is an array of elements in the _bricks_page_content_2 post meta,
 * each element a name, a parent, its children and a bag of settings — the same
 * shape of problem Elementor poses, kept as an array rather than JSON.
 *
 * Written against Bricks' documented element format. Bricks is a commercial
 * theme and was not installed while this was built, so the settings listed
 * below are the documented ones for its own elements; a site with Bricks
 * should be checked before this is relied on, and anything missing can be
 * added on the localizepilot_builder_text_keys filter without touching this
 * file. Headers and footers are Bricks templates, which are separate posts and
 * are not translated yet.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Builders;

defined( 'ABSPATH' ) || exit;

final class Bricks_Builder extends Builder {
	public function id(): string {
		return 'bricks';
	}

	public function label(): string {
		return 'Bricks';
	}

	public function available(): bool {
		return defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Theme' );
	}

	public function handles( \WP_Post $post ): bool {
		if ( 'bricks' === get_post_meta( $post->ID, '_bricks_editor_mode', true ) ) {
			return true;
		}

		// Older pages were saved before the editor-mode flag existed; having
		// content is what makes a page a Bricks page.
		return ! empty( get_post_meta( $post->ID, $this->content_key(), true ) );
	}

	public function content_key(): string {
		return '_bricks_page_content_2';
	}

	/**
	 * @return array<int,string>
	 */
	public function companion_keys(): array {
		return array(
			'_bricks_editor_mode',
			'_bricks_page_settings',
		);
	}

	public function edit_url( int $post_id ): string {
		return add_query_arg( 'bricks', 'run', (string) get_permalink( $post_id ) );
	}

	/**
	 * Bricks edits the post types listed in its own settings. This adds one to
	 * the list as it is read, rather than writing to the stored setting, so
	 * the choices someone made there are never rewritten.
	 */
	public function enable_editing( string $post_type ): void {
		add_filter(
			'option_bricks_global_settings',
			static function ( $settings ) use ( $post_type ) {
				if ( ! is_array( $settings ) ) {
					return $settings;
				}

				$types = isset( $settings['postTypes'] ) && is_array( $settings['postTypes'] ) ? $settings['postTypes'] : array();

				if ( ! in_array( $post_type, $types, true ) ) {
					$types[] = $post_type;
				}

				$settings['postTypes'] = $types;

				return $settings;
			}
		);
	}

	/**
	 * The settings Bricks' own elements keep words in.
	 *
	 * @return array<string,bool> Setting key => usually HTML.
	 */
	protected function text_keys(): array {
		return array(
			'title'       => false,
			'alt'         => false,
			'caption'     => false,
			'label'       => false,
			'subtitle'    => false,
			'placeholder' => false,
			'buttonText'  => false,
			'linkText'    => false,
			'text'        => true,
			'content'     => true,
			'description' => true,
		);
	}
}
