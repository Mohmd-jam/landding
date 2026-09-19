<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Writes an append-only audit trail (admin actions) both to the log file and
 * to the database, so Security → Activity can show who changed what.
 */
final class AuditLogger
{
    public static function log(
        ?int $adminId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $context = []
    ): void {
        $entry = [
            'admin_id' => $adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'context' => $context,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        try {
            if (self::tableExists()) {
                Database::table('audit_logs')->insert([
                    'admin_id' => $adminId,
                    'action' => $action,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'ip' => $entry['ip'],
                    'context' => Json::encode($context),
                    'created_at' => $entry['created_at'],
                    'updated_at' => $entry['created_at'],
                ]);
            }
        } catch (\Throwable) {
            // Never let auditing break the request; the file log still records it.
        }

        Logger::info('audit:' . $action, $context + ['admin_id' => $adminId, 'entity' => $entityType . '#' . $entityId]);
    }

    private static function tableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            Database::table('audit_logs')->limit(1)->get();
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
    }
}
