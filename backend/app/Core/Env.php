<?php

declare(strict_types=1);

namespace App\Core;

/**
 * .env loader.
 *
 * Reads the project .env once, supports quotes, comments and the usual
 * scalar casts, and never overwrites variables that are already defined
 * (so real environment variables win in production/Docker/CI).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $value = trim($value);

            // Strip surrounding quotes and unescape doubled quotes.
            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
                $quote = $value[0];
                $value = substr($value, 1, -1);
                $value = $quote === '"' ? stripcslashes($value) : str_replace("\\'", "'", $value);
            } elseif (str_contains($value, ' #')) {
                $value = rtrim(substr($value, 0, (int) strpos($value, ' #')));
            }

            self::$values[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureLoaded();

        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return is_string($default) ? (string) $value : self::cast($value);
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    public static function has(string $key): bool
    {
        return self::get($key, null) !== null;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        self::ensureLoaded();

        return self::$values;
    }

    private static function cast(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    private static function ensureLoaded(): void
    {
        if (!self::$loaded) {
            self::load(dirname(__DIR__, 3) . '/.env');
        }
    }
}
