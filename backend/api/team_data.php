<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Mcg\Mappers;
use Mcg\Response;

/** GET backend/api/team_data.php — planner bundle for the signed-in member. */
Mcg\Auth::requireAuth();
$me = Mcg\Auth::me();
Response::json(Mappers::teamData((string) $me['userId'], $me['roles']));
