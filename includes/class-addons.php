<?php
/**
 * The register of add-on plugins extending LocalizePilot.
 *
 * LocalizePilot never knows which add-ons exist, or that any particular one
 * does. It publishes an integer API version and a set of filters; an add-on
 * declares the API version it was built against and consumes those filters.
 * Everything else about the relationship is the add-on's business.
 *
 * The version handshake is the point of this class. A free release that
 * changes a seam bumps LOCALIZEPILOT_API; an add-on built against an older
 * seam then fails to register and can go dormant with an explanation, rather
 * than calling a function that no longer exists and taking the site down with
 * it. That is the failure this whole arrangement exists to prevent, so the
 * check happens before an add-on gets to run anything.
 *
 * Registering looks like this, from the add-on's own bootstrap:
 *
 *     add_action(
 *         'plugins_loaded',
 *         static function () {
 *             if ( ! class_exists( '\\LocalizePilot\\Addons' ) ) {
 *                 return; // LocalizePilot is not installed or not active.
 *             }
 *
 *             if ( ! \LocalizePilot\Addons::register( array(
 *                 'slug'         => 'pro',
 *                 'name'         => 'LocalizePilot Pro',
 *                 'version'      => '1.0.0',
 *                 'file'         => __FILE__,
 *                 'requires_api' => 1,
 *             ) ) ) {
 *                 // Tell the user why, then do nothing else.
 *                 return;
 *             }
 *
 *             // Safe to hook.
 *         },
 *         5
 *     );
 *
 * @package LocalizePilot
 */

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Addons {
	/**
	 * The oldest API version this release still honours.
	 *
	 * Raising this is a deliberate break: every add-on built against anything
	 * older stops registering. It should change only when a seam is removed
	 * rather than added to.
	 */
	public const MIN_API = 1;

	/**
	 * Add-ons that registered successfully, keyed by slug.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $active = array();

	/**
	 * Add-ons that asked to register and were refused, keyed by slug. The
	 * structured record lets registration remain safe on plugins_loaded while
	 * the human message is translated later, after WordPress permits it.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $refused = array();

	/**
	 * The API version this release of LocalizePilot publishes.
	 *
	 * Deliberately not the release version: LOCALIZEPILOT_VERSION moves on
	 * every bug fix, and an add-on does not care about bug fixes. This moves
	 * only when the seams below change shape.
	 */
	public static function api_version(): int {
		return defined( 'LOCALIZEPILOT_API' ) ? (int) LOCALIZEPILOT_API : 0;
	}

	/**
	 * Register an add-on.
	 *
	 * @param array<string,mixed> $addon {
	 *     @type string $slug         Required. Unique, lowercase, e.g. "pro".
	 *     @type string $name         Required. Human-readable plugin name.
	 *     @type string $version      Required. The add-on's own version.
	 *     @type string $file         Required. The add-on's main plugin file.
	 *     @type int    $requires_api Required. API version it was built against.
	 * }
	 * @return bool True when the add-on may hook. False means it must not.
	 */
	public static function register( array $addon ): bool {
		$slug = sanitize_key( (string) ( $addon['slug'] ?? '' ) );

		if ( '' === $slug ) {
			return false;
		}

		if ( isset( self::$active[ $slug ] ) ) {
			// Registering twice is a bug in the add-on, not a reason to fail
			// it: the first registration already decided the answer.
			return true;
		}

		$requires = (int) ( $addon['requires_api'] ?? 0 );
		$current  = self::api_version();

		if ( $requires > $current ) {
			self::$refused[ $slug ] = array(
				'type'     => 'newer',
				'name'     => (string) ( $addon['name'] ?? $slug ),
				'requires' => $requires,
				'current'  => $current,
			);

			return false;
		}

		if ( $requires < self::MIN_API ) {
			self::$refused[ $slug ] = array(
				'type'     => 'older',
				'name'     => (string) ( $addon['name'] ?? $slug ),
				'requires' => $requires,
				'minimum'  => self::MIN_API,
			);

			return false;
		}

		self::$active[ $slug ] = array(
			'slug'         => $slug,
			'name'         => (string) ( $addon['name'] ?? $slug ),
			'version'      => (string) ( $addon['version'] ?? '' ),
			'file'         => (string) ( $addon['file'] ?? '' ),
			'requires_api' => $requires,
		);

		/**
		 * Fires once an add-on has registered successfully.
		 *
		 * @param array<string,mixed> $addon The stored registration.
		 */
		do_action( 'localizepilot_addon_registered', self::$active[ $slug ] );

		return true;
	}

	/**
	 * Every registered add-on.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		return self::$active;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $slug ): ?array {
		return self::$active[ sanitize_key( $slug ) ] ?? null;
	}

	/**
	 * True when an add-on registered successfully and may be relied on.
	 *
	 * This answers "is it installed and compatible", never "is it licensed".
	 * Licensing is the add-on's own business and LocalizePilot has no opinion
	 * on it.
	 */
	public static function is_active( string $slug ): bool {
		return isset( self::$active[ sanitize_key( $slug ) ] );
	}

	/**
	 * Why an add-on was refused, or an empty string if it was not.
	 */
	public static function refusal( string $slug ): string {
		$refusal = self::$refused[ sanitize_key( $slug ) ] ?? array();

		return empty( $refusal ) ? '' : self::format_refusal( $refusal );
	}

	/**
	 * Every refusal, keyed by slug.
	 *
	 * @return array<string,string>
	 */
	public static function refusals(): array {
		$messages = array();

		foreach ( self::$refused as $slug => $refusal ) {
			$messages[ $slug ] = self::format_refusal( $refusal );
		}

		return $messages;
	}

	/**
	 * Turn a refusal record into user-facing copy at the moment it is read.
	 *
	 * Add-ons register on plugins_loaded. Calling __() there triggers a
	 * WordPress 6.7+ doing-it-wrong notice, so an unusually early reader gets
	 * the same English fallback; normal admin notices run after init and are
	 * translated.
	 *
	 * @param array<string,mixed> $refusal Stored refusal facts.
	 */
	private static function format_refusal( array $refusal ): string {
		$name     = (string) ( $refusal['name'] ?? '' );
		$requires = (int) ( $refusal['requires'] ?? 0 );

		if ( 'older' === (string) ( $refusal['type'] ?? '' ) ) {
			if ( did_action( 'init' ) ) {
				/* translators: 1: add-on name, 2: its API version, 3: oldest supported API version. */
				$format = __( '%1$s was built for LocalizePilot add-on API %2$d, which this release no longer supports (the oldest is %3$d). Update %1$s.', 'localizepilot' );
			} else {
				$format = '%1$s was built for LocalizePilot add-on API %2$d, which this release no longer supports (the oldest is %3$d). Update %1$s.';
			}

			return sprintf( $format, $name, $requires, (int) ( $refusal['minimum'] ?? self::MIN_API ) );
		}

		if ( did_action( 'init' ) ) {
			/* translators: 1: add-on name, 2: required API version, 3: provided API version. */
			$format = __( '%1$s needs LocalizePilot add-on API %2$d, and this copy of LocalizePilot provides %3$d. Update LocalizePilot.', 'localizepilot' );
		} else {
			$format = '%1$s needs LocalizePilot add-on API %2$d, and this copy of LocalizePilot provides %3$d. Update LocalizePilot.';
		}

		return sprintf( $format, $name, $requires, (int) ( $refusal['current'] ?? self::api_version() ) );
	}
}
