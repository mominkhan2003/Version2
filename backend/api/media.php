<?php
declare(strict_types=1);

/**
 * backend/api/media.php — admin media library
 *   GET   → paginated list (with filters)
 *   POST  → multipart upload (single)     action=upload
 *           replace existing binary        action=replace&id=...
 *           delete asset                   action=delete&id=...          (JSON)
 *           update alt/caption             action=update&id=...          (JSON)
 * Auth: staff only. Uploads cost nothing on the DB — binaries stay on disk.
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Database;
use Mcg\Media;
use Mcg\Response;
use Mcg\Validate;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    Auth::requireStaff();
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(48, max(6, (int) ($_GET['per_page'] ?? 24)));
    $offset  = ($page - 1) * $perPage;
    $rows = Database::all(
        'SELECT m.*, t.alt, t.caption
         FROM media_assets m
         LEFT JOIN media_translations t ON t.media_id = m.id AND t.lang = "de"
         WHERE m.is_deleted = 0
         ORDER BY m.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $total = (int) Database::scalar('SELECT COUNT(*) FROM media_assets WHERE is_deleted = 0');
    Response::json([
        'ok' => true,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'assets' => array_map(static function (array $r): array {
            $base = Media::decorate($r);
            return [
                'id' => (string) $base['id'],
                'mime_type' => (string) $base['mime_type'],
                'byte_size' => (int) $base['byte_size'],
                'width' => $base['width'] !== null ? (int) $base['width'] : null,
                'height' => $base['height'] !== null ? (int) $base['height'] : null,
                'url' => (string) $base['url'],
                'thumb_url' => (string) $base['thumb_url'],
                'alt' => $r['alt'] !== null ? (string) $r['alt'] : '',
                'caption' => $r['caption'] !== null ? (string) $r['caption'] : '',
                'created_at' => (string) $r['created_at'],
            ];
        }, $rows),
    ]);
}

Auth::requireStaff();
if ($method !== 'POST') {
    Response::error('Method not allowed.', 405);
}

// multipart (upload/replace) versus JSON (delete/update)
$isMultipart = isset($_SERVER['CONTENT_TYPE']) && str_starts_with((string) $_SERVER['CONTENT_TYPE'], 'multipart/form-data');
if ($isMultipart) {
    $action = (string) ($_POST['action'] ?? 'upload');
    $action = in_array($action, ['upload', 'replace'], true) ? $action : 'upload';
    $me = Auth::requireStaff();
    if ($action === 'upload') {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::error('No file received.', 422, ['file' => 'required']);
        }
        $asset = Media::upload($_FILES['file'], (string) $me['userId']);
        $alt = $_POST['alt'] ?? null;
        foreach (['de', 'en'] as $lang) {
            Database::run(
                'INSERT INTO media_translations (media_id, lang, alt, caption) VALUES (:m, :l, :a, :c)
                 ON DUPLICATE KEY UPDATE alt = VALUES(alt)',
                [':m' => $asset['id'], ':l' => $lang, ':a' => $alt]
            );
        }
        Response::json(['ok' => true, 'asset' => $asset]);
    }
    $id = Validate::uuid(['id' => (string) ($_POST['id'] ?? '')], 'id', true);
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        Response::error('No file received.', 422, ['file' => 'required']);
    }
    $asset = Media::replace($id, $_FILES['file'], (string) $me['userId']);
    Response::json(['ok' => true, 'asset' => $asset]);
}

$body = Validate::jsonBody();
$action = Validate::in($body, 'action', ['delete', 'update'], true);
$id = Validate::uuid($body, 'id', true);

if ($action === 'delete') {
    Media::delete($id, (string) Auth::me()['userId']);
    Response::json(['ok' => true]);
}

// update translations (alt / caption, per language)
$lang = Validate::in($body, 'lang', ['de', 'en']);
if ($lang === null) {
    Response::error('Language required (de|en).', 422, ['lang' => 'required']);
}
$alt = Validate::str($body, 'alt', 200) ?? '';
$caption = Validate::str($body, 'caption', 400) ?? '';
if (!array_key_exists('alt', $body) && !array_key_exists('caption', $body)) {
    Response::error('Nothing to update.', 400);
}
Database::run(
    'INSERT INTO media_translations (media_id, lang, alt, caption) VALUES (:m, :l, :a, :c)
     ON DUPLICATE KEY UPDATE alt = VALUES(alt), caption = VALUES(caption)',
    [':m' => $id, ':l' => $lang, ':a' => $alt, ':c' => $caption]
);
Audit::log('media_update', 'media_assets', $id, ['lang' => $lang]);
Response::json(['ok' => true, 'asset' => Media::findWithUrls($id)]);
