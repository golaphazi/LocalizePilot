<?php
/**
 * Migration console screen: bring translations over from WPML or Polylang.
 *
 * Gathers what each supported plugin left on this site and what a migration
 * would do with it. The work itself happens in Migrator, driven by the
 * screen's script one batch at a time.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Screens;

use LocalizePilot\Admin\Screen_Registry;
use LocalizePilot\Migration\Migrator;

defined( 'ABSPATH' ) || exit;

final class Migration_Screen extends Abstract_Screen {
	public function slug(): string {
		return 'migration';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$sources = array();

		foreach ( Migrator::sources() as $source ) {
			$detected = $source->detected();

			$sources[] = array(
				'id'       => $source->id(),
				'label'    => $source->label(),
				'detected' => $detected,
				'preview'  => $detected ? Migrator::preview( $source ) : array(),
			);
		}

		return array(
			'sources'      => $sources,
			'run'          => Migrator::run(),
			'drafted'      => Migrator::drafted_count(),
			'settings_url' => Screen_Registry::url( 'settings' ),
		);
	}
}
