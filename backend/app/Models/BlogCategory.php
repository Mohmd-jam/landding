<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `blog_categories`.
 */
final class BlogCategory extends Model
{
    protected static string $table = 'blog_categories';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int'];
}
