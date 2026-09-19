<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `section_translations`.
 */
final class SectionTranslation extends Model
{
    protected static string $table = 'section_translations';
    protected static array $casts = ['items_json' => 'json'];
}
