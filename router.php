<?php
/**
 * Router for `php -S`, used only by bin/setup-dev.sh for local preview.
 * WordPress isn't tracked in git — see bin/setup-dev.sh and .gitignore.
 */
$root = __DIR__;
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $root . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

chdir($root);
require $root . '/index.php';
