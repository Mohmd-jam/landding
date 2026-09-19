<?php

declare(strict_types=1);

namespace App\Core;

/**
 * String helpers with first-class Persian/Arabic support (slugify, normalising
 * Arabic/Persian digits and ZWNJ, safe truncation for RTL text).
 */
final class Str
{
    /** Persian & Arabic digit maps used for normalisation of user input. */
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * Build a URL slug that works for both Persian and English titles.
     * Keeps Persian letters (search engines index them fine) and strips
     * punctuation/diacritics; falls back to a random suffix when empty.
     */
    public static function slug(string $value, int $maxLength = 90): string
    {
        $value = trim($value);

        // Normalise Arabic characters to Persian equivalents
        $value = str_replace(['ي', 'ك', 'ة', 'ۀ', 'ؤ', 'إ', 'أ'], ['ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا'], $value);

        // Remove Arabic diacritics (harakat)
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;

        // ZWNJ / ZWJ / BOM become dashes
        $value = str_replace(["\u{200c}", "\u{200d}", "\u{feff}"], '-', $value);

        // Lowercase latin
        $value = mb_strtolower($value, 'UTF-8');

        // Replace anything that is not a letter, digit or dash
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';

        $value = trim($value, '-');
        $value = preg_replace('/-{2,}/', '-', $value) ?? '';

        if ($value === '') {
            $value = 'item-' . self::random(6);
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    /** HTML-escape for safe output (defence in depth next to React escaping). */
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** Convert Persian/Arabic digits to ASCII digits. */
    public static function toAsciiDigits(string $value): string
    {
        return str_replace(
            array_merge(self::PERSIAN_DIGITS, self::ARABIC_DIGITS),
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }

    public static function random(int $length = 32): string
    {
        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** Multibyte-safe limit with ellipsis. */
    public static function limit(string $value, int $limit = 120, string $end = '…'): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . $end;
    }

    /** Reading time estimate (Persian ~ 900 chars/min, English ~ 1100). */
    public static function readingTime(string $content): int
    {
        $length = mb_strlen(strip_tags($content), 'UTF-8');

        return max(1, (int) ceil($length / 950));
    }

    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
    }

    public static function plural(int $count, string $singular, ?string $plural = null): string
    {
        return $count === 1 ? $singular : ($plural ?? $singular . 's');
    }

    /** Format a number into Persian digits when the locale is fa. */
    public static function digits(string|int|float $value, string $locale = 'en'): string
    {
        $formatted = (string) $value;

        if ($locale !== 'fa') {
            return $formatted;
        }

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            self::PERSIAN_DIGITS,
            $formatted
        );
    }
}
