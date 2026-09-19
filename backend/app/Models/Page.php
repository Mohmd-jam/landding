<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `pages`.
 */
final class Page extends Model
{
    protected static string $table = 'pages';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int'];


    public static function findByKey(string $key): ?array
    {
        return static::findBy('key_name', $key);
    }

    public static function ordered(): array
    {
        return static::castMany(static::query()->where('is_active', 1)->orderBy('sort_order')->get());
    }
}
