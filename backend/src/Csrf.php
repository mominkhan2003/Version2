<?php
declare(strict_types=1);

namespace Mcg;

/** CSRF protection: same-origin gate + session-bound token header. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verify(string $method): void
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        // 1) Same-origin check
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $allowed = self::allowedOrigins();
        if ($origin !== '') {
            if (!in_array($origin, $allowed, true)) {
                Response::error('Cross-site request blocked.', 403);
            }
        } elseif ($referer !== '') {
            $part = ((string) parse_url($referer, PHP_URL_SCHEME)) . '://' . ((string) parse_url($referer, PHP_URL_HOST));
            if (!in_array($part, $allowed, true)) {
                Response::error('Cross-site request blocked.', 403);
            }
        } else {
            Response::error('Missing Origin/Referer for state-changing request.', 403);
        }
        // 2) Session-bound double-submit token
        $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($sent === '' || empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], $sent)) {
            Response::error('CSRF token invalid. Reload and try again.', 403);
        }
    }

    /** @return list<string> */
    private static function allowedOrigins(): array
    {
        $list = [];
        $cfg = Database::config();
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (!empty($_SERVER['HTTP_HOST'])) {
            // same host as the API request is always allowed
            $list[] = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }
        if (!empty($cfg['base_url']) && is_string($cfg['base_url'])) {
            $list[] = rtrim((string) $cfg['base_url'], '/');
        }
        foreach ((array) ($cfg['dev_origins'] ?? []) as $o) {
            if (is_string($o) && $o !== '') {
                $list[] = rtrim($o, '/');
            }
        }
        return array_values(array_unique(array_filter($list)));
    }
}
