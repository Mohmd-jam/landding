<?php
// Dev router for `php -S 0.0.0.0:8080 -t public public/router.php`
$file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($file !== __DIR__ . '/' && is_file($file)) return false;
require __DIR__ . '/index.php';
