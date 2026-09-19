<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Marks API requests so errors are always rendered as JSON, even when the
 * client forgot the Accept header.
 */
final class ForceJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('wants_json', true);

        return $next($request)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
