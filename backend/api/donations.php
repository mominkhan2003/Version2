<?php
declare(strict_types=1);

/**
 * backend/api/donations.php
 *   POST action=new_request      (public, rate limited, no session needed)
 *   GET  ?list=&status=&from=&to=&page=  (admin only) — donor data stays here
 *   POST action=set_status|save_notes|delete  (admin only)
 *   GET  action report?from&to   (admin) — date-filtered totals
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Database;
use Mcg\RateLimit;
use Mcg\Response;
use Mcg\Validate;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$contentTypeJson = str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');

if ($method === 'POST') {
    // both JSON and multipart are accepted; public, CSRF protected, rate limited
    $body = $contentTypeJson ? Validate::jsonBody() : $_POST;
} else {
    $body = [];
}
if ($method === 'POST' && (($body['action'] ?? '') === 'new_request')) {
    if ($body === []) {
        Response::error('Empty request.', 400);
    }
    $name = Validate::str($body, 'full_name', 120, true);
    $email = Validate::email($body);
    $phone = Validate::str($body, 'phone', 40);
    $amount = Validate::num($body, 'amount', 0, 1000000);
    $typeSlug = Validate::in($body, 'donation_type', ['money', 'food', 'ingredients', 'equipment', 'other'], true);
    $contact = Validate::in($body, 'contact_method', ['email', 'phone', 'whatsapp'], true);
    $message = Validate::str($body, 'message', 2000);
    $consent = Validate::bool($body, 'consent', false);
    if ($consent !== true) {
        Response::error('Please accept the privacy notice.', 422, ['consent' => 'consent']);
    }
    // rate limit: 5 submissions per IP+e-mail per hour
    if (!RateLimit::hit('donation', $email, 5, 3600)) {
        Response::error('Too many requests. Please try again later.', 429);
    }
    $type = Database::one('SELECT id FROM donation_types WHERE slug = :s', [':s' => $typeSlug]);
    if ($type === null) {
        Response::error('Unsupported donation type.', 422);
    }
    $id = Database::uuid();
    Database::run(
        'INSERT INTO donation_requests (id, full_name, email, phone, amount, type_id, contact_method, message, consent, status)
         VALUES (:id, :n, :e, :p, :a, :t, :cm, :m, :c, "new")',
        [':id' => $id, ':n' => $name, ':e' => $email, ':p' => $phone, ':a' => $amount,
         ':t' => (int) $type['id'], ':cm' => $contact, ':m' => $message, ':c' => 1]
    );
    // admin notifications (broadcast)
    $admins = Database::all('SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = "admin"');
    foreach ($admins as $a) {
        Database::run(
            'INSERT INTO notifications (id, kind, title, link, user_id) VALUES (:id, "donation", :t, "/teams/admin/donations", :u)',
            [':id' => Database::uuid(), ':t' => 'Neue Spendenanfrage: ' . $name, ':u' => $a['user_id']]
        );
    }
    Response::json(['ok' => true]);
}

// above this line everything after is admin-only
$me = Auth::requireAdmin();

// ------------------------------- GET: list / report --------------------------
if ($method === 'GET') {
    $action = (string) ($_GET['action'] ?? 'list');
    if ($action === 'report') {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '2000-01-01';
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '2999-12-31';
        $received = (float) Database::scalar(
            'SELECT COALESCE(SUM(d.amount),0) FROM donations d
             JOIN donation_types t ON t.id = d.type_id AND t.slug = "money"
             WHERE d.received_on BETWEEN :f AND :t', [':f' => $from, ':t' => $to]
        );
        $spent = (float) Database::scalar(
            'SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.spent_on BETWEEN :f AND :t', [':f' => $from, ':t' => $to]
        );
        $donationsRows = Database::all(
            'SELECT d.id, d.received_on, d.amount, d.description, t.slug AS type
             FROM donations d JOIN donation_types t ON t.id = d.type_id
             WHERE d.received_on BETWEEN :f AND :t ORDER BY d.received_on DESC LIMIT 200', [':f' => $from, ':t' => $to]
        );
        $expensesRows = Database::all(
            'SELECT e.id, e.spent_on, e.amount, e.description, e.supplier, c.slug AS category
             FROM expenses e JOIN expense_categories c ON c.id = e.category_id
             WHERE e.spent_on BETWEEN :f AND :t ORDER BY e.spent_on DESC LIMIT 200', [':f' => $from, ':t' => $to]
        );
        Response::json([
            'ok' => true,
            'from' => $from, 'to' => $to,
            'received' => round($received, 2),
            'spent' => round($spent, 2),
            'available' => round($received - $spent, 2),
            'donations' => $donationsRows,
            'expenses' => $expensesRows,
        ]);
    }
    $status = (string) ($_GET['status'] ?? '');
    $search = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $where = ['1 = 1'];
    $params = [];
    if (in_array($status, ['new', 'contacted', 'confirmed', 'received', 'closed'], true)) {
        $where[] = 'r.status = :s';
        $params[':s'] = $status;
    }
    if ($search !== '') {
        $where[] = '(r.full_name LIKE :q OR r.email LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
    $whereSql = implode(' AND ', $where);
    $total = (int) Database::scalar("SELECT COUNT(*) FROM donation_requests r WHERE $whereSql", $params);
    $rows = Database::all(
        "SELECT r.*, t.slug AS type_slug, t.label_de AS type_label
         FROM donation_requests r JOIN donation_types t ON t.id = r.type_id
         WHERE $whereSql ORDER BY r.created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
        $params
    );
    Response::json([
        'ok' => true, 'total' => $total, 'page' => $page,
        'requests' => array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'full_name' => (string) $r['full_name'],
            'email' => (string) $r['email'],
            'phone' => $r['phone'] !== null ? (string) $r['phone'] : null,
            'amount' => $r['amount'] !== null ? (float) $r['amount'] : null,
            'type' => (string) $r['type_slug'],
            'type_label' => (string) $r['type_label'],
            'contact_method' => (string) $r['contact_method'],
            'message' => $r['message'] !== null ? (string) $r['message'] : null,
            'status' => (string) $r['status'],
            'internal_notes' => $r['internal_notes'] !== null ? (string) $r['internal_notes'] : null,
            'is_demo' => (bool) $r['is_demo'],
            'created_at' => substr((string) $r['created_at'], 0, 19),
        ], $rows),
    ]);
}

// ---------------------------------- POST admin -------------------------------
$body = Validate::jsonBody();
$action = Validate::in($body, 'action', ['set_status', 'save_notes', 'delete'], true);
$id = Validate::uuid($body, 'id', true);
$row = Database::one('SELECT * FROM donation_requests WHERE id = :i', [':i' => $id]);
if ($row === null) {
    Response::error('Donation request not found.', 404);
}

if ($action === 'set_status') {
    /** @var string $status */
    $status = Validate::in($body, 'status', ['new', 'contacted', 'confirmed', 'received', 'closed'], true);
    $db = Database::conn();
    $db->beginTransaction();
    try {
        Database::run('UPDATE donation_requests SET status = :s WHERE id = :i', [':s' => $status, ':i' => $id]);
        // "received" creates the actual received donation entry (pledges stay in requests)
        if ($status === 'received' && $row['status'] !== 'received') {
            $exists = Database::scalar('SELECT COUNT(*) FROM donations WHERE request_id = :r', [':r' => $id]);
            if ((int) $exists === 0) {
                // description stays neutral — no donor data in the public table
                Database::run(
                    'INSERT INTO donations (id, request_id, type_id, amount, description, received_on, recorded_by)
                     SELECT :id, :r, r.type_id, r.amount, CONCAT("Spende: ", t.label_de), CURDATE(), :u
                     FROM donation_requests r JOIN donation_types t ON t.id = r.type_id WHERE r.id = :r2',
                    [':id' => Database::uuid(), ':r' => $id, ':u' => $me['userId'], ':r2' => $id]
                );
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    Audit::log('donation_status', 'donation_requests', $id, ['to' => $status]);
    Response::json(['ok' => true]);
}

if ($action === 'save_notes') {
    Database::run('UPDATE donation_requests SET internal_notes = :n WHERE id = :i', [
        ':n' => Validate::str($body, 'internal_notes', 2000), ':i' => $id,
    ]);
    Audit::log('donation_notes', 'donation_requests', $id);
    Response::json(['ok' => true]);
}

Database::run('DELETE FROM donation_requests WHERE id = :i', [':i' => $id]);
Audit::log('donation_delete', 'donation_requests', $id);
Response::json(['ok' => true]);
