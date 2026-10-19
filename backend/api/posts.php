<?php
declare(strict_types=1);

/**
 * backend/api/posts.php — canvas studio export posts (staff only)
 *   GET  → list own/team posts
 *   POST → save a rendered post (multipart with file, or JSON metadata-only)
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Database;
use Mcg\Media;
use Mcg\Response;
use Mcg\Validate;

$me = Auth::requireStaff();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $rows = Database::all(
        'SELECT p.*, m.mime_type, m.byte_size, m.replaced_at, u.email AS author_email, pr.display_name AS author_name
         FROM social_posts p
         JOIN media_assets m ON m.id = p.media_id AND m.is_deleted = 0
         LEFT JOIN users u ON u.id = p.created_by
         LEFT JOIN profiles pr ON pr.user_id = p.created_by
         ORDER BY p.created_at DESC LIMIT 60'
    );
    Response::json([
        'ok' => true,
        'posts' => array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'channel' => (string) $r['channel'],
            'format' => (string) $r['format'],
            'title' => $r['title'] !== null ? (string) $r['title'] : '',
            'status' => (string) $r['status'],
            'url' => Media::url((string) $r['media_id'], (string) $r['replaced_at']),
            'byte_size' => (int) $r['byte_size'],
            'author' => $r['author_name'] !== null ? (string) $r['author_name'] : (string) $r['author_email'],
            'created_at' => substr((string) $r['created_at'], 0, 19),
        ], $rows),
    ]);
}

// ---------------------------------------------------------------- POST save
if (isset($_SERVER['CONTENT_TYPE']) && str_starts_with((string) $_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        Response::error('No rendered image received.', 422, ['file' => 'required']);
    }
    $asset = Media::upload($_FILES['file'], (string) $me['userId']);
    Database::run(
        'INSERT INTO social_posts (id, channel, format, title, media_id, status, created_by)
         VALUES (:id, :ch, :f, :t, :m, "ready", :u)',
        [':id' => Database::uuid(),
         ':ch' => in_array(($_POST['channel'] ?? ''), ['instagram', 'flyer', 'story'], true) ? (string) $_POST['channel'] : 'instagram',
         ':f' => in_array(($_POST['format'] ?? ''), ['square_1080', 'portrait_1080x1350', 'story_1080x1920'], true) ? (string) $_POST['format'] : 'square_1080',
         ':t' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120),
         ':m' => $asset['id'], ':u' => $me['userId']]
    );
    Audit::log('post_save', 'social_posts', $asset['id']);
    Response::json(['ok' => true, 'asset' => $asset]);
}

Response::error('Upload posts as multipart/form-data with the rendered PNG.', 415);
