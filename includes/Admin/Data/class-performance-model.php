<?php
/**
 * Read model for cache configuration, summary, and generated-file history.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Analytics;
use LocalizePilot\File_Cache;
use LocalizePilot\Plugin;
use LocalizePilot\Router;

defined( 'ABSPATH' ) || exit;

final class Performance_Model {
	/**
	 * @var array<string,mixed>
	 */
	private array $settings;
	private File_Cache $cache;
	private Analytics $analytics;

	public function __construct() {
		$this->settings  = wp_parse_args( Plugin::instance()->get_settings(), Plugin::defaults() );
		$this->cache     = new File_Cache( $this->settings );
		$this->analytics = new Analytics( new Router() );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function settings(): array {
		return array(
			'cache_enabled'        => ! empty( $this->settings['cache_enabled'] ) ? 1 : 0,
			'object_cache_enabled' => ! empty( $this->settings['object_cache_enabled'] ) ? 1 : 0,
			'cache_hours'          => max( 1, absint( $this->settings['cache_hours'] ?? 24 ) ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function stats(): array {
		return $this->cache->stats();
	}

	/**
	 * Cache history plus the existing first-party visitor counts.
	 *
	 * @return array<string,mixed>
	 */
	public function history( int $page, string $type ): array {
		$history          = $this->cache->history( $page, 15, $type );
		$history['items'] = $this->analytics->attach_counts( (array) ( $history['items'] ?? array() ) );

		return $history;
	}

	public function host_cache(): string {
		return File_Cache::detected_external_cache_label();
	}
}
