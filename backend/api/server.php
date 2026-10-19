<?php
declare(strict_types=1);

/**
 * Router for the PHP built-in development server:
 *   php -S 127.0.0.1:8787 -t backend api/server.php
 *
 * Routes only /api/*.php; everything else 404s. In production on cPanel,
 * Apache + .htaccess do this job (see DEPLOYMENT.md).
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit("Development router — run via: php -S 127.0.0.1:8787 api/server.php\n");
}

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (preg_match('#^/api/([a-z_]{1,40})\.php$#', $path, $m) === 1) {
    $file = __DIR__ . '/' . $m[1] . '.php';
    if (is_file($file)) {
        require $file;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unknown endpoint.']);
    return true;
}

if ($path === '/' || $path === '/index.php') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>MCG API</title>'
        . '<body style="font-family:system-ui;max-width:640px;margin:4rem auto">'
        . '<h1>Mensa Comunista Graz — API</h1>'
        . '<p>Endpoints (all JSON):</p><ul>'
        . '<li><a href="/api/public_data.php?lang=de">public_data.php?lang=de|en</a> — public homepage bundle</li>'
        . '<li><a href="/api/csrf.php">csrf.php</a> — CSRF token bootstrap</li>'
        . '<li>auth.php — GET me / POST login|logout|signup|reset_request|reset_confirm</li>'
        . '<li>team_data.php — planner bundle (auth)</li>'
        . '<li>events.php, expenses.php, donations.php, homepage_admin.php, media.php,</li>'
        . '<li>media_file.php?id=…, notifications.php, users_admin.php, audit.php, posts.php, account.php</li>'
        . '</ul></body>';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Not found';
return true;
