<?php
/**
 * Build the distributable copy of this plugin.
 *
 *   php build.php
 *
 * Copies the plugin to a sibling <folder>dist/ directory, minifies what
 * config.php lists, deletes what config.php lists, then drops build-package
 * itself. The source tree is only ever read from, never written to, so a
 * failed build cannot damage what you are working on.
 *
 * Exits 1 when any step failed, so a wrapper script can tell.
 */

if (PHP_SAPI !== 'cli') {
    exit("run this from the command line: php build.php\n");
}

require __DIR__ . '/helper.php';

define('SRC_DIR', dirname(__DIR__, 1));
define('PLUGIN_SLUG', basename(SRC_DIR));

// Derived from the folder name rather than hard-coded, so this whole directory
// can be copied to a new addon and still land beside it.
define('DIST_DIR', dirname(__DIR__, 2) . '/' . PLUGIN_SLUG . 'dist/');

$exception = false;

require __DIR__ . '/config.php';

$remove = isset($config->remove) ? $config->remove : [];

printf("building %s\n  from %s\n  to   %s\n\n", PLUGIN_SLUG, SRC_DIR, DIST_DIR);

echo "cleaning old build files... \n";
if (file_exists(DIST_DIR)) {
    recursiveRemove(DIST_DIR, false);
}
echo "OK\n\n";

echo "copying files to dist... \n";
$copied = xcopy(SRC_DIR, DIST_DIR, $remove);
echo "copied $copied file(s)\n";
echo "OK\n\n";

if ($copied === 0) {
    echo "nothing was copied - check the paths above\n\n";
    $exception = true;
}

require __DIR__ . '/minify.php';
require __DIR__ . '/remove.php';

echo "deleting temp files... \n";
recursiveRemove(DIST_DIR . 'build-package', false);
echo "OK\n\n";

/* ------------------------------------------------------------------ zip -- */

$archive = null;

if (!empty($config->zip)) {
    echo "packaging... \n";

    /*
     * The folder inside the archive is what WordPress names the plugin
     * directory on install, so it must be the plugin's real slug - never the
     * name of the build output directory. Defaults to the source folder name,
     * which is what this install already uses.
     */
    $slug = !empty($config->slug) ? $config->slug : PLUGIN_SLUG;
    $main = plugin_main_file(DIST_DIR);

    if ($main === null) {
        echo "  no file with a Plugin Name header - cannot read the version \n";
        $exception = true;
    } else {
        $version = plugin_version($main);

        if ($version === '') {
            echo '  no Version header in ' . basename($main) . " \n";
            $exception = true;
        } else {
            $archive = dirname(rtrim(DIST_DIR, '/\\')) . '/' . $slug . '-' . $version . '.zip';

            foreach ([$archive, $archive . '.sha256'] as $stale) {
                if (file_exists($stale)) {
                    unlink($stale);
                }
            }

            if (!zip_dir(DIST_DIR, $archive, $slug)) {
                echo "  failed to write the archive \n";
                $exception = true;
                $archive   = null;
            } else {
                $hash = hash_file('sha256', $archive);
                file_put_contents($archive . '.sha256', $hash . '  ' . basename($archive) . "\n");

                printf("  %s (%s) \n", basename($archive), human_bytes(filesize($archive)));
                printf("  root folder inside the zip: %s/ \n", $slug);
                printf("  sha256 %s \n", $hash);
            }
        }
    }

    echo "OK\n\n";
}

if ($exception) {
    echo "build failed \nplease check the log above\n\n";
    exit(1);
}

printf(
    "building pro version... OK\n%s, %s\n",
    DIST_DIR,
    human_bytes(dir_size(DIST_DIR))
);

if ($archive !== null) {
    echo $archive . "\n";
}

echo "\n";
