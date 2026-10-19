<?php
declare(strict_types=1);

namespace Mcg;

final class Response
{
    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<string,mixed> $errors field => message */
    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        self::json(['ok' => false, 'error' => $message, 'errors' => $errors], $status);
    }

    /** @param array<string,mixed> $data */
    public static function ok(array $data): never
    {
        self::json(['ok' => true] + $data);
    }
}
