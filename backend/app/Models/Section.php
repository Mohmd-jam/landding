<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `sections`.
 */
final class Section extends Model
{
    protected static string $table = 'sections';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int','settings_json' => 'json'];


    public static function forPage(int $pageId, bool $onlyActive = true): array
    {
        $query = static::query()->where('page_id', $pageId);
        if ($onlyActive) {
            $query->where('is_active', 1);
        }

        return static::castMany($query->orderBy('sort_order')->get());
    }
}
