<?php
/**
 * Read model shared by the console's configuration screens.
 *
 * It is deliberately the only Phase 4 class that knows how raw option keys
 * map to providers, usage, and enabled-language preview rows. Templates get
 * presentation-ready arrays and never read WordPress options themselves.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Provider_Catalog;
use LocalizePilot\Usage_Limiter;

defined( 'ABSPATH' ) || exit;

final class Settings_Model {
	/**
	 * Current settings with every default present.
	 *
	 * @var array<string,mixed>
	 */
	private array $settings;

	public function __construct() {
		$this->settings = wp_parse_args( Plugin::instance()->get_settings(), Plugin::defaults() );
	}

	/**
	 * Settings needed by forms, with every provider secret removed.
	 *
	 * @return array<string,mixed>
	 */
	public function settings(): array {
		$settings = $this->settings;

		foreach ( Provider_Catalog::all() as $provider ) {
			$key_field = (string) ( $provider['key_field'] ?? '' );

			if ( '' !== $key_field ) {
				unset( $settings[ $key_field ] );
			}
		}

		return $settings;
	}

	/**
	 * Provider cards and their non-secret configuration state.
	 *
	 * API keys are represented only as a boolean. Their values never cross the
	 * controller/template boundary or appear in the DOM.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function providers(): array {
		$selected = sanitize_key( (string) ( $this->settings['translation_provider'] ?? 'translatex' ) );
		$rows     = array();

		foreach ( Provider_Catalog::all() as $id => $config ) {
			$key_field   = (string) ( $config['key_field'] ?? '' );
			$model_field = (string) ( $config['model_field'] ?? '' );

			$rows[] = array(
				'id'            => $id,
				'label'         => (string) ( $config['label'] ?? $id ),
				'mark'          => (string) ( $config['mark'] ?? strtoupper( substr( $id, 0, 2 ) ) ),
				'description'   => (string) ( $config['description'] ?? '' ),
				'kind'          => (string) ( $config['kind'] ?? 'translation' ),
				'key_field'     => $key_field,
				'model_field'   => $model_field,
				'default_model' => (string) ( $config['default_model'] ?? '' ),
				'model'         => '' !== $model_field
					? (string) ( $this->settings[ $model_field ] ?? $config['default_model'] ?? '' )
					: '',
				'configured'    => '' !== $key_field && '' !== trim( (string) ( $this->settings[ $key_field ] ?? '' ) ),
				'selected'      => $id === $selected,
			);
		}

		return $rows;
	}

	/**
	 * Provider labels for the fallback select.
	 *
	 * @return array<string,string>
	 */
	public function provider_options(): array {
		$options = array( '' => __( 'No fallback provider', 'localizepilot' ) );

		foreach ( Provider_Catalog::all() as $id => $config ) {
			$options[ $id ] = (string) ( $config['label'] ?? $id );
		}

		return $options;
	}

	/**
	 * Enabled languages used by connection tests and the switcher preview.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function languages(): array {
		$source = sanitize_key( (string) ( $this->settings['source_language'] ?? 'en' ) );
		$codes  = array_values(
			array_unique(
				array_merge(
					array( $source ),
					(array) ( $this->settings['enabled_languages'] ?? array() )
				)
			)
		);
		$rows = array();

		foreach ( $codes as $code ) {
			$code = sanitize_key( (string) $code );

			if ( ! Language_Catalog::exists( $code ) ) {
				continue;
			}

			$rows[] = array(
				'code'    => $code,
				'name'    => Language_Catalog::label( $code, 'english' ),
				'native'  => Language_Catalog::label( $code, 'native' ),
				'is_source' => $code === $source ? '1' : '',
			);
		}

		return $rows;
	}

	/**
	 * Daily limiter state for the Providers and Settings summaries.
	 *
	 * @return array{count:int,limit:int,remaining:int,percent:int,enabled:bool}
	 */
	public function usage(): array {
		$usage   = ( new Usage_Limiter() )->get_usage();
		$count   = max( 0, absint( $usage['count'] ?? 0 ) );
		$limit   = max( 1, absint( $this->settings['daily_limit'] ?? 10 ) );
		$enabled = ! empty( $this->settings['daily_limit_enabled'] );

		return array(
			'count'     => $count,
			'limit'     => $limit,
			'remaining' => max( 0, $limit - $count ),
			'percent'   => $enabled ? (int) min( 100, round( 100 * $count / $limit ) ) : 0,
			'enabled'   => $enabled,
		);
	}
}
