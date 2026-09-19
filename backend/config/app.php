<?php

/**
 * Application configuration.
 *
 * Every value can be overridden through the environment (.env) so the same
 * codebase runs in development, the sandbox preview and production.
 */

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Arash Mahdavi — Full-Stack Web Developer'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim(Env::get('APP_URL', ''), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'default_locale' => Env::get('APP_LOCALE', 'fa'),
    'fallback_locale' => Env::get('APP_FALLBACK_LOCALE', 'en'),
    'locales' => ['fa', 'en'],
    'key' => Env::get('APP_KEY', 'insecure-development-key-change-me'),

    // Paths
    'base_path' => dirname(__DIR__, 2),
    'public_path' => dirname(__DIR__, 2) . '/public',
    'storage_path' => dirname(__DIR__, 2) . '/storage',
    'uploads_path' => dirname(__DIR__, 2) . '/public/uploads',
    'uploads_url' => '/uploads',

    // Frontend build output (Vite) — served as the SPA shell
    'spa_shell' => dirname(__DIR__, 2) . '/public/app/index.html',
    'admin_shell' => dirname(__DIR__, 2) . '/public/app/admin.html',

    'admin_path' => 'admin',
    'asset_version' => Env::get('ASSET_VERSION', ''),

    // Pagination defaults
    'per_page' => 9,
    'admin_per_page' => 15,

    'log_level' => Env::get('LOG_LEVEL', 'warning'),
];
