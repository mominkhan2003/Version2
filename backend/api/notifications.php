<?php
declare(strict_types=1);

/**
 * backend/api/notifications.php — signed-in user notifications
 *   GET  → list (newest first, unread first, capped 50) + unread count
 *   POST action=mark_read {id?} — without id marks all read
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Auth;
use Mcg\Database;
use Mcg\Response;
use Mcg\Validate;

$me = Auth::requireAuth();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $rows = Database::all(
        'SELECT n.id, n.kind, n.title, n.link, n.read_at, n.created_at
         FROM notifications n
         WHERE n.user_id = :me
         ORDER BY n.read_at IS NULL DESC, n.created_at DESC LIMIT 50',
        [':me' => $me['userId']]
    );
    $unread = (int) Database::scalar(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :me AND read_at IS NULL',
        [':me' => $me['userId']]
    );
    Response::json([
        'ok' => true,
        'unread' => $unread,
        'items' => array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'kind' => (string) $r['kind'],
            'title' => (string) $r['title'],
            'link' => $r['link'] !== null ? (string) $r['link'] : null,
            'read' => $r['read_at'] !== null,
            'created_at' => substr((string) $r['created_at'], 0, 19),
        ], $rows),
    ]);
}

$body = Validate::jsonBody();
Validate::in($body, 'action', ['mark_read'], true);
$id = Validate::uuid($body, 'id');
if ($id !== null) {
    Database::run(
        'UPDATE notifications SET read_at = NOW() WHERE id = :i AND user_id = :u',
        [':i' => $id, ':u' => $me['userId']]
    );
} else {
    Database::run(
        'UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL AND user_id = :u',
        [':u' => $me['userId']]
    );
}
Response::json(['ok' => true]);
