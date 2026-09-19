<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Json;
use App\Core\Security;
use App\Core\Str;

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escape for HTML output — always used in PHP-rendered views and SSR. */
    function e(mixed $value): string
    {
        return Security::escape($value);
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 120): string
    {
        return Str::limit((string) $value, $limit);
    }
}

if (!function_exists('json_attr')) {
    /** JSON payload safe to embed inside an HTML attribute. */
    function json_attr(mixed $value): string
    {
        return htmlspecialchars(Json::encode($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('now_utc')) {
    function now_utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        $base = rtrim((string) config('app.public_path', dirname(__DIR__, 3) . '/public'), '/');

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = rtrim((string) config('app.storage_path', dirname(__DIR__, 3) . '/storage'), '/');

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = rtrim((string) config('app.base_path', dirname(__DIR__, 3)), '/');

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_url')) {
    /** Absolute URL honouring the current request host (proxy/preview safe). */
    function app_url(string $path = ''): string
    {
        static $origin = null;

        if ($origin === null) {
            $configured = (string) config('app.url', '');

            if ($configured !== '') {
                $origin = rtrim($configured, '/');
            } else {
                $https = ($_SERVER['HTTPS'] ?? '') === 'on'
                    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                    || (string) config('app.force_https', '') === '1';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $origin = ($https ? 'https://' : 'http://') . $host;
            }
        }

        return $path === '' ? $origin : $origin . '/' . ltrim($path, '/');
    }
}

if (!function_exists('media_url')) {
    /** Resolve a stored media path into a public URL. */
    function media_url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim((string) config('uploads.url_prefix', '/uploads'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('locale_dir')) {
    function locale_dir(string $locale): string
    {
        return $locale === 'fa' || $locale === 'ar' || $locale === 'he' ? 'rtl' : 'ltr';
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $value = $array;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('format_date')) {
    /** Locale-aware short date (Gregorian, both locales; Persian uses jalali labels in the UI). */
    function format_date(?string $date, string $locale = 'en'): ?string
    {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }

        return $locale === 'fa'
            ? date('Y/m/d', $timestamp)
            : date('M Y', $timestamp);
    }
}
