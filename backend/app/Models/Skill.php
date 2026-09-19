<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `skills`.
 */
final class Skill extends Model
{
    protected static string $table = 'skills';
    protected static array $casts = ['level' => 'int','is_featured' => 'bool','is_active' => 'bool','sort_order' => 'int'];


    public static function byKind(string $kind, bool $onlyActive = true): array
    {
        $query = static::query()->where('kind', $kind);
        if ($onlyActive) {
            $query->where('is_active', 1);
        }

        return static::castMany($query->orderBy('sort_order')->get());
    }
}
