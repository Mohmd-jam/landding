<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `education`.
 */
final class Education extends Model
{
    protected static string $table = 'education';
    protected static array $casts = ['is_current' => 'bool','is_active' => 'bool','sort_order' => 'int'];
}
