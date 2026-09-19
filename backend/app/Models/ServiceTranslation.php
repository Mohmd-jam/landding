<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `service_translations`.
 */
final class ServiceTranslation extends Model
{
    protected static string $table = 'service_translations';
    protected static array $casts = ['features_json' => 'array'];
}
