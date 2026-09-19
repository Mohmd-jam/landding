<?php
/**
 * JamSoft — default configuration.
 * Copy this file to app/config.local.php and override what you need
 * (config.local.php is git-ignored).
 */
$config = [
    'app' => [
        'name'        => 'JamSoft',
        'url'         => '',          // e.g. https://jamsoft.ir (leave empty for auto)
        'debug'       => false,
        'default_lang'=> 'fa',
        'langs'       => ['fa', 'en'],
        'timezone'    => 'Asia/Tehran',
    ],

    // Database — production: MySQL / MariaDB.
    // For local preview without a MySQL server set driver => 'sqlite'.
    'db' => [
        'driver'   => 'mysql',       // mysql | sqlite
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'jamsoft',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
        'sqlite'   => __DIR__ . '/../storage/jamsoft.sqlite',
    ],

    'upload' => [
        'dir'     => __DIR__ . '/../public/uploads',
        'url'     => '/uploads',
        'max_mb'  => 5,
        'allowed' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
    ],
];

if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    $config = array_replace_recursive($config, $local);
}

return $config;
