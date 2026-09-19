<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `certifications`.
 */
final class Certification extends Model
{
    protected static string $table = 'certifications';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int'];
}
