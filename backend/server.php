<?php

ini_set('max_execution_time', '86400');
set_time_limit(0);

$publicPath = __DIR__ . '/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$filePath = $publicPath . $uri;

if ($uri !== '/' && is_file($filePath)) {
    $mimeTypes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'mjs'   => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'map'   => 'application/json',
    ];

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$ext] ?? mime_content_type($filePath) ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=31536000');
    readfile($filePath);
    exit;
}

require_once $publicPath . '/index.php';
