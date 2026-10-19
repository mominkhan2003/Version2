<?php
declare(strict_types=1);

/**
 * POST backend/api/msf_csrf.php → not applicable; CSRF token is issued here:
 * GET backend/api/csrf.php returns { ok, token } known after session start.
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Csrf;
use Mcg\Response;

Response::json(['ok' => true, 'token' => Csrf::token()]);
