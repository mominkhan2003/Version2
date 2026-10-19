<?php
declare(strict_types=1);

/**
 * CLI bootstrap: autoload + error handling, NO session, NO CORS.
 * Used by backend/tools/*.php and one-off maintenance scripts.
 */

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Mcg\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    fwrite(STDERR, '[mcg] ' . $msg . ' @ ' . basename($file) . ':' . $line . "\n");
    return true;
});

date_default_timezone_set('Europe/Vienna');
