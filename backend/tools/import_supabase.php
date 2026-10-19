<?php
declare(strict_types=1);

/**
 * CLI only: import data from the previous Supabase (Postgres) export.
 *
 * Source is an SQL dump in "INSERT INTO ..." plain text form (pg_dump style is fine).
 * It expects the tables/events structure of the legacy project:
 *   events, event_assignments, profiles(+users), donation_requests,
 *   activity types, locations, etc.
 *
 * Usage:
 *   php import_supabase.php <file.sql>              full import attempt
 *   php import_supabase.php --check <file.sql>      dry-run: only counts rows
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require __DIR__ . '/../src/bootstrap_cli.php';

use Mcg\Audit;
use Mcg\Database;

$argvPath = $argv[1] ?? '';
if (($argv[1] ?? '') === '--check') {
    $argvPath = $argv[2] ?? '';
}
if ($argvPath === '' || !is_file($argvPath)) {
    fwrite(STDERR, "Usage: php import_supabase.php [--check] <supabase_dump.sql>\n");
    exit(1);
}

/**
 * Parse "INSERT INTO <table> ..." statements into [table, columns[], valuesRows[]].
 * Handles quoted literals with '' escapes; E'\n' is unescaped.
 * @return list<array{string, list<string>, list<list<string|null>>}>
 */
function mcg_parse_inserts(string $sql): array
{
    $out = [];
    $stmts = preg_split('/;\s*\n/', $sql) ?: [];
    foreach ($stmts as $stmt) {
        if (!preg_match('/^INSERT\s+INTO\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?\s*\((.*?)\)\s*VALUES\s*(.*)$/is', trim($stmt), $m)) {
            continue;
        }
        [, $table, $colsRaw, $valuesRaw] = $m;
        $cols = array_map(static fn (string $c): string => trim(trim($c), "\"`"), preg_split('/,/', $colsRaw) ?: []);
        foreach (mcg_split_tuples($valuesRaw) as $tuple) {
            $out[] = [$table, $cols, $tuple];
        }
    }
    return $out;
}

/** @return list<list<string|null>> */
function mcg_split_tuples(string $raw): array
{
    $tuples = [];
    $depth = 0; $inStr = false; $esc = false; $cur = ''; $chars = [];
    $len = strlen($raw);
    for ($i = 0; $i < $len; $i++) {
        $ch = $raw[$i];
        if ($inStr) {
            $cur .= $ch;
            if ($esc) { $esc = false; continue; }
            if ($ch === 'E' && isset($raw[$i + 1]) && $raw[$i + 1] === '\'') { continue; } // E' marker
            if ($ch === '\\') { $esc = true; continue; }
            if ($ch === "'") {
                if (isset($raw[$i + 1]) && $raw[$i + 1] === "'") { $i++; $cur .= "'"; continue; }
                $inStr = false; $chars[] = mcg_unquote($cur) === null ? null : mcg_unquote($cur);
                $cur = '';
                continue;
            }
            continue;
        }
        if ($ch === "'") {
            // start of a quoted value that might follow a comma/space
            $prefix = preg_match('/[\'"]?\s*$/', $cur);
            $inStr = true; $cur .= $ch;
            continue;
        }
        if ($ch === '(') {
            $depth++; $cur = ''; $chars = []; continue;
        }
        if ($ch === ')' && $depth > 0) {
            $depth--;
            if (trim($cur) !== '' ) { $chars[] = trim($cur) === 'NULL' ? null : mcg_unquote($cur); }
            $tuples[] = $chars; $cur = ''; $chars = []; continue;
        }
        if ($ch === ',' && $depth > 0) {
            if (trim($cur) !== '') { $chars[] = trim($cur) === 'NULL' ? null : mcg_unquote($cur); }
            $cur = ''; continue;
        }
        $cur .= $ch;
    }
    $clean = [];
    foreach ($tuples as $t) {
        if (count($t) > 0 && !$t[0] instanceof \Closure) {
            $clean[] = $t;
        }
    }
    return $clean;
}

function mcg_unquote(string $s): ?string
{
    $s = trim($s);
    if ($s === '' ) { return null; }
    if ($s[0] === "'" && substr($s, -1) === "'") {
        $body = substr($s, 1, -1);
        $body = str_replace("''", "'", ltrim($body, 'E'));
        // postgres escapes
        $body = str_replace(['\\r', '\\n', '\\t', '\\"', '\\\\'], ["\r", "\n", "\t", '"', '\\'], $body);
        return $body;
    }
    if ($s[0] === '"' && substr($s, -1) === '"') {
        return substr($s, 1, -1);
    }
    return $s;
}

$countBy = [];
$rows = mcg_parse_inserts((string) file_get_contents($argvPath));
foreach ($rows as [$table, $cols, $vals]) {
    $countBy[$table] = ($countBy[$table] ?? 0) + 1;
}
if (($argv[1] ?? '') === '--check') {
    foreach ($countBy as $t => $c) {
        fwrite(STDOUT, "$t: $c rows\n");
    }
    exit(0);
}

/**
 * Map source table → INSERT statement on the new schema, with per-table hooks.
 * @return \Generator<array{string, array<string, mixed>}>
 */
function mcg_map_row(string $table, array $cols, array $vals): ?array
{
    $row = [];
    foreach ($cols as $i => $c) {
        $row[$c] = $vals[$i] ?? null;
    }
    $db = Database::conn();
    switch ($table) {
        case 'events':
            $id = $row['id'] ?? Database::uuid();
            $date = substr((string) ($row['activity_date'] ?? $row['event_date'] ?? ''), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                return null;
            }
            $time = null !== ($row['start_time'] ?? null) ? substr((string) $row['start_time'], 0, 8) : null;
            Database::run(
                'INSERT INTO events (id, event_date, start_time, meeting_point, volunteers_needed, is_published, is_demo)
                 VALUES (:i, :d, :s, :mp, :v, :pub, :demo)
                 ON DUPLICATE KEY UPDATE meeting_point = VALUES(meeting_point)',
                [':i' => $id, ':d' => $date, ':s' => $time,
                 ':mp' => $row['meeting_point'] ?? null,
                 ':v' => max(0, (int) ($row['volunteers_needed'] ?? 2)),
                 ':pub' => (int) (is_null($row['published'] ?? null) ? 1 : (int) ($row['published'] === true || $row['published'] === 't' ? 1 : 0)),
                 ':demo' => 0]
            );
            // title/description (de or single language) into German translation
            $title = $row['title'] ?? null;
            if ($title !== null && trim((string) $title) !== '') {
                Database::run(
                    'INSERT INTO event_translations (event_id, lang, title, description, extra_info)
                     VALUES (:e, "de", :t, :x1, :x2) ON DUPLICATE KEY UPDATE title = VALUES(title)',
                    [':e' => $id, ':t' => mb_substr((string) $title, 0, 200),
                     ':x1' => mb_substr((string) ($row['description'] ?? ''), 0, 4000) ?: null,
                     ':x2' => $row['extra_info'] ?? null]
                );
            }
            return ['events ' . $id];

        case 'event_assignments':
            $eventId = $row['event_id'] ?? null;
            if ($eventId === null) {
                return null;
            }
            $exists = Database::scalar('SELECT COUNT(*) FROM events WHERE id = :e', [':e' => $eventId]);
            if ((int) $exists === 0) {
                return null; // orphan assignment
            }
            $userId = $row['user_id'] ?? null;
            if ($userId !== null && (int) Database::scalar('SELECT COUNT(*) FROM users WHERE id = :u', [':u' => $userId]) === 0) {
                $userId = null;
            }
            Database::run(
                'INSERT INTO event_assignments (id, event_id, user_id, person_name, constraint_note)
                 VALUES (:i, :e, :u, :p, :c)',
                [':i' => $row['id'] ?? Database::uuid(), ':e' => $eventId, ':u' => $userId,
                 ':p' => mb_substr((string) ($row['person_name'] ?? 'Team'), 0, 120),
                 ':c' => mb_substr((string) ($row['constraint_note'] ?? ''), 0, 300) ?: null]
            );
            return ['assignments'];

        case 'donation_requests':
            $id = $row['id'] ?? Database::uuid();
            $typeSlug = (string) ($row['type'] ?? 'other');
            $typeId = (int) (Database::scalar('SELECT id FROM donation_types WHERE slug = :s', [':s' => $typeSlug])
                ?: Database::scalar('SELECT id FROM donation_types WHERE slug = "other"'));
            $status = (string) ($row['status'] ?? 'new');
            if (!in_array($status, ['new', 'contacted', 'confirmed', 'received', 'closed'], true)) {
                $status = 'new';
            }
            Database::run(
                'INSERT INTO donation_requests (id, full_name, email, phone, amount, type_id, contact_method, message, consent, status, created_at)
                 VALUES (:i, :n, :e, :p, :a, :t, :cm, :m, 1, :st, :ca)',
                [':i' => $id,
                 ':n' => mb_substr((string) ($row['full_name'] ?? 'Anonymous'), 0, 120),
                 ':e' => mb_substr((string) ($row['email'] ?? ''), 0, 200),
                 ':p' => $row['phone'] ?? null,
                 ':a' => $row['amount'] !== null ? (float) $row['amount'] : null,
                 ':t' => $typeId, ':cm' => in_array($row['contact_method'] ?? '', ['email', 'phone', 'whatsapp'], true) ? (string) $row['contact_method'] : 'email',
                 ':m' => $row['message'] ?? null, ':st' => $status,
                 ':ca' => $row['created_at'] ?? date('Y-m-d H:i:s')]
            );
            // convert received/closed pledges into the actual donations ledger
            if (in_array($status, ['received', 'closed'], true)) {
                Database::run(
                    'INSERT INTO donations (id, request_id, type_id, amount, description, received_on, recorded_by)
                     SELECT :id, :r, type_id, amount, "Spende: Import", COALESCE(DATE(created_at), CURDATE()), NULL
                     FROM donation_requests WHERE id = :r2 AND NOT EXISTS (SELECT 1 FROM donations WHERE request_id = :r3)',
                    [':id' => Database::uuid(), ':r' => $id, ':r2' => $id, ':r3' => $id]
                );
            }
            return ['donation_requests'];

        case 'profiles':
        case 'users':
            // user + profile live together; id/email/password_hash allowed
            $id = $row['id'] ?? $row['user_id'] ?? null;
            $email = $row['email'] ?? null;
            if ($id === null || $email === null) {
                return null;
            }
            $hash = $row['encrypted_password'] ?? $row['password_hash'] ?? null;
            if ($hash === null) {
                return null; // no usable credential
            }
            Database::run(
                'INSERT INTO users (id, email, password_hash, is_active) VALUES (:i, :e, :p, 1)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)',
                [':i' => (string) $id, ':e' => (string) $email, ':p' => (string) $hash]
            );
            $name = $row['display_name'] ?? $row['name'] ?? (string) $email;
            Database::run(
                'INSERT INTO profiles (user_id, display_name, phone) VALUES (:u, :d, :p)
                 ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)',
                [':u' => (string) $id, ':d' => mb_substr((string) $name, 0, 80), ':p' => $row['phone'] ?? null]
            );
            // legacy role column ("admin"/"staff"/"volunteer")
            $legacyRole = $row['role'] ?? null;
            if ($legacyRole !== null) {
                $slug = in_array($legacyRole, ['admin', 'staff', 'volunteer'], true) ? $legacyRole : 'volunteer';
                Database::run(
                    'INSERT IGNORE INTO user_roles (user_id, role_id) SELECT :u, id FROM roles WHERE slug = :r',
                    [':u' => (string) $id, ':r' => $slug]
                );
            }
            return ['profiles ' . $email];

        default:
            return null;
    }
}

$imported = 0;
$skipped = 0;
$started = microtime(true);
foreach ($rows as [$table, $cols, $vals]) {
    $res = mcg_map_row($table, $cols, $vals);
    if ($res === null) {
        $skipped++;
    } else {
        $imported++;
    }
}
Audit::logCli('supabase_import', null, null, ['imported' => $imported, 'skipped' => $skipped, 'file' => basename($argvPath)]);
fwrite(STDOUT, "Import finished: $imported rows imported, $skipped skipped, "
    . round(microtime(true) - $started, 1) . "s\n");
