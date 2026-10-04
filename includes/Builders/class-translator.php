<?php
/**
 * Translating a page builder's content.
 *
 * The text comes out of the tree, goes to the provider, and goes back exactly
 * where it was. Plain settings travel in batches, because a page has dozens of
 * them and one request each would be slow and expensive; settings holding HTML
 * go through the same translator the Gutenberg content uses, which keeps the
 * markup and translates only the words between it.
 *
 * Nothing is written until every string is back. A provider failing halfway
 * would otherwise leave a translation holding half a page.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Builders;

use LocalizePilot\HTML_Translator;
use LocalizePilot\Translation_Client_Interface;

defined( 'ABSPATH' ) || exit;

final class Translator {
	/** Strings per provider request, and the characters that caps it. */
	private const BATCH_STRINGS = 40;
	private const BATCH_CHARACTERS = 4000;

	private Translation_Client_Interface $client;

	private HTML_Translator $html;

	public function __construct( Translation_Client_Interface $client, HTML_Translator $html ) {
		$this->client = $client;
		$this->html   = $html;
	}

	/**
	 * The source's builder content, translated, ready to be stored.
	 *
	 * @return array{content:mixed,companions:array<string,mixed>,strings:int}|null
	 *         Null when the page has no builder content to translate.
	 * @throws \RuntimeException When the provider cannot translate.
	 */
	public function prepare( Builder $builder, \WP_Post $source, string $language ): ?array {
		$tree = $builder->decode( get_post_meta( $source->ID, $builder->content_key(), true ) );

		if ( empty( $tree ) ) {
			return null;
		}

		$strings      = $builder->strings( $tree );
		$translations = $this->translate( $strings, $language );
		$companions   = array();

		foreach ( $builder->companion_keys() as $key ) {
			$value = get_post_meta( $source->ID, $key, true );

			if ( '' !== $value && null !== $value ) {
				$companions[ $key ] = $value;
			}
		}

		return array(
			'content'    => $builder->encode( $builder->replace( $tree, $translations ) ),
			'companions' => $companions,
			'strings'    => count( $translations ),
		);
	}

	/**
	 * Put a prepared translation on the record.
	 *
	 * @param array{content:mixed,companions:array<string,mixed>,strings:int} $payload From prepare().
	 */
	public function store( Builder $builder, int $record_id, array $payload ): void {
		$content = $payload['content'];

		// update_post_meta strips one level of slashes; JSON is full of them.
		update_post_meta( $record_id, $builder->content_key(), is_string( $content ) ? wp_slash( $content ) : $content );

		foreach ( (array) $payload['companions'] as $key => $value ) {
			update_post_meta( $record_id, $key, is_string( $value ) ? wp_slash( $value ) : $value );
		}
	}

	/**
	 * @param array<string,array{text:string,html:bool}> $strings Path => string.
	 * @return array<string,string> Path => translated.
	 * @throws \RuntimeException When the provider cannot translate.
	 */
	private function translate( array $strings, string $language ): array {
		$plain = array();
		$rich  = array();

		// The same words in two places — a repeated button label — are one
		// translation, not two.
		foreach ( $strings as $path => $string ) {
			if ( $string['html'] ) {
				$rich[ $string['text'] ][] = $path;
			} else {
				$plain[ $string['text'] ][] = $path;
			}
		}

		$translations = array();

		foreach ( $this->batches( array_keys( $plain ) ) as $batch ) {
			$results = $this->client->translate_batch( $batch, $language );

			foreach ( $batch as $index => $original ) {
				$translated = (string) ( $results[ $index ] ?? $original );

				foreach ( $plain[ $original ] as $path ) {
					$translations[ $path ] = $translated;
				}
			}
		}

		foreach ( $rich as $original => $paths ) {
			$translated = $this->html->translate_fragment( (string) $original, $language );

			foreach ( $paths as $path ) {
				$translations[ $path ] = $translated;
			}
		}

		return $translations;
	}

	/**
	 * Strings grouped into requests, by count and by size.
	 *
	 * @param array<int,string> $values Strings.
	 * @return array<int,array<int,string>>
	 */
	private function batches( array $values ): array {
		$batches    = array();
		$batch      = array();
		$characters = 0;

		foreach ( $values as $value ) {
			$length = mb_strlen( (string) $value );

			if ( ! empty( $batch ) && ( count( $batch ) >= self::BATCH_STRINGS || $characters + $length > self::BATCH_CHARACTERS ) ) {
				$batches[]  = $batch;
				$batch      = array();
				$characters = 0;
			}

			$batch[]     = (string) $value;
			$characters += $length;
		}

		if ( ! empty( $batch ) ) {
			$batches[] = $batch;
		}

		return $batches;
	}
}
