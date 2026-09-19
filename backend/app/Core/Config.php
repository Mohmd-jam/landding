<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Dot-notation configuration repository.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function load(string $directory): void
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            /** @var array<string,mixed> $values */
            $values = require $file;
            self::$items[$key] = is_array($values) ? $values : [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    public static function has(string $key): bool
    {
        return self::get($key, '__missing__') !== '__missing__';
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}
