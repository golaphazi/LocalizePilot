<?php
/**
 * Translate one post into one language, from code.
 *
 * Part of add-on API 3, and the supported way for anything other than a person
 * in the editor to produce a translation — a bulk job, a migration, an add-on.
 * The editor's own buttons go through admin-post handlers that check a nonce,
 * check a capability and end in a redirect; none of that means anything to a
 * background job, so this skips it. Callers that act for a user are
 * responsible for checking that user's capabilities themselves.
 *
 * Failures come back as WP_Error rather than exceptions, so a job can record
 * one item failing and carry on with the next.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Translation_Service {
	/**
	 * Statuses that mean a person has touched the translation.
	 *
	 * Refreshing one of these replaces their work with a fresh machine
	 * translation, so it only happens when the caller asks for it by name.
	 */
	public const PROTECTED_STATUSES = array( 'edited', 'reviewed' );

	/**
	 * Create the translation, or refresh the one that exists.
	 *
	 * @param int                 $source_id Source post ID.
	 * @param string              $language  Target language code.
	 * @param array<string,mixed> $args {
	 *     @type bool $overwrite_edited Replace a translation a person has edited
	 *                                  or reviewed. Default false.
	 * }
	 * @return array{id:int,created:bool,language:string}|\WP_Error
	 */
	public static function translate( int $source_id, string $language, array $args = array() ) {
		$language = sanitize_key( $language );
		$manager  = Plugin::instance()->translations();
		$existing = $manager->find_translation( $source_id, $language );

		if ( $existing instanceof \WP_Post && empty( $args['overwrite_edited'] ) ) {
			$status = (string) get_post_meta( $existing->ID, Translation_Manager::META_STATUS, true );

			if ( in_array( $status, self::PROTECTED_STATUSES, true ) ) {
				return new \WP_Error(
					'localizepilot_translation_protected',
					__( 'This translation has been edited by hand, so it was left as it is.', 'localizepilot' ),
					array(
						'id'     => (int) $existing->ID,
						'status' => $status,
					)
				);
			}
		}

		try {
			$id = $manager->translate( $source_id, $language );
		} catch ( Limit_Reached_Exception $exception ) {
			return new \WP_Error( 'localizepilot_daily_limit', wp_strip_all_tags( $exception->getMessage() ) );
		} catch ( \Throwable $exception ) {
			return new \WP_Error( 'localizepilot_translation_failed', wp_strip_all_tags( $exception->getMessage() ) );
		}

		return array(
			'id'       => $id,
			'created'  => ! $existing instanceof \WP_Post,
			'language' => $language,
		);
	}

	/**
	 * Where a translation stands, without producing one.
	 *
	 * For a job deciding what to do with an item: "missing" has no record,
	 * "outdated" was made from an older version of the source, "current" is
	 * up to date, and "protected" has been touched by a person.
	 */
	public static function state( int $source_id, string $language ): string {
		$existing = Plugin::instance()->translations()->find_translation( $source_id, sanitize_key( $language ) );

		if ( ! $existing instanceof \WP_Post ) {
			return 'missing';
		}

		$status = (string) get_post_meta( $existing->ID, Translation_Manager::META_STATUS, true );

		if ( in_array( $status, self::PROTECTED_STATUSES, true ) ) {
			return 'protected';
		}

		return 'needs_update' === $status ? 'outdated' : 'current';
	}

	/**
	 * The one failure a job has to treat differently: the daily limit.
	 *
	 * Every other failure belongs to one item. This one belongs to the day, and
	 * a job that met it should stop and wait rather than fail every item left.
	 */
	public static function is_limit_error( \WP_Error $error ): bool {
		return 'localizepilot_daily_limit' === $error->get_error_code();
	}
}
