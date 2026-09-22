<?php
/**
 * Which post types LocalizePilot translates.
 *
 * The one answer to that question. It used to be written out by hand wherever
 * it was needed — fourteen places — and when products were added only five of
 * them were told. The rest kept acting as if products did not exist: deleting
 * a product left its translations behind, editing one never marked them out of
 * date, and translated product pages were cached under a key nothing purged.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Post_Types {
	/** What a new site translates, and what an existing site had implicitly. */
	public const DEFAULTS = array( 'post', 'page', 'product' );

	/**
	 * Post types an administrator could choose to translate.
	 *
	 * Public, with an admin UI, and not media or LocalizePilot's own records.
	 *
	 * @return array<string,string> Slug => label.
	 */
	public static function available(): array {
		$types = array();

		foreach ( get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' ) as $slug => $object ) {
			if ( in_array( $slug, array( 'attachment', Translation_Manager::POST_TYPE ), true ) ) {
				continue;
			}

			$types[ (string) $slug ] = (string) $object->labels->name;
		}

		return $types;
	}

	/**
	 * Post types translated on this site right now.
	 *
	 * The stored choice, narrowed to types that are actually registered — a
	 * site with products selected and WooCommerce switched off translates no
	 * products, but has not forgotten it wanted to.
	 *
	 * @return array<int,string>
	 */
	public static function translatable(): array {
		$settings = Plugin::instance()->get_settings();
		$chosen   = array_map( 'sanitize_key', (array) ( $settings['translatable_post_types'] ?? self::DEFAULTS ) );
		$types    = array_values( array_filter( $chosen, 'post_type_exists' ) );

		/**
		 * Filter the post types LocalizePilot translates.
		 *
		 * Part of add-on API 3.
		 *
		 * @param array<int,string> $types Post type slugs.
		 */
		$types = (array) apply_filters( 'localizepilot_translatable_post_types', $types );

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $types ),
					static function ( string $type ): bool {
						return '' !== $type && Translation_Manager::POST_TYPE !== $type && 'attachment' !== $type;
					}
				)
			)
		);
	}

	public static function is_translatable( string $post_type ): bool {
		return in_array( $post_type, self::translatable(), true );
	}

	/**
	 * is_singular() for translatable types only.
	 *
	 * Not is_singular( self::translatable() ): handed an empty array, that
	 * answers true for every singular request, so switching every type off
	 * would have switched them all on.
	 */
	public static function is_singular(): bool {
		$types = self::translatable();

		return ! empty( $types ) && is_singular( $types );
	}

	/**
	 * The setting's value after a save.
	 *
	 * What was ticked, among the types that exist now, plus whatever was
	 * stored for types that do not exist at the moment. Saving settings while
	 * WooCommerce is deactivated must not quietly turn product translation off
	 * for when it comes back.
	 *
	 * @param array<int,string> $submitted Ticked slugs.
	 * @param array<int,string> $stored    Previously stored slugs.
	 * @return array<int,string>
	 */
	public static function sanitize( array $submitted, array $stored ): array {
		$available = array_keys( self::available() );
		$submitted = array_map( 'sanitize_key', $submitted );
		$stored    = array_map( 'sanitize_key', $stored );

		$kept   = array_intersect( $submitted, $available );
		$absent = array_diff( $stored, $available );

		return array_values( array_unique( array_merge( $kept, $absent ) ) );
	}
}
