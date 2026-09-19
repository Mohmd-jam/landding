<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `seo_metadata`.
 */
final class SeoMetadata extends Model
{
    protected static string $table = 'seo_metadata';


    public static function forEntity(string $type, ?int $entityId, string $lang): ?array
    {
        $query = static::query()->where('entity_type', $type)->where('lang', $lang);
        $entityId === null ? $query->whereNull('entity_id') : $query->where('entity_id', $entityId);

        return $query->first();
    }
}
