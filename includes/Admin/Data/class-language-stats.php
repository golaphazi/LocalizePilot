<?php
/**
 * Read model for the Languages screen and every language coverage figure.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Post_Types;
use LocalizePilot\Router;
use LocalizePilot\Translation_Manager;

defined( 'ABSPATH' ) || exit;

final class Language_Stats {
	/**
	 * How many published posts and pages could be translated.
	 */
	public function translatable_total(): int {
		$total = 0;

		foreach ( Post_Types::translatable() as $type ) {
			$counts = wp_count_posts( $type );
			$total += (int) ( $counts->publish ?? 0 );
		}

		return $total;
	}

	/**
	 * Translations per language, keyed by language code.
	 *
	 * One grouped query behind a short transient, rather than a query per
	 * language. Translation_Repository::flush() clears it on save.
	 *
	 * @return array<string,int>
	 */
	public function counts_by_language(): array {
		$cached = get_transient( 'localizepilot_language_coverage' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta.meta_value AS language, COUNT(*) AS total
				 FROM {$wpdb->postmeta} AS meta
				 INNER JOIN {$wpdb->posts} AS posts ON posts.ID = meta.post_id
				 WHERE meta.meta_key = %s
				   AND posts.post_type = %s
				   AND posts.post_status = 'publish'
				 GROUP BY meta.meta_value",
				Translation_Manager::META_LANGUAGE,
				Translation_Manager::POST_TYPE
			),
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$code = sanitize_key( (string) ( $row['language'] ?? '' ) );

			if ( '' !== $code ) {
				$counts[ $code ] = (int) $row['total'];
			}
		}

		set_transient( 'localizepilot_language_coverage', $counts, 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * The languages currently enabled, with coverage and example URL.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function enabled(): array {
		$settings = Plugin::instance()->get_settings();
		$source   = strtolower( (string) ( $settings['source_language'] ?? 'en' ) );
		$codes    = array_values( array_unique( array_merge( array( $source ), (array) ( $settings['enabled_languages'] ?? array() ) ) ) );
		$counts   = $this->counts_by_language();
		$total    = max( 1, $this->translatable_total() );
		$router   = new Router();
		$rows     = array();

		foreach ( $codes as $code ) {
			$code = strtolower( (string) $code );

			if ( ! Language_Catalog::exists( $code ) ) {
				continue;
			}

			$is_source  = $code === $source;
			$translated = (int) ( $counts[ $code ] ?? 0 );

			$rows[] = array(
				'code'       => $code,
				'name'       => Language_Catalog::label( $code, 'english' ),
				'native'     => Language_Catalog::label( $code, 'native' ),
				'rtl'        => Language_Catalog::is_rtl( $code ),
				'is_source'  => $is_source,
				'translated' => $translated,
				'coverage'   => $is_source ? 100 : (int) min( 100, round( 100 * $translated / $total ) ),
				'url'        => $router->language_url( $code ),
			);
		}

		return $rows;
	}

	/**
	 * Every language the catalog knows, flagged with whether it is enabled.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function available(): array {
		$settings = Plugin::instance()->get_settings();
		$source   = strtolower( (string) ( $settings['source_language'] ?? 'en' ) );
		$enabled  = array_map( 'strtolower', (array) ( $settings['enabled_languages'] ?? array() ) );
		$rows     = array();

		foreach ( Language_Catalog::all() as $code => $language ) {
			if ( $code === $source ) {
				continue;
			}

			$rows[] = array(
				'code'    => $code,
				'name'    => $language['name'],
				'native'  => $language['native'],
				'enabled' => in_array( $code, $enabled, true ),
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $rows;
	}

	/**
	 * Average coverage across the non-source languages.
	 */
	public function average_coverage(): int {
		$rows = array_values(
			array_filter(
				$this->enabled(),
				static function ( array $row ): bool {
					return empty( $row['is_source'] );
				}
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		return (int) round( array_sum( wp_list_pluck( $rows, 'coverage' ) ) / count( $rows ) );
	}

	/**
	 * How many enabled languages have at least one translation.
	 */
	public function translated_count(): int {
		return count(
			array_filter(
				$this->enabled(),
				static function ( array $row ): bool {
					return empty( $row['is_source'] ) && $row['translated'] > 0;
				}
			)
		);
	}

	/**
	 * Example URLs for the language URL structure card.
	 *
	 * @param string $path Sample path appended to each language root.
	 * @return array<int,array<string,string>>
	 */
	public function example_urls( string $path = 'blog/example-post/' ): array {
		$router = new Router();
		$rows   = array();

		foreach ( $this->enabled() as $language ) {
			$rows[] = array(
				'code'  => $language['code'],
				'name'  => $language['name'],
				'label' => $language['is_source']
					? sprintf( '%s · %s', $language['name'], __( 'source', 'localizepilot' ) )
					: $language['name'],
				'url'   => trailingslashit( $router->language_url( $language['code'] ) ) . $path,
			);
		}

		return $rows;
	}
}
