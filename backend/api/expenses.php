<?php
declare(strict_types=1);

/**
 * backend/api/expenses.php (admin/staff only)
 *   GET  ?from&to&category&page → filtered, paginated expense list (+categories)
 *   POST action=create|update|delete
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Database;
use Mcg\Response;
use Mcg\Validate;

Auth::requireStaff();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 25;
    $where = ['1 = 1'];
    $params = [];
    foreach ([['from', 'e.spent_on >= :f'], ['to', 'e.spent_on <= :t']] as [$key, $cond]) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$key] ?? ''))) {
            $where[] = $cond;
            $params[":$key"] = (string) $_GET[$key];
        }
    }
    if (preg_match('/^\d+$/', (string) ($_GET['category'] ?? ''))) {
        $where[] = 'e.category_id = :c';
        $params[':c'] = (int) $_GET['category'];
    }
    $whereSql = implode(' AND ', $where);
    $total = (int) Database::scalar("SELECT COUNT(*) FROM expenses e WHERE $whereSql", $params);
    $sum = (float) Database::scalar("SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE $whereSql", $params);
    $rows = Database::all(
        "SELECT e.*, c.slug AS category FROM expenses e JOIN expense_categories c ON c.id = e.category_id
         WHERE $whereSql ORDER BY e.spent_on DESC, e.created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
        $params
    );
    Response::json([
        'ok' => true, 'total' => $total, 'page' => $page, 'sum' => round($sum, 2),
        'categories' => Database::all('SELECT id, slug, label_de, label_en FROM expense_categories ORDER BY slug'),
        'expenses' => array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'spent_on' => (string) $r['spent_on'],
            'category_id' => (int) $r['category_id'],
            'category' => (string) $r['category'],
            'amount' => (float) $r['amount'],
            'supplier' => $r['supplier'] !== null ? (string) $r['supplier'] : null,
            'description' => $r['description'] !== null ? (string) $r['description'] : null,
            'created_at' => substr((string) $r['created_at'], 0, 19),
        ], $rows),
    ]);
}

$body = Validate::jsonBody();
$action = Validate::in($body, 'action', ['create', 'update', 'delete'], true);

function mcg_expense_data(array $body, bool $creating): array
{
    return [
        'spent_on'    => Validate::date($body, 'spent_on', $creating),
        'category_id' => (int) (Validate::num($body, 'category_id', 1, 100000) ?? 0),
        'amount'      => Validate::num($body, 'amount', 0, 1000000, $creating),
        'supplier'    => Validate::str($body, 'supplier', 120),
        'description' => Validate::str($body, 'description', 500),
    ];
}

if ($action === 'create') {
    $fields = mcg_expense_data($body, true);
    if ((int) $fields['category_id'] === 0 || $fields['amount'] === null) {
        Response::error('Category and amount are required.', 422);
    }
    if ($fields['spent_on'] === null) {
        $fields['spent_on'] = date('Y-m-d');
    }
    $id = Database::uuid();
    Database::run(
        'INSERT INTO expenses (id, spent_on, category_id, amount, supplier, description, recorded_by)
         VALUES (:id, :d, :c, :a, :s, :desc, :u)',
        [':id' => $id, ':d' => $fields['spent_on'], ':c' => $fields['category_id'], ':a' => $fields['amount'],
         ':s' => $fields['supplier'], ':desc' => $fields['description'], ':u' => Auth::me()['userId']]
    );
    Audit::log('expense_create', 'expenses', $id, ['amount' => $fields['amount']]);
    Response::json(['ok' => true, 'id' => $id]);
}

$id = Validate::uuid($body, 'id', true);
$row = Database::one('SELECT id FROM expenses WHERE id = :i', [':i' => $id]);
if ($row === null) {
    Response::error('Expense not found.', 404);
}
if ($action === 'update') {
    $fields = mcg_expense_data($body, false);
    $sets = [];
    $params = [':i' => $id];
    if ($fields['spent_on'] !== null) {
        $sets[] = 'spent_on = :d';
        $params[':d'] = $fields['spent_on'];
    }
    if ($fields['category_id'] !== 0) {
        $sets[] = 'category_id = :c';
        $params[':c'] = $fields['category_id'];
    }
    if ($fields['amount'] !== null) {
        $sets[] = 'amount = :a';
        $params[':a'] = $fields['amount'];
    }
    if (array_key_exists('supplier', $body)) {
        $sets[] = 'supplier = :s';
        $params[':s'] = $fields['supplier'];
    }
    if (array_key_exists('description', $body)) {
        $sets[] = 'description = :desc';
        $params[':desc'] = $fields['description'];
    }
    if ($sets === []) {
        Response::error('Nothing to update.', 400);
    }
    Database::run('UPDATE expenses SET ' . implode(', ', $sets) . ' WHERE id = :i', $params);
    Audit::log('expense_update', 'expenses', $id);
    Response::json(['ok' => true]);
}

Database::run('DELETE FROM expenses WHERE id = :i', [':i' => $id]);
Audit::log('expense_delete', 'expenses', $id);
Response::json(['ok' => true]);
