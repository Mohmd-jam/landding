<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;
use App\Http\Controllers\Controller;

/**
 * Serves the admin SPA shell.
 *
 * The document is generated on every request (never cached) so the bundle can
 * be replaced without a stale HTML cache in front of it. The shell bootstraps
 * the session, the resolved locale and — for signed-in admins — the menu with
 * their role's capabilities; anonymous visitors get the login screen.
 */
final class AdminController extends Controller
{
    public function shell(Request $request): Response
    {
        $user = Auth::user();

        $html = View::spaShell([
            'lang' => 'en',
            'dir' => 'ltr',
            'theme' => 'dark',
            'title' => 'Admin — Portfolio CMS',
            'head' => '<meta name="robots" content="noindex,nofollow">'
                . '<meta name="color-scheme" content="dark">',
            'bootstrap' => [
                'kind' => 'admin',
                'path' => $request->path(),
                'authenticated' => $user !== null,
                'admin' => $user === null ? null : [
                    'id' => (int) $user['id'],
                    'name' => (string) $user['name'],
                    'email' => (string) $user['email'],
                    'role' => (string) $user['role'],
                    'avatar_url' => $user['avatar_url'] ?? null,
                    'capabilities' => array_values(array_filter(
                        ['content', 'media', 'messages', 'seo'],
                        static fn (string $capability): bool => Auth::can($capability)
                    )),
                ],
                'csrf_token' => Security::csrfToken(),
                'locales' => $this->locales()->publicList(),
                'api' => '/api/admin',
            ],
            'prerender' => '',
            'kind' => 'admin',
        ]);

        return Response::html($html)
            ->withHeader('Cache-Control', 'no-store, must-revalidate');
    }
}
