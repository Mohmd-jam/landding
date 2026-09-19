<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `password_resets`.
 */
final class PasswordReset extends Model
{
    protected static string $table = 'password_resets';
}
