<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

/**
 * Reuse provider translations for unchanged strings on the same language page.
 *
 * Full-page HTML is never shared for logged-in requests. This smaller cache
 * stores only translated values, keyed by a one-way hash of their source text,
 * so a safe anonymous cache warm can reuse work done during an editor preview.
 */
final class Translation_Memory_Client implements Translation_Client_Interface {
	private Translation_Client_Interface $client;
	private File_Cache $cache;
	private string $cache_key;
	private string $fingerprint;
	private string $last_provider;
	private bool $provider_allowed;
	private bool $provider_used = false;

	/** @var array<string,array{provider:string,translations:array<string,string>}> */
	private array $memory = array();

	public function __construct(
		Translation_Client_Interface $client,
		File_Cache $cache,
		string $cache_key,
		string $fingerprint,
		bool $provider_allowed = true
	) {
		$this->client           = $client;
		$this->cache            = $cache;
		$this->cache_key        = $cache_key;
		$this->fingerprint      = $fingerprint;
		$this->last_provider    = $client->provider();
		$this->provider_allowed = $provider_allowed;
	}

	/**
	 * @param string[] $texts Source strings.
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		$texts           = array_values( array_map( 'strval', $texts ) );
		$target_language = sanitize_key( $target_language );

		if ( empty( $texts ) ) {
			return array();
		}

		if ( ! isset( $this->memory[ $target_language ] ) ) {
			$this->memory[ $target_language ] = $this->cache->get_translation_memory(
				$this->cache_key,
				$this->fingerprint,
				$target_language
			);
		}

		$memory       = $this->memory[ $target_language ];
		$translations = (array) ( $memory['translations'] ?? array() );
		$misses       = array();

		if ( '' !== (string) ( $memory['provider'] ?? '' ) ) {
			$this->last_provider = (string) $memory['provider'];
		}

		foreach ( $texts as $text ) {
			$hash = hash( 'sha256', $text );
			if ( ! array_key_exists( $hash, $translations ) ) {
				$misses[ $hash ] = $text;
			}
		}

		if ( ! empty( $misses ) ) {
			if ( ! $this->provider_allowed ) {
				throw new \RuntimeException( esc_html__( 'The LocalizePilot daily automatic translation limit has been reached.', 'localizepilot' ) );
			}

			$sources = array_values( $misses );
			$result  = $this->client->translate_batch( $sources, $target_language );
			$this->provider_used = true;

			foreach ( array_keys( $misses ) as $index => $hash ) {
				$translations[ $hash ] = (string) ( $result[ $index ] ?? $sources[ $index ] );
			}

			$this->last_provider = $this->client->provider();
			$this->cache->set_translation_memory(
				$this->cache_key,
				$this->fingerprint,
				$target_language,
				$translations,
				$this->last_provider
			);
		}

		$this->memory[ $target_language ] = array(
			'provider'     => $this->last_provider,
			'translations' => $translations,
		);

		return array_map(
			static function ( string $text ) use ( $translations ): string {
				$hash = hash( 'sha256', $text );
				return array_key_exists( $hash, $translations ) ? (string) $translations[ $hash ] : $text;
			},
			$texts
		);
	}

	public function test( string $target_language = 'es' ): string {
		return $this->client->test( $target_language );
	}

	public function provider(): string {
		return $this->last_provider;
	}

	public function used_provider(): bool {
		return $this->provider_used;
	}
}
