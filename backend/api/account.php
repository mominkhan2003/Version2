<?php
declare(strict_types=1);

/**
 * POST backend/api/account.php
 *   action: update_profile | change_password
 * Self-service profile changes for the signed-in member.
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Database;
use Mcg\Response;
use Mcg\Validate;

$me = Auth::requireAuth();
$body = Validate::jsonBody();
$action = Validate::in($body, 'action', ['update_profile', 'change_password'], true);

if ($action === 'update_profile') {
    $name = Validate::str($body, 'display_name', 80, true);
    $phone = Validate::str($body, 'phone', 40);
    $skillsRaw = Validate::str($body, 'skills', 600) ?? '';
    Database::run(
        'UPDATE profiles SET display_name = :n, phone = :p WHERE user_id = :i',
        [':n' => $name, ':p' => $phone, ':i' => $me['userId']]
    );
    // normalize free-text skill list into the junction table (3NF)
    $wanted = [];
    foreach (explode(',', $skillsRaw) as $s) {
        $s = mb_substr(trim($s), 0, 80);
        if ($s !== '' && mb_strlen($s) <= 80) {
            $wanted[mb_strtolower($s)] = $s;
        }
    }
    $slugs = [];
    foreach ($wanted as $label) {
        $slug = trim(mb_strtolower($label));
        if (!preg_match('/^[a-z0-9äöüß\- ]+$/u', $slug)) {
            continue; // non-transliterated free text is skipped
        }
        $slug = preg_replace('/[^a-z0-9äöüß]+/', '_', $slug);
        $slug = trim((string) $slug, '_');
        if ($slug === '') {
            continue;
        }
        $id = Database::scalar('SELECT id FROM skills WHERE slug = :s', [':s' => $slug]);
        if ($id === false || $id === null) {
            Database::run(
                'INSERT INTO skills (slug, label_de, label_en) VALUES (:s, :l, :l)',
                [':s' => mb_substr($slug, 0, 40), ':l' => $label]
            );
            $id = Database::conn()->lastInsertId();
        }
        $slugs[] = ['id' => (int) $id, 'label' => $label];
    }
    $db = Database::conn();
    $db->beginTransaction();
    try {
        Database::run('DELETE FROM profile_skills WHERE user_id = :i', [':i' => $me['userId']]);
        foreach ($slugs as $s) {
            Database::run(
                'INSERT IGNORE INTO profile_skills (user_id, skill_id) VALUES (:i, :s)',
                [':i' => $me['userId'], ':s' => $s['id']]
            );
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    Response::json(['ok' => true, 'skills' => array_map(static fn (array $s): string => $s['label'], $slugs)]);
}

Auth::changePassword(
    (string) (Validate::str($body, 'current', 200, true) ?? ''),
    (string) (Validate::str($body, 'password', 200, true) ?? '')
);
Response::json(['ok' => true]);
