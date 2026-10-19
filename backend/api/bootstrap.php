<?php
declare(strict_types=1);

/**
 * Shared API bootstrap: autoload, error envelope, session, CORS/same-origin.
 * Every endpoint file requires this first.
 */

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Mcg\\')) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use Mcg\Database;
use Mcg\Response;

// ---- global error / exception envelope (details go to logs only) -----------
set_exception_handler(static function (Throwable $e): void {
    error_log('[mcg] ' . $e->getMessage() . ' @ ' . basename(str_replace('\\', '/', $e->getFile())) . ':' . $e->getLine());
    Response::json(['ok' => false, 'error' => 'Internal error. Please try again later.', 'errors' => []], 500);
});
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    error_log('[mcg] php-error: ' . $msg . ' @ ' . basename($file) . ':' . $line);
    return true;
});

Mcg\Auth::sessionStart();

// Same-origin policy enforced by Csrf for all state-changing requests.
Mcg\Csrf::verify($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Write a given JSON payload with public caching derived from version stamp.
 * @param array<string,mixed> $payload
 */
function mcg_public_response(array $payload, string $lang): never
{
    header('Cache-Control: public, max-age=30, stale-while-revalidate=120');
    header('X-Mcg-Version: ' . Mcg\Cache::version());
    Mcg\Response::json($payload);
}
