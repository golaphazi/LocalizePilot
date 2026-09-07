<?php
/**
 * Build configuration for LocalizePilot (free).
 *
 * Only this file differs between plugins. See readme.txt for what each list
 * means and how a remove entry is matched.
 */

$config = (object) [

    // Ship a zip beside the build directory, ready to install.
    // The folder inside it defaults to this plugin's own directory name; set
    // 'slug' here to override that.
    'zip' => true,

    /*
     * Every console script is shipped as readable source. core, ui and router
     * are enqueued by name; the screen-*.js files are picked up by the loader
     * in includes/Admin/class-assets.php, which builds the path from the
     * screen slug - so none of them can be deleted, only compressed.
     */
    'compressJs' => [
        'assets/console/js/core.js',
        'assets/console/js/ui.js',
        'assets/console/js/router.js',
        'assets/console/js/screen-language-switcher.js',
        'assets/console/js/screen-license.js',
        'assets/console/js/screen-providers.js',
        'blocks/language-switcher/index.js',
    ],

    /*
     * Same again for the stylesheets: class-assets.php enqueues the console
     * sheets from a list of names, so tokens, base, layout and components are
     * all live even though they never appear as a literal path.
     */
    'compressCss' => [
        'assets/console/css/tokens.css',
        'assets/console/css/fonts.css',
        'assets/console/css/base.css',
        'assets/console/css/layout.css',
        'assets/console/css/components.css',
        'assets/console/css/rtl.css',
        'assets/editor.css',
        'assets/frontend.css',
        'blocks/language-switcher/editor.css',
    ],

    // Nothing here needs the php-minify service.
    'compressPhp' => [],

    'remove' => [
        '.git',
        '.gitattributes',
        '.gitignore',
        '.DS_Store',
        'node_modules',
    ],
];
