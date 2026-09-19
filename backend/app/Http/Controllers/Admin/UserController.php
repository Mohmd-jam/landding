<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Validator;
use App\Http\Controllers\Controller;
use App\Models\Admin;

/**
 * Admin accounts. Only a super admin reaches these routes (enforced in the
 * route middleware and again here): creating users, changing roles, disabling
 * accounts and resetting passwords — never exposing a password hash.
 */
final class UserController extends Controller
{
    private const ROLES = ['super_admin', 'admin', 'editor'];

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request, 15, 100);
        $page = $this->page($request);

        $result = Admin::listPaginated([
            'search' => trim((string) $request->query('search', '')),
            'role' => (string) $request->query('role', ''),
        ], $perPage, $page);

        // QueryBuilder::paginate() returns {data,total,per_page,current_page,last_page}.
        $items = [];

        foreach ($result['data'] as $user) {
            $items[] = Admin::safe($user) + [
                'sessions' => (int) Database::table('admin_sessions')->where('admin_id', (int) $user['id'])->count(),
            ];
        }

        return Response::json([
            'data' => [
                'items' => $items,
                'meta' => [
                    'page' => (int) $result['current_page'],
                    'per_page' => (int) $result['per_page'],
                    'total' => (int) $result['total'],
                    'total_pages' => (int) $result['last_page'],
                ],
                'roles' => self::ROLES,
                'capabilities' => [
                    'super_admin' => ['content', 'media', 'messages', 'seo'],
                    'admin' => ['content', 'media', 'messages', 'seo'],
                    'editor' => ['content', 'media'],
                ],
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $input = Validator::make($request->body(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'role' => ['required', 'in:' . implode(',', self::ROLES)],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_media_id' => ['nullable', 'int', 'exists:media,id'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $problems = Security::passwordIssues((string) $input['password']);

        if ($problems !== []) {
            throw HttpException::validation('Choose a stronger password.', [
                'password' => array_map(static fn (string $problem): string => ucfirst($problem) . '.', $problems),
            ]);
        }

        $id = Admin::create([
            'name' => (string) $input['name'],
            'email' => mb_strtolower((string) $input['email']),
            'password_hash' => Security::hashPassword((string) $input['password']),
            'role' => (string) $input['role'],
            'bio' => $input['bio'] ?? null,
            'avatar_media_id' => $input['avatar_media_id'] ?? null,
            'is_active' => (int) ($input['is_active'] ?? true),
            'must_change_password' => 0,
        ]);

        AuditLogger::log(Auth::id(), 'admin.create', 'admins', $id, [
            'email' => $input['email'],
            'role' => $input['role'],
        ]);

        return Response::json([
            'data' => Admin::safe(Database::table('admins')->where('id', $id)->first() ?? []),
            'message' => 'Admin account created.',
        ], 201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $user = Database::table('admins')->where('id', $id)->first();

        if ($user === null) {
            throw HttpException::notFound('Admin account not found.');
        }

        $input = Validator::make($request->body(), [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190'],
            'role' => ['sometimes', 'in:' . implode(',', self::ROLES)],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_media_id' => ['nullable', 'int', 'exists:media,id'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:10', 'max:200'],
        ])->validate();

        $update = ['updated_at' => now_utc()];

        foreach (['name', 'bio', 'avatar_media_id'] as $field) {
            if (array_key_exists($field, $input)) {
                $update[$field] = $input[$field];
            }
        }

        if (array_key_exists('email', $input)) {
            $email = mb_strtolower((string) $input['email']);
            $collision = Admin::findByEmail($email);

            if ($collision !== null && (int) $collision['id'] !== $id) {
                throw HttpException::validation('That e-mail is already in use.', ['email' => ['E-mail already registered.']]);
            }

            $update['email'] = $email;
        }

        if (array_key_exists('role', $input)) {
            $this->assertRoleChangeAllowed($id, (string) $input['role']);
            $update['role'] = (string) $input['role'];
        }

        if (array_key_exists('is_active', $input)) {
            if ($id === (int) Auth::id() && !(bool) $input['is_active']) {
                throw HttpException::badRequest('You cannot disable your own account.');
            }

            $update['is_active'] = (int) (bool) $input['is_active'];

            if (!$update['is_active']) {
                Admin::deleteSessionsFor($id);
            }
        }

        if (!empty($input['password'])) {
            $problems = Security::passwordIssues((string) $input['password']);

            if ($problems !== []) {
                throw HttpException::validation('Choose a stronger password.', ['password' => $problems]);
            }

            $update['password_hash'] = Security::hashPassword((string) $input['password']);
            $update['must_change_password'] = 1;
            $update['failed_attempts'] = 0;
            $update['locked_until'] = null;
            Admin::deleteSessionsFor($id);
        }

        Database::table('admins')->where('id', $id)->update($update);

        AuditLogger::log(Auth::id(), 'admin.update', 'admins', $id, array_keys($update));

        return Response::json([
            'data' => Admin::safe(Database::table('admins')->where('id', $id)->first() ?? $user),
            'message' => 'Admin account saved.',
        ]);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');

        if ($id === (int) Auth::id()) {
            throw HttpException::badRequest('You cannot delete the account you are signed in with.');
        }

        $user = Database::table('admins')->where('id', $id)->first();

        if ($user === null) {
            throw HttpException::notFound('Admin account not found.');
        }

        if ((string) $user['role'] === 'super_admin') {
            $others = (int) Database::table('admins')->where('role', 'super_admin')->where('id', '!=', $id)->count();

            if ($others === 0) {
                throw HttpException::badRequest('At least one super admin must remain.');
            }
        }

        Admin::deleteSessionsFor($id);
        Database::table('admins')->where('id', $id)->delete();

        AuditLogger::log(Auth::id(), 'admin.delete', 'admins', $id, ['email' => $user['email']]);

        return Response::json(['data' => ['id' => $id], 'message' => 'Admin account deleted.']);
    }

    /** No one can demote the last super admin (that would lock the panel). */
    private function assertRoleChangeAllowed(int $id, string $role): void
    {
        $user = Database::table('admins')->where('id', $id)->first();

        if ($user === null || (string) $user['role'] !== 'super_admin' || $role === 'super_admin') {
            return;
        }

        $others = (int) Database::table('admins')->where('role', 'super_admin')->where('id', '!=', $id)->count();

        if ($others === 0) {
            throw HttpException::badRequest('At least one super admin must remain.');
        }
    }
}
