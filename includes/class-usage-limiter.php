<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

final class Usage_Limiter {
	private const OPTION = 'next_translate_daily_usage';

	/**
	 * @return array{date:string,count:int}
	 */
	public function get_usage(): array {
		$today = wp_date( 'Y-m-d' );
		$usage = get_option( self::OPTION, array() );

		if ( ! is_array( $usage ) || ( $usage['date'] ?? '' ) !== $today ) {
			$usage = array(
				'date'  => $today,
				'count' => 0,
			);
		}

		$usage['count'] = max( 0, (int) ( $usage['count'] ?? 0 ) );
		return $usage;
	}

	public function remaining( int $limit ): int {
		$usage = $this->get_usage();
		return max( 0, max( 1, $limit ) - $usage['count'] );
	}

	public function can_translate( int $limit ): bool {
		return $this->remaining( $limit ) > 0;
	}

	public function increment( int $limit ): void {
		$usage          = $this->get_usage();
		$usage['count'] = min( max( 1, $limit ), $usage['count'] + 1 );
		update_option( self::OPTION, $usage, false );
	}

	public function reset(): void {
		delete_option( self::OPTION );
	}
}
