<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Http\Controllers\Controller;

/**
 * Admin session endpoints.
 *
 * `GET /session` starts the PHP session and returns the CSRF token the SPA must
 * send with every write, which is why the login form itself is already CSRF
 * protected. Login failures return a generic message and are throttled; success
 * rotates the session id, records the attempt and writes an audit entry.
 */
final class AuthController extends Controller
{
    public function session(Request $request): Response
    {
        $unused = $request;

        return Response::json([
            'data' => [
                'authenticated' => Auth::check(),
                'csrf_token' => Security::csrfToken(),
                'admin' => ($user = Auth::user()) === null ? null
                    : \App\Models\Admin::safe($user) + ['capabilities' => array_values(array_filter(
                        ['content', 'media', 'messages', 'seo'],
                        static fn (string $capability): bool => Auth::can($capability)
                    ))],
                'locale' => $this->locales()->defaultCode(),
                'locales' => $this->locales()->publicList(),
            ],
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function login(Request $request): Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max' => 190],
            'password' => ['required', 'string', 'min' => 6, 'max' => 200],
            'remember' => ['nullable', 'boolean'],
        ]);

        $result = Auth::attempt(
            (string) $credentials['email'],
            (string) $credentials['password'],
            (bool) ($credentials['remember'] ?? false),
            $request->ip(),
            $request->userAgent()
        );

        if (!($result['ok'] ?? false)) {
            AuditLogger::log(null, 'admin.login.failed', 'admins', null, [
                'email' => mb_strtolower((string) $credentials['email']),
                'reason' => $result['error'] ?? 'invalid credentials',
            ]);

            throw new HttpException(401, (string) ($result['error'] ?? 'Unable to sign in.'));
        }

        /** @var array<string,mixed> $user */
        $user = $result['user'];

        AuditLogger::log((int) $user['id'], 'admin.login', 'admins', (int) $user['id'], [
            'ip' => $request->ip(),
            'agent' => mb_substr($request->userAgent(), 0, 120),
        ]);

        return Response::json([
            'data' => [
                'admin' => $user + ['capabilities' => array_values(array_filter(
                    ['content', 'media', 'messages', 'seo'],
                    static fn (string $capability): bool => Auth::can($capability)
                ))],
                'csrf_token' => Security::csrfToken(),
                'redirect' => (string) $request->query('redirect', '/admin'),
            ],
            'message' => 'Welcome back, ' . (string) $user['name'] . '.',
        ]);
    }

    public function me(Request $request): Response
    {
        $unused = $request;

        return Response::json([
            'data' => [
                'admin' => \App\Models\Admin::safe(Auth::user() ?? []),
                'capabilities' => array_values(array_filter(
                    ['content', 'media', 'messages', 'seo'],
                    static fn (string $capability): bool => Auth::can($capability)
                )),
                'session' => [
                    'idle_timeout' => (int) \App\Core\Config::get('security.session.idle_timeout', 7200),
                    'csrf_token' => Security::csrfToken(),
                ],
            ],
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function logout(Request $request): Response
    {
        $id = Auth::id();
        Auth::logout();

        if ($id !== null) {
            AuditLogger::log($id, 'admin.logout', 'admins', $id, ['ip' => $request->ip()]);
        }

        return Response::json(['message' => 'You have been signed out.']);
    }

    /** Change the signed-in admin's own password (requires the current one). */
    public function password(Request $request): Response
    {
        $input = $request->validate([
            'current_password' => ['required', 'string', 'max' => 200],
            'password' => ['required', 'string', 'min' => 10, 'max' => 200, 'confirmed'],
        ]);

        $id = (int) Auth::id();

        try {
            Auth::changePassword($id, (string) $input['current_password'], (string) $input['password']);
        } catch (HttpException $e) {
            throw $e;
        }

        AuditLogger::log($id, 'admin.password.changed', 'admins', $id, ['ip' => $request->ip()]);

        return Response::json(['message' => 'Password updated. Other sessions stay signed in until they expire.']);
    }
}
