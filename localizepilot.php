<?php
/**
 * Plugin Name: LocalizePilot - Multilingual Content & Media
 * Plugin URI: https://themedev.net/
 * Description: Multilingual WordPress content with Gutenberg-editable translations, Google and TranslateX APIs, language-specific media, same-page URLs, and persistent HTML file caching.
 * Version: 1.3.0
 * Author: ThemeDev
 * Author URI: https://themedev.net/
 * Text Domain: localizepilot
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXT_TRANSLATE_VERSION', '1.3.0' );
define( 'NEXT_TRANSLATE_FILE', __FILE__ );
define( 'NEXT_TRANSLATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEXT_TRANSLATE_URL', plugin_dir_url( __FILE__ ) );

require_once NEXT_TRANSLATE_PATH . 'includes/class-language-catalog.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-usage-limiter.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-router.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-translation-client-interface.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-translatex-client.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-google-translate-client.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-client-factory.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-file-cache.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-html-translator.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-language-switcher.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-translation-manager.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-settings.php';
require_once NEXT_TRANSLATE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'NextTranslate\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NextTranslate\\Plugin', 'deactivate' ) );

NextTranslate\Plugin::instance()->boot();
