<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Admin;

/**
 * Admin authentication.
 *
 * - passwords are verified with password_verify() against a bcrypt/argon hash;
 * - repeated failures are counted per e-mail + IP with a lockout window;
 * - the authenticated session is persisted in `admin_sessions` so the Security
 *   screen can list and revoke active sessions;
 * - "remember me" uses a signed, rotating token that is stored hashed.
 */
final class Auth
{
    private const SESSION_KEY = 'admin_id';
    private const TOKEN_KEY = 'admin_session_id';
    private const REMEMBER_COOKIE = 'portfolio_remember';

    private static ?array $user = null;
    private static bool $resolved = false;

    /**
     * @return array{ok: bool, user?: array, error?: string, locked_until?: string}
     */
    public static function attempt(string $email, string $password, bool $remember = false, ?string $ip = null, ?string $agent = null): array
    {
        $email = strtolower(trim($email));
        $ip = $ip ?? 'cli';
        $login = (array) Config::get('security.login', []);
        $maxAttempts = (int) ($login['max_attempts'] ?? 5);
        $windowMinutes = (int) ($login['window_minutes'] ?? 15);

        $admin = Admin::findByEmail($email);

        // Generic message prevents account enumeration.
        $genericError = 'The email or password is incorrect.';

        // Hard lock after 2× the allowed attempts inside the window.
        if (Admin::recentFailures($email, $ip, $windowMinutes) >= $maxAttempts * 2) {
            Admin::recordAttempt($email, $ip, false);

            return [
                'ok' => false,
                'error' => 'Too many failed attempts. Please try again in ' . $windowMinutes . ' minutes.',
                'locked_until' => gmdate('c', time() + $windowMinutes * 60),
            ];
        }

        if ($admin === null) {
            Admin::recordAttempt($email, $ip, false);
            // Constant-ish time so a missing account is not detectable by timing.
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');

            return ['ok' => false, 'error' => $genericError];
        }

        if (!(bool) $admin['is_active']) {
            Admin::recordAttempt($email, $ip, false);

            return ['ok' => false, 'error' => 'This account is disabled. Contact a super admin.'];
        }

        if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
            Admin::recordAttempt($email, $ip, false);

            return [
                'ok' => false,
                'error' => 'This account is temporarily locked after repeated failures.',
                'locked_until' => gmdate('c', strtotime((string) $admin['locked_until'])),
            ];
        }

        if (!Security::verifyPassword($password, (string) $admin['password_hash'])) {
            $attempts = Admin::registerFailedLogin((int) $admin['id']);
            Admin::recordAttempt($email, $ip, false);

            $remaining = max(0, $maxAttempts - $attempts);

            return [
                'ok' => false,
                'error' => $remaining > 0
                    ? $genericError . ' ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' left.'
                    : 'Too many failed attempts. Please try again later.',
            ];
        }

        // Transparently upgrade hashes when the algorithm/cost changes.
        if (Security::needsRehash((string) $admin['password_hash'])) {
            Admin::update((int) $admin['id'], ['password_hash' => Security::hashPassword($password)]);
        }

        Admin::registerSuccessfulLogin((int) $admin['id'], $ip);
        Admin::recordAttempt($email, $ip, true);
        self::login($admin, $remember, $ip, $agent);

        return ['ok' => true, 'user' => Admin::safe($admin)];
    }

    /** Open an authenticated session for an admin row. */
    public static function login(array $admin, bool $remember = false, ?string $ip = null, ?string $agent = null): void
    {
        Session::start();
        Session::regenerate(true);

        $sessionId = bin2hex(random_bytes(32));

        Session::put(self::SESSION_KEY, (int) $admin['id']);
        Session::put(self::TOKEN_KEY, $sessionId);
        Session::put('_started_at', time());

        Admin::createSession($sessionId, (int) $admin['id'], $ip, $agent);

        if ($remember) {
            $token = Security::randomToken(32);
            Admin::update((int) $admin['id'], ['remember_token' => Security::hashToken($token)]);

            $cookieValue = $admin['id'] . '|' . $token;
            $days = (int) (Config::get('security.login.remember_days', 30));

            setcookie(self::REMEMBER_COOKIE, $cookieValue, [
                'expires' => time() + $days * 86400,
                'path' => '/',
                'secure' => (bool) Config::get('security.session.secure', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        self::$user = Admin::find((int) $admin['id']);
        self::$resolved = true;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$resolved = true;

        $sessionId = Session::get(self::TOKEN_KEY);

        if (is_string($sessionId) && $sessionId !== '') {
            Admin::deleteSession($sessionId);
        }

        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        Session::start();

        $sessionId = Session::get(self::TOKEN_KEY);
        $adminId = Session::get(self::SESSION_KEY);

        if (!is_string($sessionId) || $adminId === null) {
            self::$user = self::userFromRememberCookie();

            return self::$user;
        }

        $record = Admin::findSession($sessionId);

        if ($record === null || (int) $record['admin_id'] !== (int) $adminId) {
            self::$user = null;

            return null;
        }

        $config = (array) Config::get('security.session', []);
        $idleLimit = (int) ($config['lifetime'] ?? 7200);
        $absoluteLimit = (int) ($config['absolute_lifetime'] ?? 1209600);
        $lastActivity = strtotime((string) $record['last_activity']) ?: 0;
        $startedAt = (int) Session::get('_started_at', $lastActivity);

        if (time() - $lastActivity > $idleLimit || time() - $startedAt > $absoluteLimit) {
            Admin::deleteSession($sessionId);
            Session::destroy();
            self::$user = null;

            return null;
        }

        $admin = Admin::find((int) $adminId);

        if ($admin === null || !(bool) $admin['is_active']) {
            self::$user = null;

            return null;
        }

        Admin::touchSession($sessionId);
        self::$user = $admin;

        return self::$user;
    }

    /** Role helper used by admin controllers. */
    public static function can(string $capability): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        $role = (string) $user['role'];

        if ($role === 'super_admin') {
            return true;
        }

        return in_array($capability, match ($role) {
            'admin' => ['content', 'media', 'messages', 'seo'],
            'editor' => ['content', 'media'],
            default => [],
        }, true);
    }

    public static function require(string $capability): void
    {
        if (!self::can($capability)) {
            throw HttpException::forbidden('Your role does not allow this action.');
        }
    }

    public static function changePassword(int $adminId, string $currentPassword, string $newPassword): void
    {
        $admin = Admin::find($adminId);

        if ($admin === null) {
            throw HttpException::notFound('Account not found.');
        }

        if (!Security::verifyPassword($currentPassword, (string) $admin['password_hash'])) {
            throw new ValidationException('The current password is not correct.', ['current_password' => ['The current password is not correct.']]);
        }

        Admin::update($adminId, [
            'password_hash' => Security::hashPassword($newPassword),
            'must_change_password' => 0,
        ]);
    }

    private static function userFromRememberCookie(): ?array
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;

        if (!is_string($cookie) || !str_contains($cookie, '|')) {
            return null;
        }

        [$id, $token] = explode('|', $cookie, 2);
        $admin = Admin::find((int) $id);

        if ($admin === null || $admin['remember_token'] === null) {
            return null;
        }

        if (!Security::timingSafeEquals((string) $admin['remember_token'], Security::hashToken($token))) {
            return null;
        }

        // Rotate the token and open a fresh session.
        $newToken = Security::randomToken(32);
        Admin::update((int) $admin['id'], ['remember_token' => Security::hashToken($newToken)]);

        setcookie(self::REMEMBER_COOKIE, $admin['id'] . '|' . $newToken, [
            'expires' => time() + 30 * 86400,
            'path' => '/',
            'secure' => (bool) Config::get('security.session.secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        Session::start();
        Session::regenerate(true);

        $sessionId = bin2hex(random_bytes(32));
        Session::put(self::SESSION_KEY, (int) $admin['id']);
        Session::put(self::TOKEN_KEY, $sessionId);
        Session::put('_started_at', time());
        Admin::createSession($sessionId, (int) $admin['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

        return $admin;
    }
}
