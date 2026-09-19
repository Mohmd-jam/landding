<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `blog_tags`.
 */
final class BlogTag extends Model
{
    protected static string $table = 'blog_tags';
    protected static array $casts = ['is_active' => 'bool'];
}
