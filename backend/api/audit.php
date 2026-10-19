<?php
declare(strict_types=1);

/**
 * backend/api/audit.php (admin only)
 *   GET ?page=&action=&entity= → paginated audit trail
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Auth;
use Mcg\Database;
use Mcg\Response;

Auth::requireAdmin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$where = ['1 = 1'];
$params = [];
if (preg_match('/^[a-z_]{1,60}$/', (string) ($_GET['action'] ?? ''))) {
    $where[] = 'a.action = :a';
    $params[':a'] = (string) $_GET['action'];
}
if (preg_match('/^[a-z_]{1,60}$/', (string) ($_GET['entity'] ?? ''))) {
    $where[] = 'a.entity = :e';
    $params[':e'] = (string) $_GET['entity'];
}
$whereSql = implode(' AND ', $where);
$total = (int) Database::scalar("SELECT COUNT(*) FROM audit_logs a WHERE $whereSql", $params);
$rows = Database::all(
    "SELECT a.id, a.action, a.entity, a.entity_id, a.details, a.created_at,
            u.email AS actor_email, p.display_name AS actor_name
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.actor_id
     LEFT JOIN profiles p ON p.user_id = a.actor_id
     WHERE $whereSql ORDER BY a.created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
Response::json([
    'ok' => true,
    'total' => $total,
    'page' => $page,
    'items' => array_map(static fn (array $r): array => [
        'id' => (string) $r['id'],
        'action' => (string) $r['action'],
        'entity' => $r['entity'] !== null ? (string) $r['entity'] : null,
        'entity_id' => $r['entity_id'] !== null ? (string) $r['entity_id'] : null,
        'details' => $r['details'] !== null ? json_decode((string) $r['details'], true) : null,
        'actor' => $r['actor_email'] !== null ? (string) ($r['actor_name'] !== null ? $r['actor_name'] : $r['actor_email']) : null,
        'created_at' => substr((string) $r['created_at'], 0, 19),
    ], $rows),
]);
