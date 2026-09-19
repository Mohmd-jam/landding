<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `settings`.
 */
final class Setting extends Model
{
    protected static string $table = 'settings';
    protected static array $casts = ['is_public' => 'bool'];


    public static function getByKey(string $key): ?array
    {
        return static::findBy('key_name', $key);
    }

    public static function group(string $group): array
    {
        return static::query()->where('group_key', $group)->orderBy('key_name')->get();
    }
}
