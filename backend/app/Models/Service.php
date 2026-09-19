<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `services`.
 */
final class Service extends Model
{
    protected static string $table = 'services';
    protected static array $casts = ['is_featured' => 'bool','is_active' => 'bool','sort_order' => 'int'];
}
