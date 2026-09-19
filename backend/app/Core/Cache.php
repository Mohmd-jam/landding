<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File cache used for settings, translations, navigation and dashboard
 * aggregates. Every entry stores its own expiry, so no cron job is required —
 * expired files are simply rewritten on the next read.
 */
final class Cache
{
    private static ?string $directory = null;

    public static function path(string $key): string
    {
        return self::directory() . '/' . preg_replace('/[^a-z0-9_.-]+/i', '_', $key) . '.json';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $path = self::path($key);

        if (!is_file($path)) {
            return $default;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return $default;
        }

        $payload = Json::decode($raw, null);

        if (!is_array($payload) || !array_key_exists('payload', $payload)) {
            return $default;
        }

        $expires = (int) ($payload['expires'] ?? 0);

        if ($expires !== 0 && $expires < time()) {
            @unlink($path);

            return $default;
        }

        return $payload['payload'];
    }

    public static function put(string $key, mixed $value, int $ttlSeconds = 3600): void
    {
        $payload = [
            'expires' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
            'payload' => $value,
        ];

        @file_put_contents(self::path($key), Json::encode($payload), LOCK_EX);
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Drop every cached entry (used by "Clear cache" in the admin panel). */
    public static function flush(): int
    {
        $removed = 0;

        foreach (glob(self::directory() . '/*.json') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key, '__cache_miss__');

        if ($value !== '__cache_miss__') {
            return $value;
        }

        $value = $callback();
        self::put($key, $value, $ttl);

        return $value;
    }

    /** Incrementing counter with its own window (used by the rate limiter). */
    public static function increment(string $key, int $ttlSeconds): int
    {
        $state = self::hitState($key);
        $now = time();

        if ($state['reset_at'] === 0 || $state['reset_at'] < $now) {
            $state = ['count' => 0, 'reset_at' => $now + $ttlSeconds];
        }

        $state['count']++;
        self::put($key, $state, max(1, $state['reset_at'] - $now));

        return $state['count'];
    }

    /** @return array{count:int,reset_at:int} */
    public static function hitState(string $key): array
    {
        $value = self::get($key, []);

        if (!is_array($value)) {
            return ['count' => 0, 'reset_at' => 0];
        }

        return [
            'count' => (int) ($value['count'] ?? 0),
            'reset_at' => (int) ($value['reset_at'] ?? 0),
        ];
    }

    private static function directory(): string
    {
        if (self::$directory === null) {
            $directory = rtrim((string) Config::get('app.storage_path', sys_get_temp_dir()), '/') . '/cache';

            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            self::$directory = $directory;
        }

        return self::$directory;
    }
}
