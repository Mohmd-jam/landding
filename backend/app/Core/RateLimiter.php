<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window rate limiter backed by the file cache.
 *
 * Used for the public API, the contact form and admin logins. Returns the
 * remaining budget so the API can expose X-RateLimit-* headers.
 */
final class RateLimiter
{
    public static function key(string $bucket, string $identifier): string
    {
        return 'throttle.' . $bucket . '.' . hash('sha256', $identifier);
    }

    /** @return array{allowed:bool,remaining:int,retry_after:int,limit:int} */
    public static function check(string $bucket, string $identifier, ?int $maxAttempts = null, ?int $decay = null): array
    {
        [$limit, $window] = self::config($bucket, $maxAttempts, $decay);
        $state = Cache::hitState(self::key($bucket, $identifier));
        $now = time();

        if ($state['reset_at'] !== 0 && $state['reset_at'] < $now) {
            $state = ['count' => 0, 'reset_at' => $now + $window];
        }

        $remaining = max(0, $limit - $state['count']);

        return [
            'allowed' => $state['count'] < $limit,
            'remaining' => $remaining,
            'retry_after' => max(0, ($state['reset_at'] ?: $now + $window) - $now),
            'limit' => $limit,
        ];
    }

    public static function hit(string $bucket, string $identifier): int
    {
        [$limit, $window] = self::config($bucket, null, null);

        return Cache::increment(self::key($bucket, $identifier), $window);
    }

    public static function clear(string $bucket, string $identifier): void
    {
        Cache::forget(self::key($bucket, $identifier));
    }

    /** Throws a 429 with a Retry-After header when the budget is exhausted. */
    public static function enforce(string $bucket, string $identifier, ?int $max = null, ?int $decay = null): void
    {
        $status = self::check($bucket, $identifier, $max, $decay);

        if (!$status['allowed']) {
            throw new HttpException(429, 'Too many requests. Please try again in ' . $status['retry_after'] . ' seconds.', [
                'Retry-After' => (string) $status['retry_after'],
                'X-RateLimit-Limit' => (string) $status['limit'],
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        self::hit($bucket, $identifier);
    }

    /** @return array{0:int,1:int} [limit, window seconds] */
    private static function config(string $bucket, ?int $maxAttempts, ?int $decay): array
    {
        $config = (array) Config::get('security.throttle.' . $bucket, []);

        return [
            $maxAttempts ?? (int) ($config['attempts'] ?? 60),
            $decay ?? (int) ($config['decay_seconds'] ?? 60),
        ];
    }
}
