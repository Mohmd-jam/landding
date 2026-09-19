<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Capability check on top of AdminAuth (super_admin → everything, admin →
 * content/media/messages/seo, editor → content/media). Enforced server-side:
 * hiding a button in the UI is never the authorisation.
 */
final class Role
{
    public function __construct(private string $capability = 'content')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        Auth::require($this->capability);

        return $next($request);
    }
}
