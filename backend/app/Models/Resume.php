<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `resumes`.
 */
final class Resume extends Model
{
    protected static string $table = 'resumes';
    protected static array $casts = ['is_primary' => 'bool','is_active' => 'bool','downloads' => 'int'];


    public static function forLanguage(string $lang): ?array
    {
        $row = static::query()->where('lang', $lang)->where('is_active', 1)->orderBy('is_primary', 'DESC')->first();

        return $row === null ? null : static::cast($row);
    }

    public static function incrementDownloads(int $id): void
    {
        static::query()->where('id', $id)->increment('downloads');
    }
}
