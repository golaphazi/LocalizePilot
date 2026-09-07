<?php
/**
 * Delete everything config.php's remove list covers.
 *
 * Most plain entries are already gone: xcopy() skipped them rather than copy
 * them in the first place. What is left for this pass is the globs, and any
 * path that only exists once the tree has been assembled.
 *
 * Included by build.php, which has already defined DIST_DIR and $exception.
 */

echo "start deleting junk files & directories... \n";

if (isset($config->remove)) {
    foreach ($config->remove as $entry) {
        foreach (resolve_targets($entry) as $target) {
            recursiveRemove($target);
        }
    }
}

echo "OK \n\n";

/*
 * Widgets a free build must not carry. Kept for parity with next3-offload's
 * build-package; none of the addons ship a widgets directory, so this normally
 * does nothing.
 */
if (isset($config->pro_widgets)) {
    echo "start pro widgets... \n";

    foreach ($config->pro_widgets as $target) {
        $file = DIST_DIR . 'widgets/' . $target . '/' . $target . '.php';

        if (file_exists($file)) {
            recursiveRemove($file);
        }
    }

    echo "OK \n\n";
}
