<?php
declare(strict_types=1);

namespace Mcg;

/** Minimal request validation helpers. */
final class Validate
{
    /** @return array<string,mixed> */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::error('Invalid JSON body.', 400);
        }
        return $data;
    }

    public static function str(array $body, string $key, int $max, bool $required = false): ?string
    {
        $v = $body[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                Response::error('Missing field: ' . $key, 422, [$key => 'required']);
            }
            return null;
        }
        if (!is_string($v)) {
            Response::error('Field "' . $key . '" must be a string.', 422, [$key => 'type']);
        }
        $v = trim($v);
        if (mb_strlen($v) > $max) {
            Response::error('Field "' . $key . '" is too long.', 422, [$key => 'max']);
        }
        return $v;
    }

    public static function email(array $body, string $key = 'email'): string
    {
        $v = strtolower(trim((string) self::str($body, $key, 200, true)));
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
            Response::error('Please enter a valid e-mail address.', 422, [$key => 'invalid']);
        }
        return $v;
    }

    public static function uuid(array $body, string $key, bool $required = false): ?string
    {
        $v = self::str($body, $key, 36, $required);
        if ($v === null) {
            return null;
        }
        if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $v)) {
            Response::error('Field "' . $key . '" has an invalid id format.', 422, [$key => 'format']);
        }
        return strtolower($v);
    }

    public static function in(array $body, string $key, array $allowed, bool $required = false): ?string
    {
        $v = self::str($body, $key, 60, $required);
        if ($v === null) {
            return null;
        }
        if (!in_array($v, $allowed, true)) {
            Response::error('Field "' . $key . '" has an unsupported value.', 422, [$key => 'enum']);
        }
        return $v;
    }

    public static function date(array $body, string $key, bool $required = false): ?string
    {
        $v = self::str($body, $key, 10, $required);
        if ($v === null) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
        if ($d === false || $d->format('Y-m-d') !== $v) {
            Response::error('Field "' . $key . '" must be a date (YYYY-MM-DD).', 422, [$key => 'format']);
        }
        return $v;
    }

    public static function time(array $body, string $key, bool $required = false): ?string
    {
        $v = self::str($body, $key, 5, $required);
        if ($v === null) {
            return null;
        }
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $v)) {
            Response::error('Field "' . $key . '" must be a time (HH:MM).', 422, [$key => 'format']);
        }
        return $v . ':00';
    }

    public static function num(array $body, string $key, float $min, float $max, bool $required = false): ?float
    {
        $raw = $body[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($required) {
                Response::error('Missing field: ' . $key, 422, [$key => 'required']);
            }
            return null;
        }
        if (is_string($raw)) {
            $raw = str_replace([' ', ','][0] === '' ? '' : ' ', '', $raw);
            $raw = (string) preg_replace('/,/', '.', (string) $raw);
        }
        if (!is_numeric($raw)) {
            Response::error('Field "' . $key . '" must be a number.', 422, [$key => 'num']);
        }
        $v = (float) $raw;
        if ($v < $min || $v > $max) {
            Response::error('Field "' . $key . '" is out of range.', 422, [$key => 'range']);
        }
        return $v;
    }

    public static function posInt(array $body, string $key, bool $required = false): ?int
    {
        $raw = $body[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($required) {
                Response::error('Missing field: ' . $key, 422, [$key => 'required']);
            }
            return null;
        }
        if (is_string($raw) && preg_match('/^\d+$/', trim($raw))) {
            $raw = (int) trim($raw);
        }
        if (!is_int($raw) || $raw < 0) {
            Response::error('Field "' . $key . '" must be a positive integer.', 422, [$key => 'int']);
        }
        return $raw;
    }

    public static function bool(array $body, string $key, ?bool $default = null): ?bool
    {
        $raw = $body[$key] ?? $default;
        if ($raw === null) {
            return null;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        if ($raw === 1 || $raw === '1' || $raw === 'true') {
            return true;
        }
        if ($raw === 0 || $raw === '0' || $raw === 'false') {
            return false;
        }
        Response::error('Field "' . $key . '" must be a boolean.', 422, [$key => 'bool']);
    }
}
