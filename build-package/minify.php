<?php
/**
 * Minify the files config.php lists, in place inside dist.
 *
 * Included by build.php, which has already defined DIST_DIR and $exception.
 */

echo "start minifying files... \n";

$saved = 0;

/**
 * Minify one file in dist with a callable, and report what it saved.
 *
 * A file that is already empty is left alone rather than treated as a failure:
 * an empty stylesheet is a real thing to ship, and the old build stopped on it.
 *
 * @param string   $file      Path relative to the plugin root.
 * @param callable $minifier  minify_js() or minify_css().
 * @return int Bytes saved.
 */
$minify_file = function ($file, callable $minifier) use (&$exception) {
    $path = DIST_DIR . $file;

    if (!file_exists($path)) {
        echo "  '$file' not found \n";
        $exception = true;
        return 0;
    }

    $before = file_get_contents($path);

    if (trim($before) === '') {
        echo "  '$file' is empty, nothing to do \n";
        return 0;
    }

    $after = $minifier($before);

    if (trim((string) $after) === '') {
        echo "  '$file' failed - the minifier returned nothing \n";
        $exception = true;
        return 0;
    }

    file_put_contents($path, $after);

    $saved = strlen($before) - strlen($after);

    printf(
        "  '%s' %s -> %s (%s saved) \n",
        $file,
        human_bytes(strlen($before)),
        human_bytes(strlen($after)),
        human_bytes(max(0, $saved))
    );

    return max(0, $saved);
};

foreach ($config->compressJs as $file) {
    $saved += $minify_file($file, 'minify_js');
}

foreach ($config->compressCss as $file) {
    $saved += $minify_file($file, 'minify_css');
}

/*
 * PHP minification goes through php-minify.com, the same service next3-offload
 * uses for its licence files. None of the addons list anything here, so the
 * loop normally does not run at all. If a future addon needs it and the request
 * fails on a certificate, that is the SSL option below - flip it knowing what
 * you are turning off.
 */
foreach ($config->compressPhp as $file) {
    echo "  minifying '$file' \n";

    $path = DIST_DIR . $file;

    if (!file_exists($path)) {
        echo "  '$file' not found \n";
        $exception = true;
        continue;
    }

    $before = file_get_contents($path);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://php-minify.com/index.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['sourceCode' => $before]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    $after = curl_exec($ch);
    $error = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($error !== null || trim((string) $after) === '') {
        echo '  failed - curl error: ' . ($error ? $error : 'empty response') . " \n";
        $exception = true;
        continue;
    }

    // The service sometimes prefixes a stray "1" to the returned source.
    if (substr($after, 0, 1) === '1' && substr($after, 1, 5) === '<?php') {
        $after = substr($after, 1);
    }

    file_put_contents($path, $after);
    $saved += max(0, strlen($before) - strlen($after));
}

if ($saved > 0) {
    echo '  ' . human_bytes($saved) . " saved in total \n";
}

echo "OK \n\n";
