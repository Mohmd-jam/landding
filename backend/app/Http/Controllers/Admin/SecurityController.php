<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\LocaleService;
use App\Services\SettingsService;
use App\Services\TranslationService;

/**
 * Security screen: active sessions (revocable one by one), login attempts,
 * audit trail, lockout state and cache maintenance.
 */
final class SecurityController extends Controller
{
    public function index(Request $request): Response
    {
        $unused = $request;
        $attempts = Admin::recentAttempts(120);

        $failed = [];
        $successful = [];

        foreach ($attempts as $attempt) {
            if ((int) ($attempt['successful'] ?? 0) === 1) {
                $successful[] = $attempt;
            } else {
                $failed[] = $attempt;
            }
        }

        $sessions = Admin::activeSessions();

        foreach ($sessions as $index => $session) {
            $sessions[$index]['is_current'] = (string) $session['id'] === Session::id();
        }

        $lockedAccounts = Database::select(
            'SELECT id, name, email, failed_attempts, locked_until FROM admins
             WHERE locked_until IS NOT NULL AND locked_until > ? ORDER BY locked_until DESC',
            [now_utc()]
        );

        return Response::json([
            'data' => [
                'sessions' => $sessions,
                'attempts' => [
                    'failed' => array_slice($failed, 0, 40),
                    'successful' => array_slice($successful, 0, 20),
                    'failed_24h' => count(array_filter($failed, static fn (array $row): bool => (string) ($row['created_at'] ?? '') >= gmdate('Y-m-d H:i:s', time() - 86400))),
                ],
                'locked_accounts' => $lockedAccounts,
                'activity' => Database::select(
                    'SELECT al.id, al.action, al.entity_type, al.entity_id, al.ip, al.created_at, a.name AS admin_name
                     FROM audit_logs al LEFT JOIN admins a ON a.id = al.admin_id
                     ORDER BY al.id DESC LIMIT 40'
                ),
                'policy' => [
                    'max_attempts' => (int) Config::get('security.login.max_attempts', 5),
                    'window_minutes' => (int) Config::get('security.login.window_minutes', 15),
                    'lockout_minutes' => (int) Config::get('security.login.lockout_minutes', 15),
                    'idle_timeout' => (int) Config::get('security.session.idle_timeout', 7200),
                    'absolute_lifetime' => (int) Config::get('security.session.lifetime', 1209600),
                    'password_algorithm' => (string) Config::get('security.password.algorithm', 'bcrypt'),
                    'session_secure' => (bool) Config::get('security.session.secure_cookie', false),
                ],
                'system' => [
                    'php' => PHP_VERSION,
                    'driver' => Database::driver(),
                    'debug' => (bool) Config::get('app.debug', false),
                    'cache_files' => count(glob(rtrim((string) Config::get('app.storage_path', ''), '/') . '/cache/*') ?: []),
                    'log_size' => $this->directorySize(rtrim((string) Config::get('app.storage_path', ''), '/') . '/logs'),
                    'upload_size' => $this->directorySize(rtrim((string) Config::get('app.public_path', ''), '/') . '/uploads'),
                ],
            ],
        ]);
    }

    /** Sign out one session (IDOR-safe: the id is validated against the table). */
    public function revoke(Request $request): Response
    {
        $id = (string) $request->routeParam('id');
        $session = Admin::findSession($id);

        if ($session === null) {
            throw HttpException::notFound('Session not found or already expired.');
        }

        if ($id === Session::id()) {
            throw HttpException::badRequest('Use “sign out” to end your own session.');
        }

        Admin::deleteSession($id);
        AuditLogger::log(Auth::id(), 'session.revoke', 'admin_sessions', null, [
            'session' => substr($id, 0, 12) . '…',
            'admin_id' => (int) $session['admin_id'],
        ]);

        return Response::json(['message' => 'Session revoked.']);
    }

    /** Clear failed attempts and unlock any account that hit the limit. */
    public function clearAttempts(Request $request): Response
    {
        $unused = $request;

        $deleted = Database::table('login_attempts')->where('successful', 0)->delete();
        $unlocked = Database::table('admins')->where('failed_attempts', '>', 0)->update([
            'failed_attempts' => 0,
            'locked_until' => null,
            'updated_at' => now_utc(),
        ]);

        AuditLogger::log(Auth::id(), 'security.clear_attempts', 'login_attempts', null, [
            'deleted' => $deleted,
            'unlocked' => $unlocked,
        ]);

        return Response::json([
            'data' => ['deleted' => $deleted, 'unlocked' => $unlocked],
            'message' => $deleted . ' failed attempt(s) cleared, ' . $unlocked . ' account(s) unlocked.',
        ]);
    }

    /** Flush cached settings, translations and language rows. */
    public function clearCache(Request $request): Response
    {
        $unused = $request;

        $removed = Cache::flush();
        (new SettingsService())->flush();
        TranslationService::flush();
        (new LocaleService())->reset();


        AuditLogger::log(Auth::id(), 'security.clear_cache', null, null, ['files' => $removed]);

        return Response::json([
            'data' => ['removed' => $removed],
            'message' => $removed . ' cache file(s) removed.',
        ]);
    }

    private function directorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;

        foreach (glob(rtrim($directory, '/') . '/*') ?: [] as $file) {
            $size += is_file($file) ? (int) filesize($file) : $this->directorySize($file);
        }

        return $size;
    }
}
