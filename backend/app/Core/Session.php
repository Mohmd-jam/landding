<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened session handling.
 *
 * Sessions are cookie-only, HttpOnly, SameSite=Lax and (in production) Secure.
 * The id is regenerated on privilege changes to defeat fixation, and the store
 * lives under storage/sessions so the application works in hosts that keep
 * /tmp volatile.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $config = (array) Config::get('security.session', []);
        $savePath = rtrim((string) Config::get('app.storage_path', sys_get_temp_dir()), '/') . '/sessions';

        if (!is_dir($savePath)) {
            @mkdir($savePath, 0775, true);
        }

        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) ($config['lifetime'] ?? 7200));
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '5');

        session_name((string) ($config['name'] ?? 'portfolio_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (string) ($config['cookie_path'] ?? '/'),
            'domain' => '',
            'secure' => (bool) ($config['secure'] ?? false),
            'httponly' => true,
            'samesite' => (string) ($config['same_site'] ?? 'Lax'),
        ]);

        if (PHP_SAPI !== 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
            @session_start();
        }

        self::$started = true;
    }

    public static function id(): string
    {
        self::start();

        return session_id() !== '' ? session_id() : '';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        self::start();

        return isset($_SESSION[$key]);
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        self::start();

        return $_SESSION ?? [];
    }

    public static function flash(string $key, mixed $value): void
    {
        self::put('_flash.' . $key, $value);
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = self::get('_flash.' . $key, $default);
        self::forget('_flash.' . $key);

        return $value;
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        self::start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            // Re-init the session with a fresh id (keeps flash data across the switch).
            @session_regenerate_id($deleteOld);
        }
    }

    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => (bool) $params['secure'],
                    'httponly' => (bool) $params['httponly'],
                    'samesite' => $params['samesite'] ?: 'Lax',
                ]);
            }

            @session_destroy();
        }

        self::$started = false;
    }

    public static function isStarted(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE;
    }
}
