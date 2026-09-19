<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `experiences`.
 */
final class Experience extends Model
{
    protected static string $table = 'experiences';
    protected static array $casts = ['is_current' => 'bool','is_active' => 'bool','sort_order' => 'int'];
}
