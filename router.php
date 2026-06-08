<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/php_app/public' . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/php_app/public/index.php';
