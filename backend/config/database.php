<?php

/**
 * Database configuration.
 *
 * Two drivers are supported behind one query builder:
 *  - mysql  : production target (MySQL 8 / InnoDB, utf8mb4)
 *  - sqlite : local development + sandbox preview (zero-dependency)
 *
 * Switching drivers requires no code change — the schema is emitted in the
 * matching dialect by backend/database/Migrator.php.
 */

use App\Core\Env;

return [
    'driver' => Env::get('DB_DRIVER', 'mysql'),

    'mysql' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'portfolio_cms'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => 'InnoDB',
    ],

    'sqlite' => [
        'database' => Env::get('DB_SQLITE_PATH', dirname(__DIR__, 2) . '/storage/database.sqlite'),
    ],

    'options' => [
        'persistent' => false,
        'timeout' => 5,
    ],
];
