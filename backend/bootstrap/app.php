<?php

declare(strict_types=1);

/**
 * Application bootstrap: autoloading, environment, configuration and error
 * handling. Required by the front controller (public/index.php) and by the
 * CLI bridge (tools/cli.mjs → backend/database/install.php).
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\Kernel;
use App\Core\Logger;
use App\Core\Router;

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', dirname(__DIR__, 2));
}

$composerAutoload = APP_BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    // App\Core\Router      → backend/app/Core/Router.php
    // Database\Migrator    → backend/database/Migrator.php
    $map = [
        'App\\' => __DIR__ . '/../app/',
        'Database\\' => __DIR__ . '/../database/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = $baseDir . $relative . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

require_once __DIR__ . '/../app/Support/helpers.php';

Env::load(APP_BASE_PATH . '/.env');
Config::load(__DIR__ . '/../config');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'C');

/**
 * Error handling — errors become exceptions, exceptions are logged, and the
 * browser never sees a stack trace unless APP_DEBUG is on.
 */
error_reporting(E_ALL);
ini_set('display_errors', Config::get('app.debug', false) ? '1' : '0');
ini_set('log_errors', '0');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    Logger::exception($e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'error' => [
            'code' => 500,
            'message' => Config::get('app.debug')
                ? $e->getMessage()
                : 'Unhandled application error.',
        ],
    ], JSON_UNESCAPED_UNICODE);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error('Fatal error', ['message' => $error['message'], 'file' => $error['file'], 'line' => $error['line']]);
    }
});

/** Load the route files into a router instance. */
$loadRoutes = static function (Router $router): Router {
    foreach (['web.php', 'api.php', 'admin.php'] as $file) {
        $path = __DIR__ . '/../routes/' . $file;

        if (is_file($path)) {
            (static function (Router $router) use ($path): void {
                require $path;
            })($router);
        }
    }

    return $router;
};

/** Build the application kernel (used by the front controller). */
$createKernel = static function () use ($loadRoutes): Kernel {
    return new Kernel($loadRoutes(new Router()));
};

return [
    'kernel' => $createKernel(),
    'createKernel' => $createKernel,
    'router' => static function () use ($loadRoutes): Router {
        return $loadRoutes(new Router());
    },
];
