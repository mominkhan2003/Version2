<?php
declare(strict_types=1);

/**
 * backend/api/users_admin.php (admin only)
 *   GET  → team roster (users + profile + roles + activity)
 *   POST action=set_roles      {user_id, roles: [admin|staff|volunteer]}
 *        action=set_active     {user_id, is_active}
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Database;
use Mcg\Response;
use Mcg\Validate;

$me = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $users = Database::all(
        'SELECT u.id, u.email, u.status, u.created_at, u.last_login_at, p.display_name, p.phone
         FROM users u LEFT JOIN profiles p ON p.user_id = u.id
         ORDER BY u.created_at DESC'
    );
    $roles = Database::all('SELECT ur.user_id, r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id');
    $byUser = [];
    foreach ($roles as $r) {
        $byUser[$r['user_id']][] = (string) $r['slug'];
    }
    Response::json([
        'ok' => true,
        'roles' => Database::all('SELECT slug, label_de, label_en FROM roles'),
        'users' => array_map(static function (array $u) use ($byUser): array {
            $uid = (string) $u['id'];
            return [
                'id' => $uid,
                'email' => (string) $u['email'],
                'display_name' => $u['display_name'] !== null ? (string) $u['display_name'] : '',
                'phone' => $u['phone'] !== null ? (string) $u['phone'] : null,
                'is_active' => $u['status'] === 'active',
                'roles' => $byUser[$uid] ?? [],
                'created_at' => substr((string) $u['created_at'], 0, 19),
                'last_login' => $u['last_login_at'] !== null ? substr((string) $u['last_login_at'], 0, 19) : null,
            ];
        }, $users),
    ]);
}

$body = Validate::jsonBody();
$action = Validate::in($body, 'action', ['set_roles', 'set_active'], true);
$userId = Validate::uuid($body, 'user_id', true);
$target = Database::one('SELECT id, email, is_active FROM users WHERE id = :i', [':i' => $userId]);
if ($target === null) {
    Response::error('User not found.', 404);
}

if ($action === 'set_roles') {
    $wanted = $body['roles'] ?? null;
    if (!is_array($wanted)) {
        Response::error('Roles list required.', 422);
    }
    foreach ($wanted as $r) {
        if (!in_array($r, ['admin', 'coordinator', 'volunteer'], true)) {
            Response::error('Unsupported role: ' . (string) (is_string($r) ? $r : '?'), 422);
        }
    }
    $isAdmin = in_array('admin', $wanted, true);
    if ($isAdmin && $userId === $me['userId']) {
        Response::error('You cannot manage your own admin role here.', 422);
    }
    $db = Database::conn();
    $db->beginTransaction();
    try {
        Database::run('DELETE FROM user_roles WHERE user_id = :u', [':u' => $userId]);
        foreach ($wanted as $r) {
            /**
             * @var string $r
             */
            Database::run(
                'INSERT INTO user_roles (user_id, role_id) SELECT :u, id FROM roles WHERE slug = :r',
                [':u' => $userId, ':r' => $r]
            );
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    Audit::log('user_roles', 'users', $userId, ['roles' => $wanted]);
    Response::json(['ok' => true]);
}

// set_active
$active = Validate::bool($body, 'is_active', true) ?? true;
if ($userId === $me['userId'] && $active === false) {
    Response::error('You cannot deactivate your own account.', 422);
}
Database::run(
    'UPDATE users SET status = :a WHERE id = :i',
    [':a' => $active ? 'active' : 'inactive', ':i' => $userId]
);
Audit::log('user_active', 'users', $userId, ['active' => $active]);
Response::json(['ok' => true]);
