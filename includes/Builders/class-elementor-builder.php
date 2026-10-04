<?php
/**
 * Elementor.
 *
 * The page is a JSON tree in the _elementor_data post meta: sections and
 * containers holding widgets, each widget a bag of settings. Elementor reads
 * it with get_post_meta(), which is what lets a translation be served in its
 * place without Elementor knowing anything about languages.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Builders;

defined( 'ABSPATH' ) || exit;

final class Elementor_Builder extends Builder {
	public function id(): string {
		return 'elementor';
	}

	public function label(): string {
		return 'Elementor';
	}

	public function available(): bool {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}

	public function handles( \WP_Post $post ): bool {
		return 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true );
	}

	public function content_key(): string {
		return '_elementor_data';
	}

	/**
	 * @return array<int,string>
	 */
	public function companion_keys(): array {
		return array(
			'_elementor_edit_mode',
			'_elementor_template_type',
			'_elementor_version',
			'_elementor_page_settings',
		);
	}

	/**
	 * @param mixed $stored Meta value.
	 * @return array<int|string,mixed>
	 */
	public function decode( $stored ): array {
		if ( is_array( $stored ) ) {
			return $stored;
		}

		$decoded = json_decode( (string) $stored, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<int|string,mixed> $tree Tree.
	 * @return string
	 */
	public function encode( array $tree ) {
		// Plain JSON, exactly as it comes back out of the database — whoever
		// writes it adds the slashes update_post_meta will strip. Encoding it
		// slashed here would make decode( encode( $tree ) ) fail, and that is
		// a trap for every later caller.
		return (string) wp_json_encode( $tree );
	}

	/**
	 * Elementor keeps the HTML it rendered in _elementor_element_cache, and
	 * that HTML is in one language.
	 *
	 * @return array<int,string>
	 */
	public function cache_keys(): array {
		return array( '_elementor_element_cache' );
	}

	public function edit_url( int $post_id ): string {
		return admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
	}

	/**
	 * Elementor edits any post type that supports 'elementor' — which is what
	 * its own setting does for the types listed there. Adding the support
	 * directly leaves that setting, and the choices someone made in it, alone.
	 */
	public function enable_editing( string $post_type ): void {
		add_post_type_support( $post_type, 'elementor' );
	}

	/**
	 * The settings Elementor's own widgets keep words in.
	 *
	 * Drawn from the core widget set. Anything an add-on widget adds goes on
	 * the localizepilot_builder_text_keys filter — a setting that is not here
	 * is left exactly as it is, which is the safe direction to be wrong in.
	 *
	 * The shortcode widget's "shortcode" setting is deliberately absent:
	 * translating [localizepilot_switcher] would leave a shortcode that no
	 * longer exists.
	 *
	 * @return array<string,bool> Setting key => usually HTML.
	 */
	protected function text_keys(): array {
		return array(
			// Headings, buttons, captions, labels.
			'title'                  => false,
			'text'                   => false,
			'caption'                => false,
			'alt'                    => false,
			'button_text'            => false,
			'title_text'             => false,
			'inner_text'             => false,
			'before_text'            => false,
			'highlighted_text'       => false,
			'after_text'             => false,
			'heading'                => false,
			'sub_heading'            => false,
			'period'                 => false,
			'ribbon_title'           => false,
			'label'                  => false,
			'placeholder'            => false,
			'author'                 => false,
			'testimonial_name'       => false,
			'testimonial_job'        => false,
			'alert_title'            => false,
			'field_label'            => false,
			'item_text'              => false,
			// Repeater items: tabs, accordions, lists, price tables.
			'tab_title'              => false,
			'item_description'       => false,
			// Rich text.
			'editor'                 => true,
			'description'            => true,
			'description_text'       => true,
			'tab_content'            => true,
			'testimonial_content'    => true,
			'alert_description'      => true,
			'blockquote_content'     => true,
			'footer_additional_info' => true,
			'html'                   => true,
			'content'                => true,
		);
	}
}
