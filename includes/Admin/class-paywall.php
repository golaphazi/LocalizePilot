<?php
/**
 * The one gate for console surfaces that belong to a paid add-on.
 *
 * Deliberately a separate class from Preview, and the distinction is worth
 * defending: Preview means "LocalizePilot has not built this", Paywall means
 * "this is built, and it lives in the Pro add-on". Merging them would let a
 * feature nobody has written hide behind a price tag, which is the one
 * outcome this console has been careful to avoid everywhere else.
 *
 * That distinction implies a release rule. An entry in the catalogue below is
 * a promise that buying the add-on gets you the thing described, so a feature
 * only earns an entry once the add-on actually ships it. Adding the entry
 * first would be selling an empty screen.
 *
 * The whole surface is one grep:
 *
 *     grep -rn "Paywall::" includes/ templates/
 *     grep -rn "data-lp-paywall" templates/
 *
 * Nothing here knows that an add-on called "Pro" exists. LocalizePilot
 * publishes the catalogue and two filters; whatever provides these features
 * unlocks them and rewrites the call to action.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Paywall {
	/** The element carrying the explanation every locked control points at. */
	public const HINT_ID = 'lp-paywall-hint';

	/**
	 * What a locked control is described as, for assistive technology.
	 */
	public static function hint(): string {
		return __( 'This control belongs to the paid add-on. Activating it opens a description of what the add-on includes instead of performing the action.', 'localizepilot' );
	}

	/**
	 * Where someone who does not have the add-on goes to get it.
	 *
	 * A plain link, opened in a new tab. The vendor also offers an overlay
	 * checkout that works by loading a script from their CDN, which the
	 * WordPress.org guidelines rule out for a plugin hosted there — so this
	 * stays a link, and no third-party script is ever loaded into wp-admin.
	 */
	private const CHECKOUT_URL = 'https://localizepilot.lemonsqueezy.com/checkout/buy/ebe51d24-e0b5-4e2f-8bcb-6a94d848a977';

	/**
	 * Features provided by an add-on rather than by LocalizePilot.
	 *
	 * Each entry is what the modal shows: what the feature is called, one
	 * sentence on what it does, and the specific things it gives you. Write
	 * them as promises that can be kept.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function features(): array {
		/*
		 * The catalogue lives here, in LocalizePilot, and not in the add-on
		 * that provides these features — because the person who needs to read
		 * it is precisely the person who has not installed that add-on. An
		 * add-on cannot describe itself to someone who does not have it.
		 *
		 * Performance monitoring is here because the add-on measures it: the
		 * render timer, cache outcome and provider stopwatch are published as
		 * actions, and something is listening. Media filters are here because
		 * the add-on now owns a real attachment-variant model and fills the
		 * query, row and counter seams from that data.
		 */
		$features = array(
			'performance' => array(
				'title'   => __( 'Performance monitoring', 'localizepilot' ),
				'promise' => __( 'Measure what your translated pages actually cost your visitors, from real requests rather than estimates.', 'localizepilot' ),
				'points'  => array(
					__( 'Render time for translated pages: fastest, average and slowest', 'localizepilot' ),
					__( 'Cache hit rate, measured on real page views', 'localizepilot' ),
					__( 'Response time for each translation provider you use', 'localizepilot' ),
					__( 'A breakdown by language, so you can see which one is slow', 'localizepilot' ),
					__( 'The specific pages running behind, with their timings', 'localizepilot' ),
					__( 'Thirty days of history', 'localizepilot' ),
				),
			),
			'media_filters' => array(
				'title'   => __( 'Media language and status filters', 'localizepilot' ),
				'promise' => __( 'See which source files have real language-specific variants and which variants are older than their source.', 'localizepilot' ),
				'points'  => array(
					__( 'Filter the Media screen by target language', 'localizepilot' ),
					__( 'Filter by localized, needs update or not localized', 'localizepilot' ),
					__( 'Show real language chips and derived status on every source file', 'localizepilot' ),
					__( 'Count source media and language-specific variants accurately', 'localizepilot' ),
				),
			),
			'switcher_layouts' => array(
				'title'   => __( 'Button switcher layout', 'localizepilot' ),
				'promise' => __( 'A compact language button that opens a list of your languages — the layout that fits best in a crowded or mobile header.', 'localizepilot' ),
				'points'  => array(
					__( 'Shows the current language as a flag and code on one small button', 'localizepilot' ),
					__( 'Opens a panel listing every enabled language by name', 'localizepilot' ),
					__( 'Works without JavaScript, and closes on Escape or a click elsewhere when it is available', 'localizepilot' ),
					__( 'Available in the header, the shortcode and the Gutenberg block', 'localizepilot' ),
				),
			),
			'switcher_placements' => array(
				'title'   => __( 'Floating and footer switcher', 'localizepilot' ),
				'promise' => __( 'Put the language switcher where visitors can always reach it, not only in the header.', 'localizepilot' ),
				'points'  => array(
					__( 'Floating: a switcher pinned to the corner of the screen while visitors scroll', 'localizepilot' ),
					__( 'Footer: a switcher at the bottom of every page', 'localizepilot' ),
					__( 'Uses the layout, labels and flags you have already chosen', 'localizepilot' ),
					__( 'Falls back to the header if the add-on is ever switched off', 'localizepilot' ),
				),
			),
			'browser_language' => array(
				'title'   => __( 'Browser language suggestion', 'localizepilot' ),
				'promise' => __( 'Offer visitors their own language when their browser says they would prefer it.', 'localizepilot' ),
				'points'  => array(
					__( 'Suggests the matching language version with one click to switch', 'localizepilot' ),
					__( 'Never redirects: visitors and search engines always see the page they asked for', 'localizepilot' ),
					__( 'Decided in the visitor\'s browser, so it works with every page cache', 'localizepilot' ),
					__( 'Remembers when a visitor says no', 'localizepilot' ),
				),
			),
			'cache_filters' => array(
				'title'   => __( 'Cache language and status filters', 'localizepilot' ),
				'promise' => __( 'Narrow the cache history to one language, or to the pages that have expired.', 'localizepilot' ),
				'points'  => array(
					__( 'Filter cached pages and snapshots by language', 'localizepilot' ),
					__( 'Show only active or only expired entries', 'localizepilot' ),
					__( 'Combine both with the type tabs', 'localizepilot' ),
				),
			),
		);

		if ( self::is_component_gallery() ) {
			$features['__gallery'] = array(
				'title'   => __( 'Sample locked feature', 'localizepilot' ),
				'promise' => __( 'Nothing is behind this. It exists so the paywall modal can be reviewed in the component gallery before there is anything to sell.', 'localizepilot' ),
				'points'  => array(
					__( 'One promise per line, written as something that can be kept', 'localizepilot' ),
					__( 'Specific enough to be checked against the add-on', 'localizepilot' ),
					__( 'Never a restatement of the feature name', 'localizepilot' ),
				),
			);
		}

		/**
		 * Filter the catalogue of add-on features.
		 *
		 * @param array<string,array<string,mixed>> $features Feature key => {title, promise, points}.
		 */
		return (array) apply_filters( 'localizepilot_paywall_features', $features );
	}

	/**
	 * True when the reviewer asked for the component gallery, which is the
	 * only place a sample catalogue entry is allowed to exist.
	 *
	 * Mirrors Abstract_Screen::is_component_gallery(), including its
	 * capability check, so sample content can never reach anyone who is not
	 * already able to see the console.
	 */
	private static function is_component_gallery(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch with no side effects.
		return ! empty( $_GET['lp_kitchen_sink'] ) && current_user_can( 'manage_options' );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $feature ): ?array {
		return self::features()[ $feature ] ?? null;
	}

	/**
	 * Features the visitor cannot use, keyed by feature.
	 *
	 * Everything in the catalogue is locked until something says otherwise —
	 * an add-on unsets the keys it provides, and only while it is entitled to.
	 * Defaulting to locked means a licensing bug leaves a feature unreachable
	 * rather than free.
	 *
	 * @return array<string,bool>
	 */
	public static function locked(): array {
		$locked = array_fill_keys( array_keys( self::features() ), true );

		/**
		 * Filter which add-on features are locked.
		 *
		 * @param array<string,bool> $locked Feature key => true when locked.
		 */
		return (array) apply_filters( 'localizepilot_paywall_locked', $locked );
	}

	/**
	 * True when a feature is catalogued and not currently available.
	 *
	 * A key that is not in the catalogue is not locked: it is simply not an
	 * add-on feature, and asking about it should not make it one.
	 */
	public static function is_locked( string $feature ): bool {
		return ! empty( self::locked()[ $feature ] );
	}

	public static function is_unlocked( string $feature ): bool {
		return ! self::is_locked( $feature );
	}

	/**
	 * Attributes for a control that renders but is not yours to use yet.
	 *
	 * Returns an empty string once the feature unlocks, so call sites need no
	 * conditional of their own — the same contract Preview::attributes() has.
	 *
	 * Two things call sites have to get right, because the browser forces
	 * them:
	 *
	 * 1. A disabled control emits no click event, so a disabled <select> or
	 *    <button> can never open the modal. Put this marker on the wrapper
	 *    around such a control, not on the control itself.
	 * 2. That wrapper then has to be reachable, which is why this carries
	 *    role and tabindex. On a control that can stay enabled — a plain
	 *    button — put the marker directly on it and leave those off by
	 *    passing false.
	 */
	public static function attributes( string $feature, bool $focusable = true ): string {
		if ( self::is_unlocked( $feature ) ) {
			return '';
		}

		$entry = self::get( $feature );
		$title = null !== $entry ? (string) $entry['title'] : '';

		/*
		 * aria-describedby carries the reason. A shared description rather
		 * than a per-feature one: it supplements the control's own label,
		 * which already says what the control is, so naming the feature twice
		 * would only make it longer to listen to.
		 */
		$attributes = sprintf(
			' data-lp-paywall="%1$s" aria-disabled="true" aria-describedby="%3$s" title="%2$s"',
			esc_attr( $feature ),
			esc_attr(
				sprintf(
					/* translators: %s is the name of the feature, e.g. "Performance monitoring". */
					__( '%s is part of the paid add-on. Select to see what it includes.', 'localizepilot' ),
					$title
				)
			),
			esc_attr( self::HINT_ID )
		);

		return $focusable ? $attributes . ' role="button" tabindex="0"' : $attributes;
	}

	/**
	 * What the modal should offer, given how far along the visitor already is.
	 *
	 * The default is the only state LocalizePilot can know on its own: nothing
	 * provides these features here. An add-on that is installed but not yet
	 * entitled replaces this with its own call to action — activate a licence,
	 * renew an expired one — so the modal asks for the next step rather than
	 * asking someone to buy what they already own.
	 *
	 * @return array<string,mixed>
	 */
	public static function state(): array {
		$state = array(
			'key'       => 'unavailable',
			'eyebrow'   => __( 'Paid add-on', 'localizepilot' ),
			'cta_label' => __( 'Unlock with Pro', 'localizepilot' ),
			'cta_url'   => self::checkout_url(),
			'external'  => true,
			'note'      => __( 'Already purchased? Install the add-on, then activate your license.', 'localizepilot' ),
		);

		/**
		 * Filter the paywall's call to action.
		 *
		 * @param array<string,mixed> $state {key, eyebrow, cta_label, cta_url, external, note}.
		 */
		return (array) apply_filters( 'localizepilot_paywall_state', $state );
	}

	/**
	 * Where to buy.
	 */
	public static function checkout_url(): string {
		/**
		 * Filter the checkout URL.
		 *
		 * @param string $url Checkout URL.
		 */
		return (string) apply_filters( 'localizepilot_checkout_url', self::CHECKOUT_URL );
	}

	/**
	 * The catalogue as the client needs it, for the modal to fill itself in.
	 *
	 * @return array<string,mixed>
	 */
	public static function payload(): array {
		$features = array();

		foreach ( self::features() as $key => $entry ) {
			if ( self::is_unlocked( (string) $key ) ) {
				continue;
			}

			$features[ $key ] = array(
				'title'   => (string) ( $entry['title'] ?? '' ),
				'promise' => (string) ( $entry['promise'] ?? '' ),
				'points'  => array_map( 'strval', (array) ( $entry['points'] ?? array() ) ),
			);
		}

		return array(
			'features' => $features,
			'state'    => self::state(),
		);
	}
}
