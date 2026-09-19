<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `experience_translations`.
 */
final class ExperienceTranslation extends Model
{
    protected static string $table = 'experience_translations';
    protected static array $casts = ['responsibilities_json' => 'array','achievements_json' => 'array'];
}
