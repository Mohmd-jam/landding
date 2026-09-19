<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `navigation`.
 */
final class Navigation extends Model
{
    protected static string $table = 'navigation';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int','open_in_new_tab' => 'bool'];


    public static function location(string $location, bool $onlyActive = true): array
    {
        $query = static::query()->where('location', $location);
        if ($onlyActive) {
            $query->where('is_active', 1);
        }

        return static::castMany($query->orderBy('sort_order')->get());
    }
}
