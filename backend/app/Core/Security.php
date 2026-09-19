<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central place for everything security related: escaping, password hashing,
 * CSRF tokens, signed payloads, upload-name hardening and header policy.
 *
 * Rule of thumb used across the codebase: escaping happens on output, validation
 * on input, and the client is never trusted for either.
 */
final class Security
{
    /** Escape a value for HTML text/attribute contexts. */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = Json::encode($value);
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, self::passwordAlgorithm());
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::passwordAlgorithm());
    }

    /** PASSWORD_DEFAULT is a string in PHP 8.3 — never cast it to int. */
    public static function passwordAlgorithm(): string|int
    {
        $configured = Config::get('security.password.algo', PASSWORD_DEFAULT);

        return is_string($configured) || is_int($configured) ? $configured : PASSWORD_DEFAULT;
    }

    public static function randomToken(int $bytes = 32): string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Throwable) {
            return hash('sha256', uniqid((string) mt_rand(), true));
        }
    }

    /** Tokens are stored hashed, so a database leak cannot be replayed. */
    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) Config::get('app.key', 'portfolio'));
    }

    public static function timingSafeEquals(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return hash_equals($a, $b);
    }

    /* --------------------------------------------------------------------- */
    /* CSRF                                                                  */
    /* --------------------------------------------------------------------- */

    public static function csrfToken(): string
    {
        Session::start();

        $token = Session::get('_csrf_token');

        if (!is_string($token) || $token === '') {
            $token = self::randomToken(32);
            Session::put('_csrf_token', $token);
        }

        return $token;
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="' . Config::get('security.csrf.token_name', '_csrf') . '" value="' . self::escape(self::csrfToken()) . '">';
    }

    /** Accepts the token from a header, the request body or the query string. */
    public static function verifyCsrf(Request $request): bool
    {
        Session::start();

        $expected = Session::get('_csrf_token');

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $header = (string) Config::get('security.csrf.header', 'X-CSRF-Token');
        $field = (string) Config::get('security.csrf.token_name', '_csrf');

        $candidates = array_filter([
            $request->header($header),
            $request->input($field),
            $request->query($field),
        ], static fn ($value): bool => is_string($value) && $value !== '');

        foreach ($candidates as $candidate) {
            if (self::timingSafeEquals($expected, (string) $candidate)) {
                return true;
            }
        }

        return false;
    }

    /* --------------------------------------------------------------------- */
    /* Signed payloads (remember-me, download links, unsubscribe …)           */
    /* --------------------------------------------------------------------- */

    public static function sign(array $payload, int $ttlSeconds = 7200): string
    {
        $payload['exp'] = time() + $ttlSeconds;
        $json = Json::encode($payload);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return $encoded . '.' . hash_hmac('sha256', $encoded, (string) Config::get('app.key', 'portfolio'));
    }

    public static function verifySignature(?string $token): ?array
    {
        if ($token === null || !str_contains($token, '.')) {
            return null;
        }

        [$encoded, $signature] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $encoded, (string) Config::get('app.key', 'portfolio'));

        if (!self::timingSafeEquals($expected, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        $payload = Json::decode($json, null);

        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    /* --------------------------------------------------------------------- */
    /* IP handling & HTML sanitising                                          */
    /* --------------------------------------------------------------------- */

    /**
     * Privacy-preserving visitor fingerprint: a daily rotating HMAC of the IP.
     * The raw address is never stored for analytics.
     */
    public static function hashIp(?string $ip): ?string
    {
        if ($ip === null || trim($ip) === '') {
            return null;
        }

        $key = (string) Config::get('app.key', 'portfolio');

        return substr(hash_hmac('sha256', trim($ip) . '|' . gmdate('Y-m-d'), $key), 0, 32);
    }

    /**
     * Content from rich-text editors is stored as authored HTML. This filter
     * strips anything executable while keeping the formatting people expect.
     */
    public static function sanitizeHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Remove script/style blocks and comments entirely.
        $clean = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $clean = preg_replace('/<!--.*?-->/s', '', $clean) ?? $clean;

        // Drop every on* event handler and javascript:/data: URLs.
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data):[^"\']*\2/i', '$1="#"', $clean) ?? $clean;

        return trim($clean);
    }

    public static function plainText(?string $html, int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');

        return Str::limit($text, $limit);
    }

    /** Only same-origin or explicitly allowed absolute URLs pass. */
    public static function safeUrl(?string $url, string $fallback = '#'): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $fallback;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return $url;
        }

        return $fallback;
    }

    /** Hardening for stored file names (defence in depth behind the media service). */
    public static function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'file';
        $name = preg_replace('/\.{2,}/', '.', $name) ?? $name;

        return trim($name, '.-') !== '' ? ltrim($name, '.') : 'file';
    }

    /** @return array<int,string> human readable findings for a weak password */
    public static function passwordIssues(string $password): array
    {
        $config = (array) Config::get('security.password', []);
        $issues = [];

        if (mb_strlen($password) < (int) ($config['min_length'] ?? 10)) {
            $issues[] = 'too_short';
        }
        if ((bool) ($config['require_mixed_case'] ?? true) && !preg_match('/[a-z]/u', $password)) {
            $issues[] = 'needs_lowercase';
        }
        if ((bool) ($config['require_mixed_case'] ?? true) && !preg_match('/[A-Z]/u', $password)) {
            $issues[] = 'needs_uppercase';
        }
        if ((bool) ($config['require_number'] ?? true) && !preg_match('/\d/u', $password)) {
            $issues[] = 'needs_number';
        }
        if (preg_match('/^(?:password|123456|qwerty|admin)/i', $password) === 1) {
            $issues[] = 'too_common';
        }

        return $issues;
    }
}
