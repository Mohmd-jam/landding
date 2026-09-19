<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Gate for every admin endpoint. Requests that are not authenticated never
 * reach a controller, and the answer differs for the SPA (JSON 401) and for a
 * plain browser navigation (redirect to the login screen).
 */
final class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            throw HttpException::unauthorized('Your session has ended. Please sign in again.');
        }

        return Response::redirect('/admin/login?redirect=' . rawurlencode($request->fullPathWithQuery()));
    }
}
