<?php
declare(strict_types=1);

namespace Mcg;

/**
 * Media library file handling. Image binaries live in the private storage
 * directory (outside public_html); only metadata is stored in MySQL.
 * Validation: finfo MIME whitelist, size limit, dimension probe, thumbs via
 * GD when available (graceful fallback to the original file).
 */
final class Media
{
    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB
    private const MIME_EXT  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function storageRoot(): string
    {
        $cfg = Database::config();
        $dir = (string) ($cfg['storage_dir'] ?? '');
        if ($dir === '' || $dir === '/YOUR/ABSOLUTE/STORAGE/PATH/mcg_storage') {
            throw new \RuntimeException('storage_dir not configured in database.php.');
        }
        return rtrim($dir, '/\\');
    }

    private static function mediaPath(string $relative): string
    {
        $root = self::storageRoot();
        $full = $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
        $realRoot = realpath($root);
        $realFull = realpath(dirname($full)) ? realpath(dirname($full)) . DIRECTORY_SEPARATOR . basename($full) : false;
        if ($realRoot === false || $realFull === false || !str_starts_with($realFull, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Invalid media path.');
        }
        return $realFull;
    }

    public static function url(string $mediaId, ?string $replacedAt = null, bool $thumb = false): string
    {
        $v = $replacedAt ? strtotime((string) $replacedAt) : '0';
        return 'api/media_file.php?id=' . rawurlencode($mediaId) . ($thumb ? '&t=thumb' : '') . '&v=' . $v;
    }

    /** @return array<string,mixed> asset row + urls */
    public static function findWithUrls(string $id): ?array
    {
        $asset = Database::one('SELECT * FROM media_assets WHERE id = :i AND is_deleted = 0', [':i' => $id]);
        if ($asset === null) {
            return null;
        }
        return self::decorate($asset);
    }

    /** @param array<string,mixed> $asset */
    public static function decorate(array $asset): array
    {
        $asset['url']      = self::url((string) $asset['id'], isset($asset['replaced_at']) ? (string) $asset['replaced_at'] : null);
        $asset['thumb_url'] = self::url((string) $asset['id'], isset($asset['replaced_at']) ? (string) $asset['replaced_at'] : null, true);
        return $asset;
    }

    /** @param array<string,mixed> $fileEntry element of $_FILES */
    public static function upload(array $fileEntry, string $uploadedBy): array
    {
        self::assertValidUpload($fileEntry);
        $mime = self::detectMime((string) $fileEntry['tmp_name']);
        if (!isset(self::MIME_EXT[$mime])) {
            Response::error('Only JPEG, PNG or WebP images are allowed.', 415, ['file' => 'type']);
        }
        $size = (int) $fileEntry['size'];
        if ($size <= 0 || $size > self::MAX_BYTES) {
            Response::error('Image is too large (max 8 MB).', 413, ['file' => 'size']);
        }
        $info = getimagesize((string) $fileEntry['tmp_name']);
        if ($info === false) {
            Response::error('Not a valid image.', 415, ['file' => 'type']);
        }
        $id  = Database::uuid();
        $ext = self::MIME_EXT[$mime];
        $relative = $id . '.' . $ext;
        self::ensureDir(dirname(self::mediaPath('x')));
        if (!move_uploaded_file((string) $fileEntry['tmp_name'], self::mediaPath($relative))) {
            Response::error('Saving the file failed. Please try again.', 500);
        }
        $thumb = self::makeThumb($relative, $info[0], $info[1]);
        Database::run(
            'INSERT INTO media_assets (id, file_path, mime_type, byte_size, width, height, thumb_path, uploaded_by)
             VALUES (:id, :p, :m, :s, :w, :h, :t, :u)',
            [':id' => $id, ':p' => $relative, ':m' => $mime, ':s' => $size, ':w' => $info[0], ':h' => $info[1], ':t' => $thumb, ':u' => $uploadedBy]
        );
        return self::findWithUrls($id) ?? [];
    }

    public static function replace(string $id, array $fileEntry, string $actingUser): array
    {
        $asset = Database::one('SELECT * FROM media_assets WHERE id = :i AND is_deleted = 0 FOR UPDATE', [':i' => $id]);
        if ($asset === null) {
            Response::error('Media asset not found.', 404);
        }
        self::assertValidUpload($fileEntry);
        $mime = self::detectMime((string) $fileEntry['tmp_name']);
        if (!isset(self::MIME_EXT[$mime])) {
            Response::error('Only JPEG, PNG or WebP images are allowed.', 415, ['file' => 'type']);
        }
        $info = getimagesize((string) $fileEntry['tmp_name']);
        if ($info === false) {
            Response::error('Not a valid image.', 415, ['file' => 'type']);
        }
        $newRelative = $id . '_' . bin2hex(random_bytes(4)) . '.' . self::MIME_EXT[$mime];
        if (!move_uploaded_file((string) $fileEntry['tmp_name'], self::mediaPath($newRelative))) {
            Response::error('Saving the file failed. Please try again.', 500);
        }
        $thumb = self::makeThumb($newRelative, $info[0], $info[1]);
        // remove previous binaries (best effort)
        @unlink(self::mediaPath((string) $asset['file_path']));
        if ($asset['thumb_path']) {
            @unlink(self::mediaPath((string) $asset['thumb_path']));
        }
        Database::run(
            'UPDATE media_assets SET file_path = :p, mime_type = :m, byte_size = :s, width = :w, height = :h,
             thumb_path = :t, replaced_at = NOW(6) WHERE id = :i',
            [':p' => $newRelative, ':m' => $mime, ':s' => (int) $fileEntry['size'], ':w' => $info[0], ':h' => $info[1], ':t' => $thumb, ':i' => $id]
        );
        Audit::log('media_replace', 'media_assets', $id, ['file' => $newRelative]);
        return self::findWithUrls($id) ?? [];
    }

    public static function delete(string $id, string $actingUser): void
    {
        $asset = Database::one('SELECT * FROM media_assets WHERE id = :i AND is_deleted = 0', [':i' => $id]);
        if ($asset === null) {
            return;
        }
        $db = Database::conn();
        $db->beginTransaction();
        try {
            Database::run('UPDATE media_assets SET is_deleted = 1 WHERE id = :i', [':i' => $id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        @unlink(self::mediaPath((string) $asset['file_path']));
        if ($asset['thumb_path']) {
            @unlink(self::mediaPath((string) $asset['thumb_path']));
        }
        Audit::log('media_delete', 'media_assets', $id);
    }

    /** Streams the file (original or thumbnail) with safe headers. */
    public static function stream(string $id, bool $thumb): void
    {
        $asset = Database::one('SELECT * FROM media_assets WHERE id = :i AND is_deleted = 0', [':i' => $id]);
        if ($asset === null) {
            http_response_code(404);
            exit;
        }
        $relative = ($thumb && $asset['thumb_path']) ? (string) $asset['thumb_path'] : (string) $asset['file_path'];
        $path = self::mediaPath($relative);
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mtime = filemtime($path) ?: 0;
        $etag  = '"' . md5($id . $mtime . ($thumb ? 't' : '')) . '"';
        header('Content-Type: ' . (string) $asset['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------- internals
    private static function assertValidUpload(array $f): void
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            Response::error('Upload failed. Please try again.', 400, ['file' => 'upload']);
        }
    }

    private static function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->file($path);
    }

    private static function makeThumb(string $relative, int $w, int $h): ?string
    {
        if (!extension_loaded('gd')) {
            return null; // graceful: frontend falls back to the original image
        }
        try {
            $max = 640;
            if ($w <= $max && $h <= $max) {
                return null;
            }
            $ratio = $max / max($w, $h);
            $tw = (int) max(1, round($w * $ratio));
            $th = (int) max(1, round($h * $ratio));
            $src = self::mediaPath($relative);
            $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            $img = match ($ext) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($src),
                'png' => @imagecreatefrompng($src),
                'webp' => @imagecreatefromwebp($src),
                default => false,
            };
            if ($img === false) {
                return null;
            }
            $out = imagecreatetruecolor($tw, $th);
            if (($ext === 'png') || ($ext === 'webp')) {
                imageSaveAlpha($out, true);
                $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
                imagefill($out, 0, 0, $transparent);
            }
            imagecopyresampled($out, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
            $thumbRelative = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '_t.jpg', $relative) ?? $relative . '_t.jpg';
            $dest = self::mediaPath($thumbRelative);
            imagejpeg($out, $dest, 82);
            imagedestroy($out);
            imagedestroy($img);
            return $thumbRelative;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
