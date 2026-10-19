<?php
declare(strict_types=1);

/** GET backend/api/media_file.php?id=<uuid>&t=thumb|full — streams a media binary. */
require __DIR__ . '/bootstrap.php';

use Mcg\Media;

$id = preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
    (string) ($_GET['id'] ?? '')) ? strtolower((string) $_GET['id']) : '';
if ($id === '') {
    http_response_code(400);
    exit;
}
$thumb = ($_GET['t'] ?? '') === 'thumb';
Media::stream($id, $thumb);
