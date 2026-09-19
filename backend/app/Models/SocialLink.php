<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `social_links`.
 */
final class SocialLink extends Model
{
    protected static string $table = 'social_links';
    protected static array $casts = ['is_active' => 'bool','sort_order' => 'int'];
}
