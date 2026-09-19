<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `login_attempts`.
 */
final class LoginAttempt extends Model
{
    protected static string $table = 'login_attempts';
    protected static array $casts = ['successful' => 'bool'];
}
