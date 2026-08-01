<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Fallback_Client implements Translation_Client_Interface {
	private Translation_Client_Interface $primary;
	private Translation_Client_Interface $fallback;
	private string $last_provider;

	public function __construct( Translation_Client_Interface $primary, Translation_Client_Interface $fallback ) {
		$this->primary       = $primary;
		$this->fallback      = $fallback;
		$this->last_provider = $primary->provider();
	}

	public function translate_batch( array $texts, string $target_language ): array {
		try {
			$result = $this->primary->translate_batch( $texts, $target_language );
			$this->last_provider = $this->primary->provider();
			return $result;
		} catch ( \Throwable $primary_error ) {
			try {
				$result = $this->fallback->translate_batch( $texts, $target_language );
				$this->last_provider = $this->fallback->provider();
				return $result;
			} catch ( \Throwable $fallback_error ) {
				throw new \RuntimeException(
					sprintf(
						__( 'Primary provider failed: %1$s Fallback provider failed: %2$s', 'localizepilot' ),
						$primary_error->getMessage(),
						$fallback_error->getMessage()
					)
				);
			}
		}
	}

	public function test( string $target_language = 'es' ): string {
		return $this->primary->test( $target_language );
	}

	public function provider(): string {
		return $this->last_provider;
	}
}
