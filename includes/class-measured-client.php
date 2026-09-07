<?php
/**
 * Measure calls at the common provider boundary.
 *
 * Keeping this as a decorator means TranslateX, Google Translate, the shared
 * AI clients, and future providers all publish the same event. The decorator
 * sits inside Translation_Memory_Client, so a translation-memory hit does not
 * masquerade as a provider request.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Measured_Client implements Translation_Client_Interface {
	private Translation_Client_Interface $client;

	public function __construct( Translation_Client_Interface $client ) {
		$this->client = $client;
	}

	/**
	 * @param string[] $texts Source strings.
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array {
		if ( empty( $texts ) ) {
			return $this->client->translate_batch( $texts, $target_language );
		}

		return $this->measure(
			'translate',
			fn(): array => $this->client->translate_batch( $texts, $target_language )
		);
	}

	public function test( string $target_language = 'es' ): string {
		return $this->measure(
			'test',
			fn(): string => $this->client->test( $target_language )
		);
	}

	public function provider(): string {
		return $this->client->provider();
	}

	/**
	 * @template T
	 * @param callable():T $request Provider operation.
	 * @return T
	 */
	private function measure( string $operation, callable $request ) {
		$provider = sanitize_key( $this->client->provider() );
		$started  = microtime( true );
		$ok       = false;

		try {
			$result = $request();
			$ok     = true;

			return $result;
		} finally {
			/**
			 * Fires after every provider operation, including failures.
			 *
			 * LocalizePilot retains nothing. An add-on may aggregate the event.
			 *
			 * @param array<string,mixed> $measurement {
			 *     @type string $provider  Provider id.
			 *     @type float  $duration  Total operation time in milliseconds.
			 *     @type bool   $ok        Whether the operation completed.
			 *     @type string $operation Either translate or test.
			 * }
			 */
			do_action(
				'localizepilot_provider_request',
				array(
					'provider'  => $provider,
					'duration'  => ( microtime( true ) - $started ) * 1000,
					'ok'        => $ok,
					'operation' => sanitize_key( $operation ),
				)
			);
		}
	}
}
