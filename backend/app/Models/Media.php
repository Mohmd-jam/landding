<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `media`.
 */
final class Media extends Model
{
    protected static string $table = 'media';
    protected static array $casts = ['size' => 'int','width' => 'int','height' => 'int'];


    public static function search(?string $term, ?string $folder = null, int $perPage = 24, int $page = 1): array
    {
        $query = static::query();

        if ($term !== null && trim($term) !== '') {
            $query->whereLikeAny(['filename', 'original_name', 'alt_text'], trim($term));
        }

        if ($folder !== null && $folder !== '' && $folder !== 'all') {
            $query->where('folder', $folder);
        }

        return $query->orderBy('created_at', 'DESC')->paginate($perPage, $page);
    }
}
