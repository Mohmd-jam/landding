<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `project_categories`.
 */
final class ProjectCategory extends Model
{
    protected static string $table = 'project_categories';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int'];
}
