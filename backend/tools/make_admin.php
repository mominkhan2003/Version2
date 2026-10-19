<?php
declare(strict_types=1);

/**
 * CLI only: promote or demote a user between admin / staff / volunteer.
 *
 * Usage:
 *   php tools/make_admin.php user@example.com admin
 *   php tools/make_admin.php user@example.com staff
 *   php tools/make_admin.php user@example.com volunteer
 *   php tools/make_admin.php user@example.com none        (removes all roles)
 *
 * Never expose this file through a web server (see DEPLOYMENT.md).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../src/bootstrap_cli.php';

use Mcg\Audit;
use Mcg\Database;

$email = strtolower(trim((string) ($argv[1] ?? '')));
$role = strtolower(trim((string) ($argv[2] ?? '')));
$allowed = ['admin', 'coordinator', 'volunteer', 'none'];

if ($email === '' || !in_array($role, $allowed, true)) {
    fwrite(STDERR, "Usage: php make_admin.php <email> <admin|staff|volunteer|none>\n");
    exit(1);
}

$user = Database::one('SELECT id FROM users WHERE email = :e', [':e' => $email]);
if ($user === null) {
    $pw = bin2hex(random_bytes(6));
    $id = Database::uuid();
    Database::run(
        'INSERT INTO users (id, email, password_hash, status) VALUES (:i, :e, :p, "active")',
        [':i' => $id, ':e' => $email, ':p' => password_hash($pw, PASSWORD_BCRYPT)]
    );
    Database::run(
        'INSERT INTO profiles (user_id, display_name) VALUES (:u, :d)',
        [':u' => $id, ':d' => ucfirst((string) preg_replace('/[^a-z0-9]+/i', ' ', explode('@', $email)[0]) ?: 'Team')]
    );
    $uid = $id;
    fwrite(STDOUT, "Created user $email with temporary password: $pw\n");
} else {
    $uid = (string) $user['id'];
}

Database::run('DELETE FROM user_roles WHERE user_id = :u', [':u' => $uid]);
if ($role !== 'none') {
    $n = Database::run(
        'INSERT INTO user_roles (user_id, role_id) SELECT :u, id FROM roles WHERE slug = :r',
        [':u' => $uid, ':r' => $role]
    );
    if ($n->rowCount() === 0) {
        fwrite(STDERR, "Role '$role' not found in roles table.\n");
        exit(1);
    }
}

Audit::logCli('cli_role_change', 'users', $uid, ['roles' => $role]);
fwrite(STDOUT, "OK: $email → $role\n");
