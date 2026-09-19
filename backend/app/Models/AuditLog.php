<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `audit_logs`.
 */
final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
    protected static array $casts = ['context' => 'json'];
}
