<?php
/**
 * Plugin Name: LocalizePilot – Multilingual Content & Media
 * Plugin URL: https://localizepilot.com/
 * Description: Create multilingual WordPress content, edit translations in Gutenberg, localize media, add language switchers, and cache translated HTML.
 * Version: 1.0.1
 * Author: Golaphazi
 * Author URI: https://github.com/golaphazi/
 * Text Domain: localizepilot
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'LOCALIZEPILOT_VERSION', '1.0.1' );
define( 'LOCALIZEPILOT_FILE', __FILE__ );
define( 'LOCALIZEPILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOCALIZEPILOT_URL', plugin_dir_url( __FILE__ ) );


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
