<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Table `messages`.
 */
final class Message extends Model
{
    protected static string $table = 'messages';
    protected static array $casts = ['is_read' => 'bool','is_starred' => 'bool','is_archived' => 'bool'];


    public static function inbox(array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $query = static::query();

        if (($filters['status'] ?? '') === 'unread') {
            $query->where('is_read', 0);
        } elseif (($filters['status'] ?? '') === 'starred') {
            $query->where('is_starred', 1);
        } elseif (($filters['status'] ?? '') === 'archived') {
            $query->where('is_archived', 1);
        } else {
            $query->where('is_archived', 0);
        }

        if (!empty($filters['search'])) {
            $query->whereLikeAny(['name', 'email', 'subject', 'message'], (string) $filters['search']);
        }

        return $query->orderBy('created_at', 'DESC')->paginate($perPage, $page);
    }
}
