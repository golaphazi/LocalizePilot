<?php
/**
 * Shared build helpers.
 *
 * This file is identical in every Next3 addon's build-package. Fix it in one
 * place and copy it to the others.
 */

/**
 * Should this path be skipped while copying, given the config's remove list?
 *
 * An entry with no slash matches by file name anywhere in the tree, which is
 * how '.DS_Store' is meant to behave. An entry with a slash matches that one
 * path relative to the plugin root. Entries containing a glob character are
 * left alone here and resolved by remove.php against the copied tree.
 *
 * @param string   $relative Path relative to the plugin root, forward slashes.
 * @param string[] $remove   The config's remove list.
 * @return bool
 */
function is_excluded($relative, array $remove) {
    $name = basename($relative);

    foreach ($remove as $entry) {
        $entry = trim(str_replace('\\', '/', $entry), '/');

        if ($entry === '' || strpbrk($entry, '*?[') !== false) {
            continue; // A glob: remove.php deals with it.
        }

        if (strpos($entry, '/') === false) {
            if ($entry === $name) {
                return true;
            }
            continue;
        }

        if ($entry === $relative) {
            return true;
        }
    }

    return false;
}

/**
 * Recursively copy $source to $dest, skipping anything the remove list covers.
 *
 * Skipping during the copy rather than deleting afterwards matters most for
 * .git, which in these addons is usually larger than the plugin itself.
 *
 * @param string      $source      Directory or file to copy.
 * @param string      $dest        Where it goes.
 * @param string[]    $remove      The config's remove list.
 * @param string|null $base        Copy root, used to work out relative paths.
 * @param int         $permissions Mode for directories this creates.
 * @return int Number of files copied.
 */
function xcopy($source, $dest, array $remove = [], $base = null, $permissions = 0755) {
    $base  = $base === null ? $source : $base;
    $count = 0;

    $relative = ltrim(str_replace('\\', '/', substr($source, strlen($base))), '/');

    if ($relative !== '' && is_excluded($relative, $remove)) {
        echo "  skipping '$relative' \n";
        return 0;
    }

    if (is_link($source)) {
        symlink(readlink($source), $dest);
        return 1;
    }

    if (is_file($source)) {
        return copy($source, $dest) ? 1 : 0;
    }

    if (!is_dir($dest) && !mkdir($dest, $permissions, true) && !is_dir($dest)) {
        echo "  failed to create '$dest' \n";
        return 0;
    }

    $dir = dir($source);

    while (false !== $entry = $dir->read()) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $count += xcopy("$source/$entry", "$dest/$entry", $remove, $base, $permissions);
    }

    $dir->close();

    return $count;
}

/**
 * Delete a file, or a directory and everything under it.
 *
 * @param string $dir   Absolute path.
 * @param bool   $print Log what went.
 * @return void
 */
function recursiveRemove($dir, $print = true) {
    if (is_file($dir) || is_link($dir)) {
        unlink($dir);

        if ($print) {
            echo "  deleting '" . dist_relative($dir) . "' \n";
        }

        return;
    }

    if (!is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
        $todo($fileinfo->getPathname());
    }

    rmdir($dir);

    if ($print) {
        echo "  deleting '" . dist_relative($dir) . "/' \n";
    }
}

/**
 * Turn an absolute path inside dist back into something readable in the log.
 *
 * @param string $path Absolute path.
 * @return string
 */
function dist_relative($path) {
    $path = str_replace('\\', '/', $path);
    $dist = str_replace('\\', '/', rtrim(DIST_DIR, '/\\')) . '/';

    return strpos($path, $dist) === 0 ? substr($path, strlen($dist)) : $path;
}

/**
 * Resolve one remove entry to the paths it actually matches inside dist.
 *
 * Plain entries resolve to themselves; entries with a glob character are
 * expanded, which is what lets a config drop a cache directory's contents
 * without naming every file by hand.
 *
 * An entry ending in '/*' is the whole contents of that directory, dot files
 * included, with the directory left in place. glob() alone will not do that:
 * '*' never matches a leading dot, so a file named '.json' would survive.
 *
 * @param string $entry Path relative to the plugin root, possibly a glob.
 * @return string[] Absolute paths.
 */
function resolve_targets($entry) {
    $entry  = ltrim(str_replace('\\', '/', $entry), '/');
    $target = DIST_DIR . $entry;

    if (substr($entry, -2) === '/*') {
        $dir = DIST_DIR . substr($entry, 0, -2);

        if (!is_dir($dir)) {
            return [];
        }

        $paths = [];

        foreach (scandir($dir) as $name) {
            if ($name !== '.' && $name !== '..') {
                $paths[] = $dir . '/' . $name;
            }
        }

        return $paths;
    }

    if (strpbrk($entry, '*?[') !== false) {
        $found = glob($target, GLOB_BRACE);

        return $found ? $found : [];
    }

    return file_exists($target) ? [$target] : [];
}

/**
 * Total size of a directory in bytes.
 *
 * @param string $dir Absolute path.
 * @return int
 */
function dir_size($dir) {
    if (!is_dir($dir)) {
        return 0;
    }

    $bytes = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->isFile()) {
            $bytes += $file->getSize();
        }
    }

    return $bytes;
}

/**
 * The plugin's main file: the PHP file in the root carrying a Plugin Name header.
 *
 * @param string $dir Plugin root.
 * @return string|null Absolute path, or null when there is no such file.
 */
function plugin_main_file($dir) {
    foreach ((array) glob($dir . '/*.php') as $file) {
        $head = (string) file_get_contents($file, false, null, 0, 8192);

        if (preg_match('/^[ \t\/*#@]*Plugin Name:/mi', $head)) {
            return $file;
        }
    }

    return null;
}

/**
 * The Version header of a plugin file.
 *
 * @param string $file Absolute path to the main plugin file.
 * @return string Version, or '' when the header is missing.
 */
function plugin_version($file) {
    $head = (string) file_get_contents($file, false, null, 0, 8192);

    if (preg_match('/^[ \t\/*#@]*Version:\s*(.+)$/mi', $head, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

/**
 * Convert a timestamp to the DOS date and time a zip entry stores.
 *
 * @param int $timestamp
 * @return array{0:int,1:int} DOS time, DOS date.
 */
function dos_datetime($timestamp) {
    $parts = getdate($timestamp);

    if ($parts['year'] < 1980) {
        return [0, 33]; // 1980-01-01, the earliest the format can hold.
    }

    return [
        ($parts['hours'] << 11) | ($parts['minutes'] << 5) | intdiv($parts['seconds'], 2),
        (($parts['year'] - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'],
    ];
}

/**
 * Zip a directory, with every path inside one top level folder.
 *
 * That wrapping folder is what WordPress names the plugin directory when the
 * archive is installed, so it has to be the plugin's real slug and not the
 * name of the build output directory.
 *
 * Written by hand rather than with ZipArchive: ext-zip is off in a stock
 * Laragon php.ini, and a release build should not depend on the developer
 * having edited it. Falls back to ZipArchive when it is available, since that
 * is the better tested path.
 *
 * @param string $dir     Directory to archive.
 * @param string $archive Zip file to write.
 * @param string $prefix  Top level folder name inside the archive.
 * @return bool
 */
function zip_dir($dir, $archive, $prefix) {
    $entries = [];
    $dir     = rtrim(str_replace('\\', '/', $dir), '/');
    $prefix  = trim($prefix, '/') . '/';

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $path     = str_replace('\\', '/', $item->getPathname());
        $relative = substr($path, strlen($dir) + 1);

        if ($relative === '') {
            continue;
        }

        // Directories are stored too, so an empty one survives the round trip.
        // next3-dropbox and next3-google-drive need includes/save/ to exist:
        // the plugin writes its listing cache straight into it.
        $entries[] = [
            'name' => $prefix . $relative . ($item->isDir() ? '/' : ''),
            'file' => $item->isDir() ? null : $path,
            'time' => $item->getMTime(),
        ];
    }

    sort($entries);

    if (class_exists('ZipArchive')) {
        return zip_write_ziparchive($entries, $archive);
    }

    return zip_write_native($entries, $archive);
}

/**
 * Write the archive with ext-zip.
 *
 * @param array  $entries
 * @param string $archive
 * @return bool
 */
function zip_write_ziparchive(array $entries, $archive) {
    $zip = new ZipArchive();

    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry['file'] === null) {
            $zip->addEmptyDir(rtrim($entry['name'], '/'));
            continue;
        }

        $zip->addFile($entry['file'], $entry['name']);
    }

    return $zip->close();
}

/**
 * Write the archive with nothing but zlib.
 *
 * Deflate for files, stored for directories, UTF-8 names, no zip64 - a plugin
 * build is nowhere near the 4 GB or 65,535 entry limits that would need it.
 *
 * @param array  $entries
 * @param string $archive
 * @return bool
 */
function zip_write_native(array $entries, $archive) {
    $handle = fopen($archive, 'wb');

    if (!$handle) {
        return false;
    }

    $central = '';
    $offset  = 0;

    foreach ($entries as $entry) {
        $name              = $entry['name'];
        $is_dir            = $entry['file'] === null;
        $raw               = $is_dir ? '' : (string) file_get_contents($entry['file']);
        $crc               = crc32($raw);
        $uncompressed      = strlen($raw);
        $method            = $is_dir ? 0 : 8;
        $data              = $is_dir ? '' : gzdeflate($raw, 9);

        // Deflate can grow tiny or already compressed payloads. Store those.
        if (!$is_dir && ($data === false || strlen($data) >= $uncompressed)) {
            $method = 0;
            $data   = $raw;
        }

        $compressed = strlen($data);
        list($dos_time, $dos_date) = dos_datetime($entry['time']);

        // 0x0800 marks the file name as UTF-8.
        $local = pack('V', 0x04034b50)
            . pack('v', 20) . pack('v', 0x0800) . pack('v', $method)
            . pack('v', $dos_time) . pack('v', $dos_date)
            . pack('V', $crc) . pack('V', $compressed) . pack('V', $uncompressed)
            . pack('v', strlen($name)) . pack('v', 0)
            . $name;

        fwrite($handle, $local . $data);

        $central .= pack('V', 0x02014b50)
            . pack('v', 20) . pack('v', 20) . pack('v', 0x0800) . pack('v', $method)
            . pack('v', $dos_time) . pack('v', $dos_date)
            . pack('V', $crc) . pack('V', $compressed) . pack('V', $uncompressed)
            . pack('v', strlen($name)) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0)
            . pack('V', $is_dir ? 0x10 : 0)
            . pack('V', $offset)
            . $name;

        $offset += strlen($local) + $compressed;
    }

    $count = count($entries);

    fwrite(
        $handle,
        $central
        . pack('V', 0x06054b50)
        . pack('v', 0) . pack('v', 0)
        . pack('v', $count) . pack('v', $count)
        . pack('V', strlen($central)) . pack('V', $offset)
        . pack('v', 0)
    );

    return fclose($handle);
}

/**
 * Bytes as something readable at a glance.
 *
 * @param int $bytes
 * @return string
 */
function human_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $i ? 1 : 0) . ' ' . $units[$i];
}

// JavaScript Minifier
function minify_js($input) {
    if(trim($input) === "") return $input;
    return preg_replace(
        array(
            // Remove comment(s)
            '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
            // Remove white-space(s) outside the string and regex
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
            // Remove the last semicolon
            '#;+\}#',
            // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
            '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
            // --ibid. From `foo['bar']` to `foo.bar`
            '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
        ),
        array(
            '$1',
            '$1$2',
            '}',
            '$1$3',
            '$1.$3'
        ),
    $input);
}

/**
 * Swap every calc() expression for a placeholder the minifier will not touch.
 *
 * Two of the rules below are actively harmful inside calc(): the whitespace
 * rule eats the space in front of a '+' or '-', and the zero-unit rule turns
 * '0px' into '0'. Either one makes the expression invalid, and a browser then
 * throws the whole declaration away - so a border or a width simply vanishes
 * from the built stylesheet with nothing in the log to say so.
 *
 * Scanned rather than matched with a regex so a nested calc() survives.
 *
 * @param string $css
 * @param array  $store Filled with the extracted expressions, keyed by placeholder.
 * @return string
 */
function protect_calc($css, array &$store) {
    $out    = '';
    $cursor = 0;
    $length = strlen($css);

    while ($cursor < $length) {
        $start = stripos($css, 'calc(', $cursor);

        if ($start === false) {
            $out .= substr($css, $cursor);
            break;
        }

        $out .= substr($css, $cursor, $start - $cursor);

        $depth = 0;
        $end   = $start + 4; // The '(' of 'calc('.

        for (; $end < $length; $end++) {
            if ($css[$end] === '(') {
                $depth++;
            } elseif ($css[$end] === ')') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            }
        }

        if ($depth !== 0) {
            // Unbalanced parentheses: leave the rest of the file alone.
            $out .= substr($css, $start);
            break;
        }

        $key           = '__CALC' . count($store) . '__';
        $store[$key]   = substr($css, $start, $end - $start + 1);
        $out          .= $key;
        $cursor        = $end + 1;
    }

    return $out;
}

// CSS Minifier => http://ideone.com/Q5USEF + improvement(s)
function minify_css($input) {
    if(trim($input) === "") return $input;

    $calc  = [];
    $input = protect_calc($input, $calc);

    $output = preg_replace(
        array(
            // Remove comment(s)
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
            // Remove unused white-space(s)
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
            '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
            // Replace `:0 0 0 0` with `:0`
            '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
            // Replace `background-position:0` with `background-position:0 0`
            '#(background-position):0(?=[;\}])#si',
            // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
            '#(?<=[\s:,\-])0+\.(\d+)#s',
            // Minify string value
            '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
            '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
            /*
             * Shorten a six digit hex colour to three: #aabbcc -> #abc.
             *
             * Two fixes to the version this came from. The original class was
             * [a-f0-6], a typo that skipped 7, 8 and 9. And with no boundary
             * at the end it also chewed on eight digit colours that carry an
             * alpha channel - #33333345 came out as #33345, which is not a
             * colour, so the browser dropped the declaration holding it. The
             * lookahead stops the match when more hex digits follow.
             */
            '#(?<=[\s:,\-]\#)([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3(?![a-f0-9])#i',
            // Replace `(border|outline):none` with `(border|outline):0`
            '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
            // Remove empty selector(s)
            '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
        ),
        array(
            '$1',
            '$1$2$3$4$5$6$7',
            '$1',
            ':0',
            '$1:0 0',
            '.$1',
            '$1$3',
            '$1$2$4$5',
            '$1$2$3',
            '$1:0',
            '$1$2'
        ),
    $input);

    return $calc ? strtr($output, $calc) : $output;
}
