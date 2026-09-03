<?php
/**
 * The one gate for console surfaces that are designed but not yet wired up.
 *
 * Nothing renders placeholder behaviour without going through this class, so
 * the full extent of the unfinished surface is always one grep away:
 *
 *     grep -rn "Preview::" includes/ templates/
 *     grep -rn "data-lp-preview" templates/
 *
 * Promoting a feature to real means flipping one entry here and swapping its
 * repository. No template changes.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Preview {
	/**
	 * Features whose UI exists but whose behaviour does not.
	 *
	 * @return array<string,bool>
	 */
	public static function features(): array {
		$features = array(
			'media'             => true,
			'canonical_editing' => true,
			'notifications'     => true,
			'analytics_export'  => true,
			'cache_hit_rate'    => true,
			'bulk_actions'      => true,
			'cache_automation'  => true,
			'cache_filters'     => true,
			'license'           => true,
			'switcher_flags'    => true,
			'switcher_order'    => true,
			'switcher_placement'=> true,
			'switcher_behavior' => true,
			'switcher_block'    => true,
			/*
			 * The Performance screen's figures. Nothing in the plugin times a
			 * render, a provider call, or a cache lookup, so every duration,
			 * rate and score on that screen is unmeasured.
			 */
			'perf_metrics'      => true,
		);

		/**
		 * Filter which console features are still preview-only.
		 *
		 * @param array<string,bool> $features Feature key => true when preview-only.
		 */
		return (array) apply_filters( 'localizepilot_preview_features', $features );
	}

	/**
	 * True when a feature is still preview-only, i.e. its UI renders but does
	 * nothing.
	 */
	public static function is_preview( string $feature ): bool {
		return ! empty( self::features()[ $feature ] );
	}

	/**
	 * True when a feature is fully implemented.
	 */
	public static function is_live( string $feature ): bool {
		return ! self::is_preview( $feature );
	}

	/**
	 * Attributes to print on any control that renders but does nothing.
	 *
	 * Returns an empty string once the feature goes live, so call sites need
	 * no conditional of their own.
	 */
	public static function attributes( string $feature ): string {
		if ( self::is_live( $feature ) ) {
			return '';
		}

		return sprintf(
			' data-lp-preview="1" aria-disabled="true" title="%s"',
			esc_attr__( 'Not connected yet — this is a preview of the interface.', 'localizepilot' )
		);
	}
}
