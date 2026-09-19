<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `skill_categories`.
 */
final class SkillCategory extends Model
{
    protected static string $table = 'skill_categories';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int'];
}
