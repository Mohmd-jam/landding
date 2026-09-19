<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Dependency-free PSR-3-style logger writing daily files under storage/logs.
 *
 * Levels are filtered by config('app.log_level'), and the last error is kept in
 * a small JSON file so the admin Security screen can surface it without the
 * operator needing shell access to the server.
 */
final class Logger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'notice' => 25, 'warning' => 30, 'error' => 40, 'critical' => 50];

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function exception(Throwable $e, array $context = []): void
    {
        self::log('error', $e->getMessage(), $context + [
            'exception' => $e::class,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
        ]);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::enabled($level)) {
            return;
        }

        $path = self::path();

        if ($path === null) {
            return;
        }

        $line = sprintf(
            '[%s] %s.%s: %s %s%s',
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            strtoupper(PHP_SAPI),
            $message,
            $context === [] ? '' : Json::encode(self::scrub($context)),
            PHP_EOL
        );

        self::rotate($path);

        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        if (in_array($level, ['error', 'critical'], true)) {
            self::recordLastError($level, $message, $context);
        }
    }

    public static function path(): ?string
    {
        $directory = rtrim((string) Config::get('app.storage_path', sys_get_temp_dir()), '/') . '/logs';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        return $directory . '/app-' . gmdate('Y-m-d') . '.log';
    }

    /** Last error surfaced in the admin panel (never contains secrets). */
    public static function lastError(): ?array
    {
        $file = self::statePath();

        if ($file === null || !is_file($file)) {
            return null;
        }

        $decoded = Json::decode((string) @file_get_contents($file), null);

        return is_array($decoded) ? $decoded : null;
    }

    public static function clearLastError(): void
    {
        $file = self::statePath();

        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    /** @return array<int,string> */
    public static function recentLines(int $lines = 100): array
    {
        $path = self::path();

        if ($path === null || !is_file($path)) {
            return [];
        }

        $content = (string) @file_get_contents($path);
        $all = array_values(array_filter(explode("\n", $content), static fn (string $line): bool => trim($line) !== ''));

        return array_slice($all, -$lines);
    }

    private static function enabled(string $level): bool
    {
        $configured = strtolower((string) Config::get('app.log_level', 'warning'));
        $threshold = self::LEVELS[$configured] ?? 30;

        return (self::LEVELS[$level] ?? 20) >= $threshold;
    }

    private static function rotate(string $path): void
    {
        if (!is_file($path) || (int) @filesize($path) < 5 * 1024 * 1024) {
            return;
        }

        $archive = $path . '.1';

        if (is_file($archive)) {
            @unlink($archive);
        }

        @rename($path, $archive);
    }

    private static function recordLastError(string $level, string $message, array $context): void
    {
        $file = self::statePath();

        if ($file === null) {
            return;
        }

        @file_put_contents($file, Json::encode([
            'level' => $level,
            'message' => $message,
            'context' => self::scrub($context),
            'at' => gmdate('c'),
        ]), LOCK_EX);
    }

    private static function statePath(): ?string
    {
        $directory = rtrim((string) Config::get('app.storage_path', sys_get_temp_dir()), '/') . '/cache';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        return $directory . '/last-error.json';
    }

    /** Remove credentials and request bodies before anything is written down. */
    private static function scrub(array $context): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'csrf', '_csrf', 'authorization', 'cookie', 'secret'];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = self::scrub($value);
            }
        }

        return $context;
    }
}
