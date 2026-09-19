<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Fixed-window rate limiting (see config/security.php → throttle).
 *
 * Buckets: public, contact, login, upload. The identifier mixes the client IP
 * with the authenticated admin id when present, so one noisy visitor cannot
 * exhaust the quota of everybody else.
 */
final class Throttle
{
    public function __construct(private string $bucket = 'public')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $identifier = $request->ip() . '|' . (Auth::id() ?? 'guest');
        $status = RateLimiter::check($this->bucket, $identifier);

        if (!$status['allowed']) {
            throw new \App\Core\HttpException(429, 'Too many requests. Please try again in ' . $status['retry_after'] . ' seconds.', [
                'Retry-After' => (string) $status['retry_after'],
            ]);
        }

        RateLimiter::hit($this->bucket, $identifier);

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $status['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $status['remaining'] - 1));
    }
}
