<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `languages`.
 */
final class Language extends Model
{
    protected static string $table = 'languages';
    protected static array $casts = ['is_default' => 'bool','is_active' => 'bool','sort_order' => 'int'];


    public static function active(): array
    {
        return array_map(static fn (array $row) => static::cast($row), static::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get());
    }

    public static function codes(): array
    {
        return static::query()->where('is_active', 1)->pluck('code');
    }

    public static function default(): ?array
    {
        $row = static::query()->where('is_default', 1)->where('is_active', 1)->first()
            ?? static::query()->where('is_active', 1)->orderBy('sort_order')->first();

        return $row === null ? null : static::cast($row);
    }

    public static function findByCode(string $code): ?array
    {
        return static::findBy('code', $code);
    }

    public static function isSupported(string $code): bool
    {
        return static::query()->where('code', $code)->where('is_active', 1)->exists();
    }
}
