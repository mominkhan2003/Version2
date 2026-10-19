<?php
declare(strict_types=1);

namespace Mcg;

/** Audit trail for sensitive admin operations. */
final class Audit
{
    /** @param mixed $details */
    public static function log(string $action, ?string $entity = null, ?string $entityId = null, mixed $details = null): void
    {
        $me = Auth::me();
        try {
            Database::run(
                'INSERT INTO audit_logs (id, actor_id, action, entity, entity_id, details, ip_hash)
                 VALUES (:id, :a, :ac, :e, :eid, :d, :ip)',
                [
                    ':id'  => Database::uuid(),
                    ':a'   => $me['userId'] ?? null,
                    ':ac'  => $action,
                    ':e'   => $entity,
                    ':eid' => $entityId,
                    ':d'   => $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                    ':ip'  => RateLimit::ipHash(),
                ]
            );
        } catch (\Throwable $e) {
            error_log('[mcg] audit failed: ' . $e->getMessage()); // never break the request because of logging
        }
    }

    /** CLI variant without session/IP context. */
    public static function logCli(string $action, ?string $entity = null, ?string $entityId = null, mixed $details = null): void
    {
        try {
            Database::run(
                'INSERT INTO audit_logs (id, actor_id, action, entity, entity_id, details)
                 VALUES (:id, NULL, :ac, :e, :eid, :d)',
                [
                    ':id'  => Database::uuid(),
                    ':ac'  => $action,
                    ':e'   => $entity,
                    ':eid' => $entityId,
                    ':d'   => $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (\Throwable $e) {
            error_log('[mcg] audit failed: ' . $e->getMessage());
        }
    }
}
