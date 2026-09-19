<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JSON helpers (UTF-8 safe, never throwing on encode).
 */
final class Json
{
    public static function encode(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{}' : $json;
    }

    /**
     * JSON safe to inline inside <script>: every character that could close the
     * tag or start a comment is hex-escaped, so content coming from the database
     * can never break out of the bootstrap payload.
     */
    public static function encodeForScript(mixed $value): string
    {
        return str_replace(
            ['<', '>', '&', '\u2028', '\u2029'],
            ['\\u003C', '\\u003E', '\\u0026', '\\u2028', '\\u2029'],
            self::encode($value)
        );
    }

    public static function decode(?string $value, mixed $default = null): mixed
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /** Decode a JSON array column, always returning a list. */
    public static function list(?string $value): array
    {
        $decoded = self::decode($value, []);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public static function lastError(): string
    {
        return match (json_last_error()) {
            JSON_ERROR_NONE => 'none',
            JSON_ERROR_DEPTH => 'depth',
            JSON_ERROR_SYNTAX => 'syntax',
            JSON_ERROR_UTF8 => 'utf8',
            default => 'unknown',
        };
    }
}
