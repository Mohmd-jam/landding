<?php

/**
 * Security policy: sessions, cookies, CSRF, throttling and response headers.
 */

use App\Core\Env;

return [
    'session' => [
        'name' => Env::get('SESSION_NAME', 'portfolio_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120 * 60),      // idle timeout (seconds)
        'absolute_lifetime' => (int) Env::get('SESSION_ABSOLUTE', 14 * 24 * 60 * 60),
        'table' => 'admin_sessions',
        'cookie_path' => '/',
        'same_site' => Env::get('SESSION_SAME_SITE', 'Lax'),
        'secure' => Env::bool('SESSION_SECURE', false),
        'bind_ip' => Env::bool('SESSION_BIND_IP', false),
        'bind_agent' => true,
        'regenerate_on_login' => true,
    ],

    'login' => [
        'max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
        'window_minutes' => (int) Env::get('LOGIN_WINDOW_MINUTES', 15),
        'remember_days' => 30,
    ],

    'password' => [
        'min_length' => 10,
        'require_mixed_case' => true,
        'require_number' => true,
        'algo' => PASSWORD_DEFAULT,
    ],

    'csrf' => [
        'token_name' => '_csrf',
        'header' => 'X-CSRF-Token',
        'ttl' => 2 * 60 * 60,
    ],

    'throttle' => [
        'public' => ['attempts' => 120, 'decay_seconds' => 60],
        'contact' => ['attempts' => 5, 'decay_seconds' => 600],
        'login' => ['attempts' => 8, 'decay_seconds' => 300],
        'upload' => ['attempts' => 40, 'decay_seconds' => 600],
    ],

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        'X-XSS-Protection' => '0',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ],

    'csp' => Env::get(
        'CONTENT_SECURITY_POLICY',
        "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'; font-src 'self' data:; connect-src 'self'; form-action 'self'; "
        . "frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
    ),
];
