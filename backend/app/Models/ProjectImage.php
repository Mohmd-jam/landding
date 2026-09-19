<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `project_images`.
 */
final class ProjectImage extends Model
{
    protected static string $table = 'project_images';
    protected static array $casts = ['sort_order' => 'int'];
}
