<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `blog_posts`.
 */
final class BlogPost extends Model
{
    protected static string $table = 'blog_posts';
    protected static array $casts = ['is_featured' => 'bool','allow_comments' => 'bool','views' => 'int','reading_time' => 'int','sort_order' => 'int'];


    public static function incrementViews(int $id): void
    {
        static::query()->where('id', $id)->increment('views');
    }
}
