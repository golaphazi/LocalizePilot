<?php
/**
 * Plugin Name: LocalizePilot – Multilingual Content & Media
 * Plugin URL: https://localizepilot.com/
 * Description: Create multilingual WordPress content, edit translations in Gutenberg, localize media, add language switchers, and cache translated HTML.
 * Version: 1.0.3
 * Author: Golaphazi
 * Author URI: https://github.com/golaphazi/
 * Text Domain: localizepilot
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'LOCALIZEPILOT_VERSION', '1.0.3' );

/*
 * The add-on API version — see includes/class-addons.php.
 *
 * Deliberately separate from the release version above. This moves only when
 * the seams an add-on hooks into change shape, so an add-on can state exactly
 * what it needs without pinning itself to a bug-fix release.
 */
define( 'LOCALIZEPILOT_API', 1 );

define( 'LOCALIZEPILOT_FILE', __FILE__ );
define( 'LOCALIZEPILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOCALIZEPILOT_URL', plugin_dir_url( __FILE__ ) );


/**
 * Autoload LocalizePilot classes.
 *
 * Maps LocalizePilot\Admin\Screens\Overview_Screen to
 * includes/Admin/Screens/class-overview-screen.php, following the naming
 * convention already used by the classes in includes/.
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( 0 !== strpos( $class, 'LocalizePilot\\' ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class, strlen( 'LocalizePilot\\' ) ) );
		$name  = array_pop( $parts );
		$file  = LOCALIZEPILOT_PATH . 'includes/'
			. ( $parts ? implode( '/', $parts ) . '/' : '' )
			. 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/*
 * Loaded eagerly rather than left to the autoloader: an add-on checks
 * class_exists() on it to decide whether LocalizePilot is present at all, and
 * that check must not depend on autoload timing.
 */
require_once LOCALIZEPILOT_PATH . 'includes/class-addons.php';

require_once LOCALIZEPILOT_PATH . 'includes/class-language-catalog.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-usage-limiter.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-router.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-provider-catalog.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-translation-client-interface.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-ai-client-base.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-openai-compatible-client.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-gemini-client.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-anthropic-client.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-fallback-client.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-translatex-client.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-google-translate-client.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-client-factory.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-analytics.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-file-cache.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-html-translator.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-language-switcher.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-translation-manager.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-settings.php';
require_once LOCALIZEPILOT_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'LocalizePilot\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LocalizePilot\\Plugin', 'deactivate' ) );

LocalizePilot\Plugin::instance()->boot();
LocalizePilot\Admin\Admin::instance()->hooks();
