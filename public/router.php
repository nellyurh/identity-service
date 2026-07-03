<?php
// php -S router: serve real files directly; everything else (incl. dotted paths like
// /.well-known/jwks.json, which php -S otherwise 404s itself) goes to Laravel.
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if ($uri !== '/' && is_file(__DIR__.$uri)) {
    return false;
}
require __DIR__.'/index.php';
