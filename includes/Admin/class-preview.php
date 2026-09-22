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
	/** The element carrying the explanation every gated control points at. */
	public const HINT_ID = 'lp-preview-hint';

	/**
	 * What a gated control is described as, for assistive technology.
	 */
	public static function hint(): string {
		return __( 'This control is part of an interface preview. It is not connected to anything yet, so using it has no effect.', 'localizepilot' );
	}

	/**
	 * Features whose UI exists but whose behaviour does not.
	 *
	 * @return array<string,bool>
	 */
	public static function features(): array {
		$features = array(
			/*
			 * Media localization, split three ways rather than gated as one.
			 * Filtering a library by language, acting on a selection, and
			 * producing a localized variant are three separate pieces of
			 * work that will not land together — one key for all three would
			 * mean unlocking the ones that are not built to unlock the one
			 * that is.
			 */
			'media_bulk'        => true,
			'media_localize'    => true,
			'canonical_editing' => true,
			'notifications'     => true,
			'analytics_export'  => true,
			'cache_hit_rate'    => true,
			'bulk_actions'      => true,
			'cache_automation'  => true,
			'license'           => true,
			'switcher_order'    => true,
			'switcher_behavior' => true,
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
	 * True when any one of several features is still preview-only.
	 *
	 * For the note a screen prints about itself when its capabilities landed
	 * piecemeal: as long as one piece is unbuilt, the screen still has
	 * something to disclose.
	 *
	 * @param array<int,string> $features Feature keys.
	 */
	public static function any( array $features ): bool {
		foreach ( $features as $feature ) {
			if ( self::is_preview( (string) $feature ) ) {
				return true;
			}
		}

		return false;
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

		/*
		 * aria-describedby, not title alone. A title attribute is announced
		 * inconsistently by screen readers and never shown on a touch device,
		 * so a control that is inert for a reason would be inert for no
		 * stated reason. The description it points at is rendered once in the
		 * console shell; describedby supplements the control's own label
		 * rather than replacing it, which aria-label would.
		 */
		return sprintf(
			' data-lp-preview="1" aria-disabled="true" aria-describedby="%1$s" title="%2$s"',
			esc_attr( self::HINT_ID ),
			esc_attr__( 'Not connected yet — this is a preview of the interface.', 'localizepilot' )
		);
	}
}
