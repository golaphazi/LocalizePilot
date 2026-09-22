<?php
/**
 * Another multilingual plugin's translations, read straight from the database.
 *
 * Straight from the database because people migrate after switching the old
 * plugin off, and by then none of its code is loaded — but its data is still
 * there. An adapter only reads. It never writes to, deletes from, or depends
 * on the plugin it reads.
 *
 * Every adapter reports the same shape: groups of posts that are translations
 * of each other, keyed by the other plugin's language code, walked in order of
 * a numeric key so a migration can stop and carry on where it left off.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Migration;

use LocalizePilot\Language_Catalog;

defined( 'ABSPATH' ) || exit;

abstract class Source {
	/** Short identifier, e.g. "wpml". */
	abstract public function id(): string;

	/** The plugin's name, as people know it. */
	abstract public function label(): string;

	/** Whether this site has data from that plugin. */
	abstract public function detected(): bool;

	/** How many translation groups there are. */
	abstract public function count(): int;

	/**
	 * Translation groups after $after, in key order.
	 *
	 * A group may hold a single post — one nobody translated. The migrator
	 * passes over those; an adapter may return them, and must not skip them
	 * in a way that makes a batch come back empty while groups remain, since
	 * an empty batch means "nothing left".
	 *
	 * @return array<int,array{key:int,members:array<string,int>}> Members are
	 *         the other plugin's language code => post ID.
	 */
	abstract public function groups( int $after, int $limit ): array;

	/**
	 * Languages the other plugin used, with how many posts each has.
	 *
	 * @return array<string,array{posts:int,locale:string}> Keyed by its code.
	 */
	abstract public function languages(): array;

	/**
	 * Posts per post type among translated content.
	 *
	 * @return array<string,int>
	 */
	abstract public function post_types(): array;

	/**
	 * LocalizePilot's code for another plugin's language, or null when
	 * LocalizePilot does not support it.
	 *
	 * Tries the code as it is, then its base language — WPML's "pt-br" and
	 * "zh-hans" become "pt" and "zh" — then the same for the locale.
	 */
	public function map_language( string $code ): ?string {
		$locale     = (string) ( $this->languages()[ $code ]['locale'] ?? '' );
		$candidates = array();

		foreach ( array( $code, $locale ) as $value ) {
			$value = strtolower( str_replace( '_', '-', trim( $value ) ) );

			if ( '' === $value ) {
				continue;
			}

			$candidates[] = $value;
			$candidates[] = (string) strtok( $value, '-' );
		}

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && Language_Catalog::exists( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
