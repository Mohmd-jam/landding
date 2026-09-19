<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `testimonials`.
 */
final class Testimonial extends Model
{
    protected static string $table = 'testimonials';
    protected static array $casts = ['rating' => 'int','is_featured' => 'bool','is_active' => 'bool','sort_order' => 'int'];
}
