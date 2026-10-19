<?php
declare(strict_types=1);

/**
 * POST backend/api/auth.php   { action: "login"|"logout"|"signup"|"reset_request"|"reset_confirm", ... }
 * GET  backend/api/auth.php   → session check (me)
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Auth;
use Mcg\Response;
use Mcg\Validate;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $me = Auth::me();
    if ($me === null) {
        Response::json(['ok' => true, 'user' => null]);
    }
    Response::json(['ok' => true, 'user' => $me]);
}

$body = Validate::jsonBody();
$action = Validate::in($body, 'action', ['login', 'logout', 'signup', 'reset_request', 'reset_confirm'], true);

switch ($action) {
    case 'login':
        Auth::login(
            Validate::email($body),
            (string) (Validate::str($body, 'password', 200, true) ?? '')
        );
        Response::json(['ok' => true, 'user' => Auth::me()]);

    case 'logout':
        Auth::logout();
        Response::json(['ok' => true]);

    case 'signup':
        $user = Auth::signup(
            Validate::email($body),
            (string) (Validate::str($body, 'name', 80, true) ?? ''),
            (string) (Validate::str($body, 'password', 200, true) ?? '')
        );
        Auth::login($user['email'], (string) $body['password']);
        Response::json(['ok' => true, 'user' => Auth::me()]);

    case 'reset_request':
        Auth::requestReset(Validate::email($body));
        Response::json(['ok' => true, 'message' =>
            'If the address is registered, a reset link has been sent. / Falls die Adresse registriert ist, wurde ein Link gesendet.']);

    case 'reset_confirm':
        Auth::confirmReset(
            (string) (Validate::str($body, 'token', 80, true) ?? ''),
            (string) (Validate::str($body, 'password', 200, true) ?? '')
        );
        Response::json(['ok' => true]);
}
