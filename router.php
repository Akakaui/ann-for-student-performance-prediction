<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/php_app/public' . $uri;

// Serve static files directly
if ($uri !== '/' && is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf'])) {
        return false;
    }
}

// Serve actual PHP files
if ($uri !== '/' && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    return true;
}

// Root -> index.php
if ($uri === '/') {
    require __DIR__ . '/php_app/public/index.php';
    return true;
}

return false;
