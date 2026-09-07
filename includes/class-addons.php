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
	 * Add-ons that asked to register and were refused, keyed by slug, each
	 * with the reason. An add-on reads its own entry to explain itself.
	 *
	 * @var array<string,string>
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
			self::$refused[ $slug ] = sprintf(
				/* translators: 1: add-on name, 2: API version the add-on needs, 3: API version LocalizePilot provides. */
				__( '%1$s needs LocalizePilot add-on API %2$d, and this copy of LocalizePilot provides %3$d. Update LocalizePilot.', 'localizepilot' ),
				(string) ( $addon['name'] ?? $slug ),
				$requires,
				$current
			);

			return false;
		}

		if ( $requires < self::MIN_API ) {
			self::$refused[ $slug ] = sprintf(
				/* translators: 1: add-on name, 2: API version the add-on was built against, 3: oldest API version still supported. */
				__( '%1$s was built for LocalizePilot add-on API %2$d, which this release no longer supports (the oldest is %3$d). Update %1$s.', 'localizepilot' ),
				(string) ( $addon['name'] ?? $slug ),
				$requires,
				self::MIN_API
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
		return self::$refused[ sanitize_key( $slug ) ] ?? '';
	}

	/**
	 * Every refusal, keyed by slug.
	 *
	 * @return array<string,string>
	 */
	public static function refusals(): array {
		return self::$refused;
	}
}
