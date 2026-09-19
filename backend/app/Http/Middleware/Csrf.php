<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use Closure;

/**
 * Cross-site request forgery protection for state-changing requests.
 *
 * The token is bound to the session and compared in constant time; GET/HEAD/
 * OPTIONS are exempt because they must never change state.
 */
final class Csrf
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if (!Security::verifyCsrf($request)) {
            throw HttpException::csrf();
        }

        return $next($request);
    }
}
