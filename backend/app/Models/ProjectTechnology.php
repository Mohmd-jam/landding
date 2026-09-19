<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `project_technologies`.
 */
final class ProjectTechnology extends Model
{
    protected static string $table = 'project_technologies';
    protected static array $casts = ['sort_order' => 'int'];
}
