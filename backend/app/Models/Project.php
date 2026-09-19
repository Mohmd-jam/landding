<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `projects`.
 */
final class Project extends Model
{
    protected static string $table = 'projects';
    protected static array $casts = ['is_featured' => 'bool','is_active' => 'bool','sort_order' => 'int','views' => 'int'];


    public static function featured(int $limit = 3): array
    {
        return static::castMany(static::query()
            ->where('is_active', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get());
    }

    public static function incrementViews(int $id): void
    {
        static::query()->where('id', $id)->increment('views');
    }
}
