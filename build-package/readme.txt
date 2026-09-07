Build package
=============

Same system next3-offload/build-package uses, one copy per addon so it travels
with the plugin's own repository.

requirements:
    php version: 7.1+ (cli)
    execution time: 100+
    memory: 128mb

commands:
    php build.php
        Copies the plugin to a sibling <folder>dist/ directory, minifies the
        files config.php lists, deletes the junk config.php lists, removes
        build-package from the copy, and writes an installable zip beside it.
        Exits 1 if anything failed.

        Run it from inside build-package:
            cd build-package
            php build.php

what to edit:
    config.php is the only file that differs between addons. The other four are
    identical everywhere - fix one, copy it across.

    zip
        true to write <slug>-<version>.zip and a .sha256 next to the dist
        directory. The version is read from the Version header of the plugin's
        main file, so bumping the header is all a release needs.

    slug
        The folder name inside the zip, which is the directory WordPress
        creates when the archive is installed. Defaults to the plugin's own
        folder name - set it only when those differ.

    compressJs / compressCss
        Files to minify in place inside dist. Paths are relative to the plugin
        root. Source files are never touched.

    compressPhp
        Sent to php-minify.com. Empty in every addon; only next3-offload uses
        it, for its licence checks.

    remove
        Paths to keep out of the build. An entry with no slash ('.DS_Store')
        matches that file name anywhere in the tree. An entry with a slash
        ('assets/script/script.js') matches that exact path. An entry with a
        glob ('includes/save/*.json') is expanded against the copied tree.

        Plain entries are skipped during the copy, so something large like .git
        is never written to disk at all.

notes:
    - The dist directory is deleted and rebuilt on every run.
    - The zip is written with zlib directly, so it works whether or not
      ext-zip is enabled in php.ini.
    - build-package is removed from dist automatically; do not list it.
