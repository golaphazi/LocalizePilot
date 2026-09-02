<?php
/**
 * Read model for the Overview screen.
 *
 * Assembles the KPI row, the attention list, and the provider card from what
 * the plugin already records. Figures with no source behind them — the cache
 * hit rate and the activity feed — stay behind Preview rather than being
 * invented here.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\File_Cache;
use LocalizePilot\Language_Catalog;
use LocalizePilot\Plugin;
use LocalizePilot\Provider_Catalog;
use LocalizePilot\Usage_Limiter;

defined( 'ABSPATH' ) || exit;

final class Overview_Model {
	private Translation_Repository $translations;

	private Language_Stats $languages;

	public function __construct() {
		$this->translations = new Translation_Repository();
		$this->languages    = new Language_Stats();
	}

	/**
	 * The four KPI cards.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function kpis(): array {
		$settings = Plugin::instance()->get_settings();
		$counts   = $this->translations->counts();
		$limiter  = new Usage_Limiter();
		$usage    = $limiter->get_usage();
		$limit    = max( 1, (int) ( $settings['daily_limit'] ?? 10 ) );
		$enabled  = count( (array) ( $settings['enabled_languages'] ?? array() ) ) + 1;

		return array(
			array(
				'label' => __( 'Languages', 'localizepilot' ),
				'value' => number_format_i18n( $enabled ),
				'note'  => sprintf(
					/* translators: %s is the number of languages with translations. */
					__( '%s translated languages', 'localizepilot' ),
					number_format_i18n( $this->languages->translated_count() )
				),
			),
			array(
				'label' => __( 'Translations', 'localizepilot' ),
				'value' => number_format_i18n( $counts['total'] ),
				'note'  => sprintf(
					/* translators: %s is a percentage. */
					__( '%s%% of content localized', 'localizepilot' ),
					number_format_i18n( $this->translations->localized_share() )
				),
			),
			array(
				'label'     => __( 'Needs Review', 'localizepilot' ),
				'value'     => number_format_i18n( $counts['automatic'] ),
				'note'      => sprintf(
					/* translators: %s is a number of translations. */
					__( '%s awaiting a human review', 'localizepilot' ),
					number_format_i18n( $counts['automatic'] )
				),
				'note_tone' => $counts['automatic'] > 0 ? 'warning' : 'muted',
			),
			array(
				'label'    => __( 'Translation Usage', 'localizepilot' ),
				'value'    => empty( $settings['daily_limit_enabled'] )
					? __( 'Unlimited', 'localizepilot' )
					: sprintf( '%d%%', (int) round( 100 * min( $usage['count'], $limit ) / $limit ) ),
				'progress' => empty( $settings['daily_limit_enabled'] ) ? null : (int) round( 100 * min( $usage['count'], $limit ) / $limit ),
				'note'     => empty( $settings['daily_limit_enabled'] )
					? __( 'Daily limit disabled', 'localizepilot' )
					: sprintf(
						/* translators: 1: translations used today, 2: daily limit. */
						__( '%1$s / %2$s used today', 'localizepilot' ),
						number_format_i18n( $usage['count'] ),
						number_format_i18n( $limit )
					),
			),
		);
	}

	/**
	 * Things the site owner should act on. Every item here is computed; when
	 * nothing needs attention the list is empty and the card says so.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function attention(): array {
		$settings = Plugin::instance()->get_settings();
		$counts   = $this->translations->counts();
		$items    = array();

		if ( $counts['automatic'] > 0 ) {
			$items[] = array(
				'count' => number_format_i18n( $counts['automatic'] ),
				'tone'  => 'warning',
				'title' => __( 'Translations need review', 'localizepilot' ),
				'meta'  => __( 'Machine translated and not yet checked by a person.', 'localizepilot' ),
				'url'   => add_query_arg( 'status', 'automatic', Screen_Registry::url( 'translations' ) ),
			);
		}

		if ( $counts['needs_update'] > 0 ) {
			$items[] = array(
				'count' => number_format_i18n( $counts['needs_update'] ),
				'tone'  => 'violet',
				'title' => __( 'Translations are out of date', 'localizepilot' ),
				'meta'  => __( 'The source content changed after these were created.', 'localizepilot' ),
				'url'   => add_query_arg( 'status', 'needs_update', Screen_Registry::url( 'translations' ) ),
			);
		}

		$provider = (string) ( $settings['translation_provider'] ?? 'translatex' );
		$field    = Provider_Catalog::key_field( $provider );
		$key      = '' !== $field ? (string) ( $settings[ $field ] ?? '' ) : '';

		if ( '' === trim( $key ) ) {
			$items[] = array(
				'icon'  => 'nav-providers',
				'tone'  => 'warning',
				'title' => __( 'Add an API key to start translating', 'localizepilot' ),
				'meta'  => sprintf(
					/* translators: %s is the provider name. */
					__( '%s is selected but has no key.', 'localizepilot' ),
					Provider_Catalog::label( $provider )
				),
				'url'   => Screen_Registry::url( 'providers' ),
			);
		}

		foreach ( $this->languages->enabled() as $language ) {
			if ( empty( $language['is_source'] ) && 0 === $language['translated'] ) {
				$items[] = array(
					'icon'  => 'nav-languages',
					'tone'  => 'brand',
					'title' => sprintf(
						/* translators: %s is a language name. */
						__( '%s has no translations yet', 'localizepilot' ),
						$language['name']
					),
					'meta'  => __( 'Enabled, but nothing has been translated into it.', 'localizepilot' ),
					'url'   => add_query_arg( 'language', $language['code'], Screen_Registry::url( 'translations' ) ),
				);
			}
		}

		return array_slice( $items, 0, 4 );
	}

	/**
	 * The configured translation provider, for the Overview provider card.
	 *
	 * @return array<string,mixed>
	 */
	public function provider(): array {
		$settings = Plugin::instance()->get_settings();
		$provider = (string) ( $settings['translation_provider'] ?? 'translatex' );
		$fallback = (string) ( $settings['fallback_provider'] ?? '' );
		$field    = Provider_Catalog::key_field( $provider );
		$key      = '' !== $field ? (string) ( $settings[ $field ] ?? '' ) : '';
		$limiter  = new Usage_Limiter();
		$usage    = $limiter->get_usage();
		$limit    = max( 1, (int) ( $settings['daily_limit'] ?? 10 ) );

		return array(
			'label'     => Provider_Catalog::label( $provider ),
			'mark'      => (string) ( Provider_Catalog::get( $provider )['mark'] ?? '' ),
			'connected' => '' !== trim( $key ),
			'usage'     => (int) $usage['count'],
			'limit'     => $limit,
			'limited'   => ! empty( $settings['daily_limit_enabled'] ),
			'fallback'  => '' !== $fallback ? Provider_Catalog::label( $fallback ) : '',
		);
	}

	/**
	 * Cache figures for the Overview performance card.
	 *
	 * The hit rate the design shows is not recorded anywhere in the plugin, so
	 * it stays behind Preview. The counts beside it are real.
	 *
	 * @return array<string,mixed>
	 */
	public function cache(): array {
		$settings = Plugin::instance()->get_settings();
		$stats    = ( new File_Cache( $settings ) )->stats();

		return array(
			'rendered'     => (int) ( $stats['count'] ?? 0 ),
			'expired'      => (int) ( $stats['expired'] ?? 0 ),
			'snapshots'    => (int) ( $stats['snapshots'] ?? 0 ),
			'object_cache' => ! empty( $settings['object_cache_enabled'] ),
			'writable'     => ! empty( $stats['writable'] ),
			'hit_rate'     => Preview::is_preview( 'cache_hit_rate' ) ? 98 : 0,
		);
	}

	/**
	 * Translation coverage per language, for the Overview coverage card.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function coverage(): array {
		$rows = array_values(
			array_filter(
				$this->languages->enabled(),
				static function ( array $row ): bool {
					return empty( $row['is_source'] );
				}
			)
		);

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return $b['coverage'] <=> $a['coverage'];
			}
		);

		return $rows;
	}

	/**
	 * The source language, for the coverage card header.
	 *
	 * @return array<string,string>
	 */
	public function source_language(): array {
		$code = strtolower( (string) ( Plugin::instance()->get_settings()['source_language'] ?? 'en' ) );

		return array(
			'code' => $code,
			'name' => Language_Catalog::label( $code, 'english' ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function recent_translations(): array {
		return $this->translations->recent( 5 );
	}
}
