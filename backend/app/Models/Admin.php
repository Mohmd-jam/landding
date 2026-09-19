<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Table `admins` + the admin session/attempt/reset tables it owns.
 *
 * Passwords are only ever stored as password_hash() output; this model never
 * handles plain-text credentials beyond passing them to Core\Auth.
 */
final class Admin extends Model
{
    protected static string $table = 'admins';

    protected static array $casts = [
        'is_active' => 'bool',
        'must_change_password' => 'bool',
        'failed_attempts' => 'int',
    ];

    /** Columns safe to serialise to the admin UI (never the password hash). */
    public const SAFE_COLUMNS = [
        'id', 'name', 'email', 'role', 'avatar_media_id', 'bio', 'is_active',
        'must_change_password', 'last_login_at', 'last_login_ip', 'failed_attempts',
        'locked_until', 'created_at', 'updated_at',
    ];

    public static function findByEmail(string $email): ?array
    {
        $row = static::query()->where('email', strtolower(trim($email)))->first();

        return $row === null ? null : static::cast($row);
    }

    public static function safe(array $row): array
    {
        return array_intersect_key($row, array_flip(self::SAFE_COLUMNS));
    }

    public static function listPaginated(array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $query = static::query();

        if (!empty($filters['search'])) {
            $query->whereLikeAny(['name', 'email'], (string) $filters['search']);
        }

        if (!empty($filters['role'])) {
            $query->where('role', (string) $filters['role']);
        }

        return $query->orderBy('name')->paginate($perPage, $page);
    }

    /* --------------------------------------------------------------------- */
    /* Sessions                                                              */
    /* --------------------------------------------------------------------- */

    public static function createSession(string $sessionId, int $adminId, ?string $ip, ?string $agent): void
    {
        $now = self::now();

        Database::table('admin_sessions')->insert([
            'id' => $sessionId,
            'admin_id' => $adminId,
            'ip' => $ip,
            'user_agent' => substr((string) $agent, 0, 255),
            'payload' => null,
            'last_activity' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function findSession(string $sessionId): ?array
    {
        return Database::table('admin_sessions')->where('id', $sessionId)->first();
    }

    public static function touchSession(string $sessionId): void
    {
        Database::table('admin_sessions')
            ->where('id', $sessionId)
            ->update(['last_activity' => self::now(), 'updated_at' => self::now()]);
    }

    public static function deleteSession(string $sessionId): void
    {
        Database::table('admin_sessions')->where('id', $sessionId)->delete();
    }

    public static function deleteSessionsFor(int $adminId): int
    {
        return Database::table('admin_sessions')->where('admin_id', $adminId)->delete();
    }

    /** Sessions for the Security screen, optionally without the current one. */
    public static function activeSessions(?string $exceptId = null): array
    {
        $query = Database::table('admin_sessions as s')
            ->select('s.id', 's.admin_id', 's.ip', 's.user_agent', 's.last_activity', 's.created_at', 'a.name as admin_name', 'a.email as admin_email')
            ->leftJoin('admins as a', 'a.id', '=', 's.admin_id')
            ->orderBy('s.last_activity', 'DESC')
            ->limit(100);

        if ($exceptId !== null) {
            $query->where('s.id', '!=', $exceptId);
        }

        return $query->get();
    }

    /* --------------------------------------------------------------------- */
    /* Login attempts                                                        */
    /* --------------------------------------------------------------------- */

    public static function recordAttempt(string $email, ?string $ip, bool $successful): void
    {
        $now = self::now();

        Database::table('login_attempts')->insert([
            'email' => strtolower(trim($email)),
            'ip' => $ip,
            'successful' => $successful ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function recentFailures(string $email, ?string $ip, int $windowMinutes): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - $windowMinutes * 60);

        return Database::table('login_attempts')
            ->where('successful', 0)
            ->where('created_at', '>=', $since)
            ->where(function ($query) use ($email, $ip) {
                $query->where('email', strtolower($email));
                if ($ip !== null) {
                    $query->orWhere('ip', $ip);
                }
            })
            ->count();
    }

    public static function recentAttempts(int $limit = 50): array
    {
        return Database::table('login_attempts')->orderBy('created_at', 'DESC')->limit($limit)->get();
    }

    public static function registerFailedLogin(int $adminId): int
    {
        $admin = self::find($adminId);

        if ($admin === null) {
            return 0;
        }

        $max = (int) (\App\Core\Config::get('security.login.max_attempts', 5));
        $attempts = (int) $admin['failed_attempts'] + 1;
        $lockMinutes = (int) (\App\Core\Config::get('security.login.lockout_minutes', 15));

        self::update($adminId, [
            'failed_attempts' => $attempts,
            'locked_until' => $attempts >= $max ? gmdate('Y-m-d H:i:s', time() + $lockMinutes * 60) : null,
        ]);

        return $attempts;
    }

    public static function registerSuccessfulLogin(int $adminId, ?string $ip): void
    {
        self::update($adminId, [
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => self::now(),
            'last_login_ip' => $ip,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /* Password resets                                                       */
    /* --------------------------------------------------------------------- */

    public static function createPasswordReset(int $adminId, string $tokenHash): void
    {
        $now = self::now();

        Database::table('password_resets')->where('admin_id', $adminId)->delete();
        Database::table('password_resets')->insert([
            'admin_id' => $adminId,
            'token_hash' => $tokenHash,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'used_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function findValidReset(int $adminId, string $tokenHash): ?array
    {
        return Database::table('password_resets')
            ->where('admin_id', $adminId)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>=', self::now())
            ->first();
    }

    public static function consumeReset(int $id): void
    {
        Database::table('password_resets')->where('id', $id)->update([
            'used_at' => self::now(),
            'updated_at' => self::now(),
        ]);
    }
}
