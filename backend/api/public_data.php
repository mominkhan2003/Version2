<?php
declare(strict_types=1);

/**
 * GET backend/api/public_data.php?lang=de|en
 * Full public homepage bundle. No session, no secrets, no donor data.
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Mappers;
use Mcg\Response;
use Mcg\Validate;

$lang = in_array(($_GET['lang'] ?? 'de'), ['de', 'en'], true) ? ($_GET['lang'] ?? 'de') : 'de';
$payload = Mappers::publicData($lang);
mcg_public_response($payload, $lang);
