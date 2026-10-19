<?php
declare(strict_types=1);

/**
 * backend/api/events.php
 *   GET  → staff: full event list (incl. unpublished) with DE/EN translations + roster
 *   POST → action: create|update|duplicate|delete|publish|unpublish|review_clear
 *                  |assignment_add|assignment_remove|assignment_update
 * Members may only add/remove their own assignment (self-service), staff may manage all.
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Cache;
use Mcg\Database;
use Mcg\Media;
use Mcg\Response;
use Mcg\Validate;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $me = Auth::requireStaff();
    $rows = Database::all(
        'SELECT e.*, l.name AS location, c.slug AS activity_type, m.replaced_at AS cover_replaced
         FROM events e
         LEFT JOIN event_locations l ON l.id = e.location_id
         LEFT JOIN activity_categories c ON c.id = e.activity_category_id
         LEFT JOIN media_assets m ON m.id = e.cover_media_id
         ORDER BY e.event_date, e.start_time'
    );
    $tx = Database::all('SELECT event_id, lang, title, description, extra_info FROM event_translations');
    $txBy = [];
    foreach ($tx as $t) {
        $txBy[$t['event_id']][$t['lang']] = $t;
    }
    $roster = Database::all(
        'SELECT a.id, a.event_id, a.user_id, a.person_name, a.constraint_note, a.created_at
         FROM event_assignments a ORDER BY a.created_at'
    );
    $rosterBy = [];
    foreach ($roster as $a) {
        $rosterBy[(string) $a['event_id']][] = [
            'id' => (string) $a['id'],
            'user_id' => $a['user_id'] !== null ? (string) $a['user_id'] : null,
            'person_name' => (string) $a['person_name'],
            'constraint_note' => $a['constraint_note'] !== null ? (string) $a['constraint_note'] : null,
            'created_at' => (string) $a['created_at'],
        ];
    }
    $cat = Database::all('SELECT slug FROM activity_categories');
    Response::json([
        'ok' => true,
        'locations' => Database::all('SELECT id, slug, name, address, city FROM event_locations WHERE is_deleted = 0 ORDER BY name'),
        'activities' => array_map(static fn (array $r): string => (string) $r['slug'], $cat),
        'events' => array_map(static function (array $r) use ($txBy, $rosterBy): array {
            $de = $txBy[$r['id']]['de'] ?? null;
            $en = $txBy[$r['id']]['en'] ?? null;
            return [
                'id'             => (string) $r['id'],
                'event_date'     => (string) $r['event_date'],
                'start_time'     => $r['start_time'] !== null ? substr((string) $r['start_time'], 0, 5) : null,
                'end_time'       => $r['end_time'] !== null ? substr((string) $r['end_time'], 0, 5) : null,
                'location_id'    => $r['location_id'] !== null ? (int) $r['location_id'] : null,
                'location'       => $r['location'] !== null ? (string) $r['location'] : null,
                'meeting_point'  => $r['meeting_point'],
                'activity_type'  => $r['activity_type'] !== null ? (string) $r['activity_type'] : 'other',
                'volunteers_needed' => $r['volunteers_needed'],
                'title_de'       => $de['title'] ?? '',
                'title_en'       => $en['title'] ?? null,
                'description_de' => $de['description'] ?? null,
                'description_en' => $en['description'] ?? null,
                'extra_info_de'  => $de['extra_info'] ?? null,
                'extra_info_en'  => $en['extra_info'] ?? null,
                'cover_media_id' => $r['cover_media_id'],
                'cover_url'      => $r['cover_media_id'] !== null
                    ? Media::url((string) $r['cover_media_id'], $r['cover_replaced'] !== null ? (string) $r['cover_replaced'] : null)
                    : null,
                'status'         => (string) $r['status'],
                'published'      => (bool) $r['is_published'],
                'needs_review'   => (bool) $r['needs_review'],
                'review_note'    => $r['review_note'],
                'internal_notes' => $r['internal_notes'],
                'is_demo'        => (bool) $r['is_demo'],
                'roster'         => $rosterBy[(string) $r['id']] ?? [],
            ];
        }, $rows),
    ]);
}

// ---------------------------------------------------------------- POST
$me = Auth::me();
$isStaff = $me !== null && $me['isStaff'];

$body = Validate::jsonBody();
$action = Validate::in($body, 'action', [
    'create', 'update', 'duplicate', 'delete', 'publish', 'unpublish', 'review_clear',
    'assignment_add', 'assignment_remove', 'assignment_update',
], true);

// member actions (self-service), everything else needs staff
if (!$isStaff) {
    if (!in_array($action, ['assignment_add', 'assignment_remove'], true)) {
        Response::error('Forbidden. Staff role required.', 403);
    }
}

/** Ensure a media id references an existing asset. */
function mcg_media_ok(?string $id): ?string
{
    if ($id === null) {
        return null;
    }
    $ok = Database::scalar('SELECT COUNT(*) FROM media_assets WHERE id = :i AND is_deleted = 0', [':i' => $id]);
    if ((int) $ok === 0) {
        Response::error('Unknown media asset.', 422, ['cover_media_id' => 'unknown']);
    }
    return $id;
}

/** Validate the whole event body once and return normalized fields. @return array<string,mixed> */
function mcg_event_fields(array $body, bool $creating): array
{
    $date = Validate::date($body, 'event_date', $creating);
    $fields = [];
    if ($date !== null) {
        $fields['event_date'] = $date;
    }
    $fields['start_time']       = Validate::time($body, 'start_time');
    $fields['end_time']         = Validate::time($body, 'end_time');
    $fields['meeting_point']    = Validate::str($body, 'meeting_point', 150);
    $fields['volunteers_needed'] = Validate::posInt($body, 'volunteers_needed');
    $fields['internal_notes']   = Validate::str($body, 'internal_notes', 2000);
    $locId = Validate::num($body, 'location_id', 1, 100000);
    if ($locId !== null) {
        $fields['location_id'] = (int) $locId;
    } else {
        // free-text location name — matches by name (case-insensitive), creates the row when new
        $locName = Validate::str($body, 'location', 120);
        if ($locName !== null) {
            $locName = trim($locName);
            if ($locName === '') {
                $fields['location_id'] = null;
            } else {
                $lid = Database::scalar('SELECT id FROM event_locations WHERE LOWER(name) = LOWER(:n)', [':n' => $locName]);
                if ($lid === false || $lid === null) {
                    // slug is UNIQUE — derive it from the name and add a counter suffix when taken
                    $baseSlug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($locName)), '-') ?: 'ort';
                    $baseSlug = mb_substr($baseSlug, 0, 50);
                    $slug = $baseSlug;
                    for ($n = 2;; $n++) {
                        if (Database::scalar('SELECT COUNT(*) FROM event_locations WHERE slug = :s', [':s' => $slug]) == 0) {
                            break;
                        }
                        $slug = $baseSlug . '-' . $n;
                    }
                    Database::run(
                        'INSERT INTO event_locations (slug, name) VALUES (:s, :n)',
                        [':s' => $slug, ':n' => mb_substr($locName, 0, 120)]
                    );
                    $lid = Database::conn()->lastInsertId();
                }
                $fields['location_id'] = (int) $lid;
            }
        }
    }
    $cat = Validate::in($body, 'activity_type', ['shopping','cooking','preparation','delivery','serving','cleaning','dishwashing','photography','video','editing','other']);
    $fields['activity_category_id'] = $cat !== null
        ? (int) Database::scalar('SELECT id FROM activity_categories WHERE slug = :s', [':s' => $cat])
        : null;
    $fields['cover_media_id']   = mcg_media_ok(Validate::uuid($body, 'cover_media_id'));
    $fields['status']           = Validate::in($body, 'status', ['planned', 'open', 'in_progress', 'completed', 'cancelled']);
    if (isset($body['needs_review'])) {
        $fields['needs_review'] = (int) (Validate::bool($body, 'needs_review', true) ?? false);
    }
    if (array_key_exists('review_note', $body)) {
        $fields['review_note'] = Validate::str($body, 'review_note', 500);
    }
    if (isset($body['published']) || $creating) {
        $fields['is_published'] = (int) (Validate::bool($body, 'published', true) ?? true);
    }
    return $fields;
}

/** Upsert a translation row. Only non-null fields are updated ("" clears). */
function mcg_upsert(string $eventId, string $lang, ?string $title, ?string $description, ?string $extra): void
{
    $row = Database::one('SELECT lang FROM event_translations WHERE event_id = :e AND lang = :l', [':e' => $eventId, ':l' => $lang]);
    if ($row === null) {
        $titleFinal = $title;
        if ($titleFinal === null && $lang !== 'de') {
            // fall back to the German title for new English rows
            $titleFinal = Database::scalar(
                'SELECT title FROM event_translations WHERE event_id = :e AND lang = "de"',
                [':e' => $eventId]
            ) ?: null;
        }
        if ($titleFinal === null) {
            return; // nothing to insert yet (title is required for new rows)
        }
        Database::run(
            'INSERT INTO event_translations (event_id, lang, title, description, extra_info) VALUES (:e, :l, :t, :d, :x)',
            [':e' => $eventId, ':l' => $lang, ':t' => $titleFinal, ':d' => $description, ':x' => $extra]
        );
        return;
    }
    $set = [];
    $params = [':e' => $eventId, ':l' => $lang];
    if ($title !== null) {
        $set[] = 'title = :t';
        $params[':t'] = $title;
    }
    if ($description !== null) {
        $set[] = 'description = :d';
        $params[':d'] = $description;
    }
    if ($extra !== null) {
        $set[] = 'extra_info = :x';
        $params[':x'] = $extra === '' ? null : $extra; // clear via ""
    }
    if ($set === []) {
        return;
    }
    Database::run('UPDATE event_translations SET ' . implode(', ', $set) . ' WHERE event_id = :e AND lang = :l', $params);
}

$db = Database::conn();

switch ($action) {
    case 'create': {
        $meS = Auth::requireStaff();
        $fields = mcg_event_fields($body, true);
        $titleDe = Validate::str($body, 'title_de', 200, true);
        $id = Database::uuid();
        $db->beginTransaction();
        try {
            Database::run(
                'INSERT INTO events (id, ' . implode(', ', array_keys($fields)) . ', timezone)
                 VALUES (:id, :' . implode(', :', array_keys($fields)) . ', "Europe/Vienna")',
                array_merge([':id' => $id], array_combine(array_map(static fn ($k) => ':' . $k, array_keys($fields)), array_values($fields)))
            );
            mcg_upsert($id, 'de', $titleDe, Validate::str($body, 'description_de', 4000), Validate::str($body, 'extra_info_de', 1000));
            mcg_upsert($id, 'en', Validate::str($body, 'title_en', 200), Validate::str($body, 'description_en', 4000), Validate::str($body, 'extra_info_en', 1000));
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        if ($fields['is_published'] ?? false) {
            Cache::bump();
        }
        Audit::log('event_create', 'events', $id, ['title' => $titleDe]);
        Response::json(['ok' => true, 'id' => $id]);
    }

    case 'update': {
        $meS = Auth::requireStaff();
        $id = Validate::uuid($body, 'id', true);
        $fields = mcg_event_fields($body, false);
        if ($fields === [] && !isset($body['title_de']) && !isset($body['title_en']) && !isset($body['description_de']) && !isset($body['description_en']) && !isset($body['extra_info_de']) && !isset($body['extra_info_en'])) {
            Response::error('Nothing to update.', 400);
        }
        $db->beginTransaction();
        try {
            if ($fields !== []) {
                $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($fields)));
                $params = array_combine(array_map(static fn ($k) => ':' . $k, array_keys($fields)), array_values($fields));
                $params[':id'] = $id;
                Database::run("UPDATE events SET $sets WHERE id = :id", $params);
            }
            mcg_upsert($id, 'de', Validate::str($body, 'title_de', 200), Validate::str($body, 'description_de', 4000), Validate::str($body, 'extra_info_de', 1000));
            mcg_upsert($id, 'en', Validate::str($body, 'title_en', 200), Validate::str($body, 'description_en', 4000), Validate::str($body, 'extra_info_en', 1000));
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        Cache::bump();
        Audit::log('event_update', 'events', $id, ['fields' => array_keys($fields)]);
        Response::json(['ok' => true]);
    }

    case 'duplicate':
    case 'delete':
    case 'publish':
    case 'unpublish':
    case 'review_clear': {
        $meS = Auth::requireStaff();
        $id = Validate::uuid($body, 'id', true);
        $exists = Database::one('SELECT id, is_published FROM events WHERE id = :i', [':i' => $id]);
        if ($exists === null) {
            Response::error('Event not found.', 404);
        }
        if ($action === 'duplicate') {
            $newId = Database::uuid();
            $db->beginTransaction();
            try {
                Database::run(
                    'INSERT INTO events (id, event_date, start_time, end_time, timezone, location_id, meeting_point, activity_category_id,
                                          volunteers_needed, cover_media_id, status, is_published, needs_review, review_note, internal_notes, is_demo)
                     SELECT :n, event_date, start_time, end_time, timezone, location_id, meeting_point, activity_category_id,
                            volunteers_needed, cover_media_id, status, 0, needs_review, review_note, internal_notes, is_demo
                     FROM events WHERE id = :o',
                    [':n' => $newId, ':o' => $id]
                );
                Database::run(
                    'INSERT INTO event_translations (event_id, lang, title, description, extra_info)
                     SELECT :n, lang, CONCAT(title, " (Kopie)"), description, extra_info FROM event_translations WHERE event_id = :o',
                    [':n' => $newId, ':o' => $id]
                );
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            Audit::log('event_duplicate', 'events', $newId, ['from' => $id]);
            Response::json(['ok' => true, 'id' => $newId]);
        }
        if ($action === 'delete') {
            Database::run('DELETE FROM events WHERE id = :i', [':i' => $id]);
            Cache::bump();
            Audit::log('event_delete', 'events', $id);
            Response::json(['ok' => true]);
        }
        if ($action === 'publish') {
            Database::run('UPDATE events SET is_published = 1 WHERE id = :i', [':i' => $id]);
            Cache::bump();
        }
        if ($action === 'unpublish') {
            Database::run('UPDATE events SET is_published = 0 WHERE id = :i', [':i' => $id]);
            Cache::bump();
        }
        if ($action === 'review_clear') {
            Database::run('UPDATE events SET needs_review = 0, review_note = NULL WHERE id = :i', [':i' => $id]);
        }
        Audit::log('event_' . $action, 'events', $id);
        Response::json(['ok' => true]);
    }

    case 'assignment_add': {
        $id = Validate::uuid($body, 'event_id', true);
        $event = Database::one('SELECT id, is_published, status FROM events WHERE id = :i', [':i' => $id]);
        if ($event === null) {
            Response::error('Event not found.', 404);
        }
        $note = Validate::str($body, 'constraint_note', 300);
        if ($isStaff) {
            $userId = Validate::uuid($body, 'user_id');
            $personName = Validate::str($body, 'person_name', 120);
            if ($personName === null && $userId === null) {
                Response::error('Choose a person or a team member.', 422, ['person_name' => 'required']);
            }
            if ($userId === null && $personName === null) {
                Response::error('Missing person.', 422);
            }
            $dup = Database::scalar(
                'SELECT COUNT(*) FROM event_assignments WHERE event_id = :e AND (user_id = :u OR (user_id IS NULL AND person_name = :p AND :p IS NOT NULL))',
                [':e' => $id, ':u' => $userId, ':p' => $personName]
            );
            if ((int) $dup > 0) {
                Response::error('This person is already assigned to this shift.', 409);
            }
            Database::run(
                'INSERT INTO event_assignments (id, event_id, user_id, person_name, user_asigned_by, constraint_note)
                 VALUES (:id, :e, :u, :p, :by, :c)',
                [':id' => Database::uuid(), ':e' => $id, ':u' => $userId,
                 ':p' => $personName ?? (string) (Database::scalar('SELECT display_name FROM profiles WHERE user_id = :i', [':i' => $userId]) ?? 'Team'),
                 ':by' => $me['userId'], ':c' => $note]
            );
            Audit::log('assignment_add', 'event_assignments', $id, ['user' => $userId, 'person' => $personName]);
        } else {
            // member self-signup: only themselves, only published & open events
            if ((int) $event['is_published'] !== 1 || $event['status'] !== 'open') {
                Response::error('This shift is not open for sign-up.', 409);
            }
            $dup = Database::scalar('SELECT COUNT(*) FROM event_assignments WHERE event_id = :e AND user_id = :u', [':e' => $id, ':u' => $me['userId']]);
            if ((int) $dup > 0) {
                Response::error('You are already assigned.', 409);
            }
            Database::run(
                'INSERT INTO event_assignments (id, event_id, user_id, person_name, constraint_note)
                 VALUES (:id, :e, :u, :p, :c)',
                [':id' => Database::uuid(), ':e' => $id, ':u' => $me['userId'],
                 ':p' => (string) (Database::scalar('SELECT display_name FROM profiles WHERE user_id = :i', [':i' => $me['userId']]) ?? 'Team'), ':c' => $note]
            );
            $staffIds = Database::all(
                'SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = "admin"'
            );
            foreach ($staffIds as $s) {
                Database::run(
                    'INSERT INTO notifications (id, kind, title, link, user_id) VALUES (:id, "signup", :t, "/teams/admin/events", :u)',
                    [':id' => Database::uuid(), ':t' => ((string) $me['profile']['display_name']) . ' hat sich für eine Schicht eingetragen', ':u' => $s['user_id']]
                );
            }
        }
        Response::json(['ok' => true]);
    }

    case 'assignment_remove': {
        $aid = Validate::uuid($body, 'assignment_id', true);
        $row = Database::one('SELECT * FROM event_assignments WHERE id = :i', [':i' => $aid]);
        if ($row === null) {
            Response::error('Assignment not found.', 404);
        }
        if (!$isStaff && $row['user_id'] !== $me['userId']) {
            Response::error('You can only remove your own sign-up.', 403);
        }
        Database::run('DELETE FROM event_assignments WHERE id = :i', [':i' => $aid]);
        Audit::log('assignment_remove', 'event_assignments', $aid);
        Response::json(['ok' => true]);
    }

    case 'assignment_update': {
        Auth::requireStaff();
        $aid = Validate::uuid($body, 'assignment_id', true);
        $sets = [];
        $params = [':i' => $aid];
        $name = Validate::str($body, 'person_name', 120);
        if ($name !== null) {
            $sets[] = 'person_name = :p';
            $params[':p'] = $name;
        }
        if (array_key_exists('constraint_note', $body)) {
            $sets[] = 'constraint_note = :c';
            $params[':c'] = Validate::str($body, 'constraint_note', 300);
        }
        if (isset($body['user_id'])) {
            $sets[] = 'user_id = :u';
            $params[':u'] = Validate::uuid($body, 'user_id');
        }
        if ($sets === []) {
            Response::error('Nothing to update.', 400);
        }
        Database::run('UPDATE event_assignments SET ' . implode(', ', $sets) . ' WHERE id = :i', $params);
        Audit::log('assignment_update', 'event_assignments', $aid);
        Response::json(['ok' => true]);
    }
}
