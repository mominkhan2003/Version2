<?php
declare(strict_types=1);

/**
 * backend/api/homepage_admin.php — the homepage CMS (admin only)
 *
 *   GET  → full admin content tree: sections (ordered), texts both langs,
 *          lists, links, images, stories (+texts), metrics (+labels),
 *          settings, bank, pages, instagram, templates labels & lookups
 *   POST action=section_save        {id, position, is_visible}
 *        action=section_reorder     {order: [sectionId,...]}
 *        action=text_save           {section_id, field_key, lang, value}
 *        action=list_save           {section_id, group_key, lang, items:[...]}
 *        action=link_save           {section_id, link_key, href, labels{l,label}}
 *        action=image_set           {section_id, role, position, media_id}
 *        action=image_remove        {section_id, role, position}
 *        action=story_save          {id?, position, is_visible, media_id, texts{l,{title,body}}[]}
 *        action=story_delete        {id}
 *        action=metric_save         {id, value, position, is_sample, is_visible, labels{l,label}}
 *        action=settings_save       {request_enabled, bank_enabled, bank_*, office_email, contact_address,
 *                                    bank_instructions {de,en}, pages {privacy:{de,en},imprint:{...}},
 *                                    instagram {handle, grid_enabled, tiles[]}}
 *        action=language_labels     (read-only convenience not needed)
 */
require __DIR__ . '/bootstrap.php';

use Mcg\Audit;
use Mcg\Auth;
use Mcg\Cache;
use Mcg\Database;
use Mcg\Media;
use Mcg\Response;
use Mcg\Validate;

$me = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------------- GET
if ($method === 'GET') {
    $sections = [];
    $rows = Database::all('SELECT * FROM homepage_sections ORDER BY position');
    $texts = Database::all('SELECT section_id, field_key, lang, value FROM homepage_section_texts ORDER BY field_key, lang');
    $textsBy = [];
    foreach ($texts as $t) {
        $textsBy[$t['section_id']][$t['field_key']][$t['lang']] = (string) $t['value'];
    }
    $listItems = Database::all('SELECT section_id, group_key, lang, position, value FROM homepage_list_items ORDER BY group_key, position, lang');
    $listsBy = [];
    foreach ($listItems as $li) {
        $listsBy[$li['section_id']][$li['group_key']][] = [
            'lang' => (string) $li['lang'], 'position' => (int) $li['position'], 'value' => (string) $li['value'],
        ];
    }
    $images = Database::all(
        'SELECT si.section_id, si.role, si.position, si.media_id, m.replaced_at
         FROM homepage_section_images si JOIN media_assets m ON m.id = si.media_id
         WHERE m.is_deleted = 0 ORDER BY si.role, si.position'
    );
    $imgBy = [];
    foreach ($images as $i) {
        $imgBy[$i['section_id']][] = [
            'role' => (string) $i['role'], 'position' => (int) $i['position'], 'media_id' => (string) $i['media_id'],
            'thumb_url' => Media::url((string) $i['media_id'], (string) $i['replaced_at'], true),
            'url' => Media::url((string) $i['media_id'], (string) $i['replaced_at']),
        ];
    }
    $links = Database::all(
        'SELECT l.id, l.section_id, l.link_key, l.href, lt.lang, lt.label
         FROM homepage_section_links l LEFT JOIN homepage_link_texts lt ON lt.link_id = l.id
         ORDER BY l.section_id, l.link_key'
    );
    $linksBy = [];
    foreach ($links as $l) {
        $k = (string) $l['section_id'] . '|' . (string) $l['link_key'];
        if (!isset($linksBy[$k])) {
            $linksBy[$k] = ['id' => (int) $l['id'], 'section_id' => (int) $l['section_id'], 'link_key' => (string) $l['link_key'], 'href' => (string) $l['href'], 'labels' => []];
        }
        if ($l['lang'] !== null) {
            $linksBy[$k]['labels'][(string) $l['lang']] = (string) $l['label'];
        }
    }
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $sections[$r['slug']] = [
            'id' => $id,
            'slug' => (string) $r['slug'],
            'kind' => (string) $r['kind'],
            'position' => (int) $r['position'],
            'is_visible' => (bool) $r['is_visible'],
            'texts' => $textsBy[$id] ?? [],
            'lists' => $listsBy[$id] ?? [],
            'images' => $imgBy[$id] ?? [],
            'links' => [],   // filled below keyed by link_key
        ];
    }
    $slugBy = [];
    foreach ($rows as $r) {
        $slugBy[(int) $r['id']] = (string) $r['slug'];
    }
    foreach ($linksBy as $l) {
        $slug = $slugBy[(int) $l['section_id']] ?? null;
        if ($slug !== null) {
            $sections[$slug]['links'][$l['link_key']] = $l;
        }
    }

    // stories
    $stories = [];
    $srows = Database::all('SELECT * FROM community_stories ORDER BY position');
    $stx = Database::all('SELECT * FROM community_story_texts');
    $stxBy = [];
    foreach ($stx as $t) {
        $stxBy[$t['story_id']][$t['lang']] = ['title' => (string) $t['title'], 'body' => (string) $t['body']];
    }
    $mediaRows = Database::all(
        'SELECT m.id, m.replaced_at FROM media_assets m WHERE m.is_deleted = 0'
    );
    $mediaUrl = [];
    foreach ($mediaRows as $m) {
        $mediaUrl[$m['id']] = Media::url((string) $m['id'], (string) $m['replaced_at'], true);
    }
    foreach ($srows as $r) {
        $stories[] = [
            'id' => (string) $r['id'],
            'position' => (int) $r['position'],
            'is_visible' => (bool) $r['is_visible'],
            'media_id' => $r['media_id'] !== null ? (string) $r['media_id'] : null,
            'thumb_url' => $r['media_id'] !== null ? ($mediaUrl[$r['media_id']] ?? null) : null,
            'texts' => $stxBy[$r['id']] ?? [],
        ];
    }

    // metrics
    $metrics = [];
    $mrows = Database::all('SELECT * FROM impact_metrics ORDER BY position');
    $mtx = Database::all('SELECT * FROM impact_metric_texts');
    $mtxBy = [];
    foreach ($mtx as $t) {
        $mtxBy[$t['metric_id']][$t['lang']] = (string) $t['label'];
    }
    foreach ($mrows as $r) {
        $metrics[] = [
            'id' => (int) $r['id'], 'metric_key' => (string) $r['metric_key'], 'value' => (float) $r['value'],
            'position' => (int) $r['position'], 'is_sample' => (bool) $r['is_sample'], 'is_visible' => (bool) $r['is_visible'],
            'labels' => $mtxBy[$r['id']] ?? [],
        ];
    }

    // settings + bank instructions + pages
    $settings = Database::one('SELECT * FROM site_settings WHERE id = 1');
    $bankInst = [];
    foreach (Database::all('SELECT * FROM site_bank_instructions') as $b) {
        $bankInst[$b['lang']] = (string) $b['instructions'];
    }
    $pages = [];
    foreach (Database::all('SELECT * FROM site_page_texts') as $p) {
        $pages[$p['page']][$p['lang']] = ['title' => (string) $p['title'], 'body' => (string) $p['body']];
    }
    $instagram = Database::one('SELECT * FROM site_instagram WHERE id = 1');
    $tiles = Database::all(
        'SELECT t.*, m.replaced_at FROM site_instagram_tiles t LEFT JOIN media_assets m ON m.id = t.media_id
         WHERE t.setting_id = 1 ORDER BY t.position'
    );

    Response::json([
        'ok' => true,
        'section_fields' => mcg_section_field_labels(),
        'sections' => array_values($sections),
        'stories' => $stories,
        'metrics' => $metrics,
        'settings' => $settings,
        'bank_instructions' => $bankInst,
        'pages' => $pages,
        'instagram' => [
            'handle' => (string) ($instagram['handle'] ?? 'mensacomunistagraz'),
            'grid_enabled' => (bool) ($instagram['grid_enabled'] ?? true),
            'tiles' => array_map(static fn (array $t): array => [
                'tile_id' => (int) $t['tile_id'],
                'position' => (int) $t['position'],
                'post_url' => $t['post_url'] !== null ? (string) $t['post_url'] : null,
                'alt' => $t['alt'] !== null ? (string) $t['alt'] : null,
                'media_id' => $t['media_id'] !== null ? (string) $t['media_id'] : null,
                'thumb_url' => $t['media_id'] !== null ? ($mediaUrl[$t['media_id']] ?? null) : null,
            ], $tiles),
        ],
        'locations' => Database::all('SELECT id, name, address, city FROM event_locations WHERE is_deleted = 0'),
        'expense_categories' => Database::all('SELECT id, slug, label_de, label_en FROM expense_categories'),
    ]);
}

/** Human-readable labels editor UI uses. @return array<string,array{de:string,en:string}> */
function mcg_section_field_labels(): array
{
    return [
        'hero' => [
            'kicker' => ['de' => 'Zeilenaufschrift', 'en' => 'Kicker'],
            'title_line1' => ['de' => 'Titel Zeile 1', 'en' => 'Title line 1'],
            'title_line2' => ['de' => 'Titel Zeile 2 (Akzent)', 'en' => 'Title line 2 (accent)'],
            'hand_line' => ['de' => 'Handschrift-Zeile', 'en' => 'Handwritten line'],
            'sub' => ['de' => 'Beschreibung', 'en' => 'Description'],
            'marquee_note' => ['de' => 'Fußzeile im Hero', 'en' => 'Hero footer note'],
            'hero_alt' => ['de' => 'Bild-Alt-Text', 'en' => 'Image alt text'],
        ],
        'kitchen' => [
            'kicker_number' => ['de' => 'Kapitelnummer', 'en' => 'Chapter number'],
            'kicker_label' => ['de' => 'Kicker', 'en' => 'Kicker'],
            'heading' => ['de' => 'Überschrift', 'en' => 'Heading'],
            'heading_accent' => ['de' => 'Überschrift (Akzent)', 'en' => 'Heading (accent)'],
            'body' => ['de' => 'Text', 'en' => 'Body'],
            'note' => ['de' => 'Hinweis', 'en' => 'Note'],
        ],
        'events' => [
            'title' => ['de' => 'Titel', 'en' => 'Title'],
        ],
        'impact' => [
            'title' => ['de' => 'Titel', 'en' => 'Title'],
        ],
        'transparency' => [
            'title' => ['de' => 'Titel', 'en' => 'Title'],
            'sub' => ['de' => 'Untertitel', 'en' => 'Subtitle'],
            'label_received' => ['de' => 'Beschriftung Bestätigt erhalten', 'en' => 'Label Confirmed received'],
            'label_spent' => ['de' => 'Beschriftung Ausgegeben', 'en' => 'Label Spent'],
            'label_available' => ['de' => 'Beschriftung Verfügbar', 'en' => 'Label Available'],
        ],
        'instagram' => [
            'title' => ['de' => 'Titel', 'en' => 'Title'],
            'kicker_label' => ['de' => 'Kicker', 'en' => 'Kicker'],
            'note' => ['de' => 'Hinweis unter dem Raster', 'en' => 'Note below the grid'],
            'alt' => ['de' => 'Bild-Alt-Text', 'en' => 'Image alt text'],
            'follow_note' => ['de' => 'Fallback-Hinweis', 'en' => 'Fallback note'],
        ],
        'involved' => [
            'kicker' => ['de' => 'Kicker', 'en' => 'Kicker'],
            'title' => ['de' => 'Titel', 'en' => 'Title'],
            'sub' => ['de' => 'Text', 'en' => 'Body'],
        ],
        'footer' => [
            'tag' => ['de' => 'Spruch', 'en' => 'Tagline'],
        ],
        'navbar' => [
            'title' => ['de' => 'Titel (Logo)', 'en' => 'Title (logo)'],
        ],
    ];
}

// ---------------------------------------------------------------------- POST
if ($method !== 'POST') {
    Response::error('Method not allowed.', 405);
}
$body = Validate::jsonBody();
$action = Validate::in($body, 'action', [
    'section_save', 'section_reorder', 'text_save', 'list_save', 'link_save',
    'image_set', 'image_remove', 'story_save', 'story_delete', 'metric_save', 'settings_save',
], true);

function mcg_text_upsert(int $sectionId, string $fieldKey, string $lang, string $value): void
{
    if (mb_strlen($value) > 5000) {
        Response::error('Text too long.', 422);
    }
    Database::run(
        'INSERT INTO homepage_section_texts (section_id, field_key, lang, value) VALUES (:s, :f, :l, :v)
         ON DUPLICATE KEY UPDATE value = VALUES(value)',
        [':s' => $sectionId, ':f' => mb_substr($fieldKey, 0, 60), ':l' => $lang, ':v' => $value]
    );
}

function mcg_list_upsert(int $sectionId, string $groupKey, string $lang, array $items): void
{
    if (count($items) > 20) {
        Response::error('Too many list items.', 422);
    }
    Database::run(
        'DELETE FROM homepage_list_items WHERE section_id = :s AND group_key = :g AND lang = :l',
        [':s' => $sectionId, ':g' => mb_substr($groupKey, 0, 40), ':l' => $lang]
    );
    $pos = 0;
    foreach ($items as $v) {
        if (!is_string($v)) {
            continue;
        }
        Database::run(
            'INSERT INTO homepage_list_items (section_id, group_key, lang, position, value) VALUES (:s, :g, :l, :p, :v)',
            [':s' => $sectionId, ':g' => mb_substr($groupKey, 0, 40), ':l' => $lang, ':p' => $pos, ':v' => mb_substr($v, 0, 300)]
        );
        $pos++;
    }
}

function mcg_link_upsert(int $sectionId, string $linkKey, string $href, array $labels): void
{
    if (strlen($href) > 300) {
        Response::error('Link too long.', 422);
    }
    Database::run(
        'INSERT INTO homepage_section_links (section_id, link_key, href) VALUES (:s, :k, :h)
         ON DUPLICATE KEY UPDATE href = VALUES(href)',
        [':s' => $sectionId, ':k' => mb_substr($linkKey, 0, 60), ':h' => $href]
    );
    $linkId = (int) Database::scalar(
        'SELECT id FROM homepage_section_links WHERE section_id = :s AND link_key = :k',
        [':s' => $sectionId, ':k' => mb_substr($linkKey, 0, 60)]
    );
    foreach ($labels as $lang => $label) {
        if (!in_array($lang, ['de', 'en'], true) || !is_string($label)) {
            continue;
        }
        Database::run(
            'INSERT INTO homepage_link_texts (link_id, lang, label) VALUES (:i, :l, :lb)
             ON DUPLICATE KEY UPDATE label = VALUES(label)',
            [':i' => $linkId, ':l' => $lang, ':lb' => mb_substr($label, 0, 200)]
        );
    }
}

$bump = true;
$db = Database::conn();

switch ($action) {
    case 'section_save': {
        $id = (int) (Validate::num($body, 'id', 1, 100000) ?? 0);
        if ($id <= 0) {
            Response::error('Section id required.', 422);
        }
        $sets = [];
        $params = [':i' => $id];
        if (($pos = Validate::num($body, 'position', 0, 999)) !== null) {
            $sets[] = 'position = :p';
            $params[':p'] = (int) $pos;
        }
        if (($vis = Validate::bool($body, 'is_visible')) !== null) {
            $sets[] = 'is_visible = :v';
            $params[':v'] = (int) $vis;
        }
        if ($sets === []) {
            Response::error('Nothing to change.', 400);
        }
        Database::run('UPDATE homepage_sections SET ' . implode(', ', $sets) . ' WHERE id = :i', $params);
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'section_reorder': {
        $order = $body['order'] ?? null;
        if (!is_array($order) || $order === []) {
            Response::error('Order list required.', 422);
        }
        $db->beginTransaction();
        try {
            $pos = 0;
            foreach ($order as $sid) {
                if (!is_numeric($sid)) {
                    continue;
                }
                Database::run('UPDATE homepage_sections SET position = :p WHERE id = :i', [':p' => $pos, ':i' => (int) $sid]);
                $pos++;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'text_save': {
        $sectionId = (int) (Validate::num($body, 'section_id', 1, 100000) ?? 0);
        $fieldKey = Validate::str($body, 'field_key', 60, true) ?? '';
        $lang = Validate::in($body, 'lang', ['de', 'en'], true) ?? 'de';
        $value = (string) (Validate::str($body, 'value', 5000) ?? '');
        $sec = Database::scalar('SELECT COUNT(*) FROM homepage_sections WHERE id = :i', [':i' => $sectionId]);
        if ((int) $sec === 0) {
            Response::error('Unknown section.', 404);
        }
        mcg_text_upsert($sectionId, $fieldKey, $lang, $value);
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'list_save': {
        $sectionId = (int) (Validate::num($body, 'section_id', 1, 100000) ?? 0);
        $groupKey = Validate::str($body, 'group_key', 40, true) ?? '';
        $lang = Validate::in($body, 'lang', ['de', 'en'], true) ?? 'de';
        $items = $body['items'] ?? null;
        if (!is_array($items)) {
            Response::error('Items list required.', 422);
        }
        mcg_list_upsert($sectionId, $groupKey, $lang, $items);
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'link_save': {
        $sectionId = (int) (Validate::num($body, 'section_id', 1, 100000) ?? 0);
        $linkKey = Validate::str($body, 'link_key', 60, true) ?? '';
        $href = Validate::str($body, 'href', 300, true) ?? '';
        $labels = is_array($body['labels'] ?? null) ? $body['labels'] : [];
        mcg_link_upsert($sectionId, $linkKey, $href, $labels);
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'image_set': {
        $sectionId = (int) (Validate::num($body, 'section_id', 1, 100000) ?? 0);
        $role = Validate::str($body, 'role', 40, true) ?? 'main';
        $position = (int) (Validate::num($body, 'position', 0, 100) ?? 0);
        $mediaId = Validate::uuid($body, 'media_id', true);
        $media = Database::scalar('SELECT COUNT(*) FROM media_assets WHERE id = :m AND is_deleted = 0', [':m' => $mediaId]);
        if ((int) $media === 0) {
            Response::error('Unknown media asset.', 404);
        }
        Database::run(
            'INSERT INTO homepage_section_images (section_id, media_id, role, position) VALUES (:s, :m, :r, :p)
             ON DUPLICATE KEY UPDATE media_id = VALUES(media_id)',
            [':s' => $sectionId, ':m' => $mediaId, ':r' => $role, ':p' => $position]
        );
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'image_remove': {
        $sectionId = (int) (Validate::num($body, 'section_id', 1, 100000) ?? 0);
        $role = Validate::str($body, 'role', 40, true) ?? 'main';
        $position = (int) (Validate::num($body, 'position', 0, 100) ?? 0);
        Database::run(
            'DELETE FROM homepage_section_images WHERE section_id = :s AND role = :r AND position = :p',
            [':s' => $sectionId, ':r' => $role, ':p' => $position]
        );
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'story_save': {
        $id = Validate::uuid($body, 'id');
        $position = (int) (Validate::num($body, 'position', 0, 100) ?? 0);
        $visible = (bool) (Validate::bool($body, 'is_visible', true) ?? true);
        $mediaId = Validate::uuid($body, 'media_id');
        if ($mediaId !== null && (int) Database::scalar('SELECT COUNT(*) FROM media_assets WHERE id = :m AND is_deleted = 0', [':m' => $mediaId]) === 0) {
            Response::error('Unknown media asset.', 404);
        }
        $texts = is_array($body['texts'] ?? null) ? $body['texts'] : [];
        if ($id === null) {
            $id = Database::uuid();
            Database::run(
                'INSERT INTO community_stories (id, position, is_visible, media_id) VALUES (:i, :p, :v, :m)',
                [':i' => $id, ':p' => $position, ':v' => (int) $visible, ':m' => $mediaId]
            );
        } else {
            Database::run(
                'UPDATE community_stories SET position = :p, is_visible = :v, media_id = :m WHERE id = :i',
                [':i' => $id, ':p' => $position, ':v' => (int) $visible, ':m' => $mediaId]
            );
        }
        foreach ($texts as $lang => $t) {
            if (!in_array($lang, ['de', 'en'], true) || !is_array($t)) {
                continue;
            }
            Database::run(
                'INSERT INTO community_story_texts (story_id, lang, title, body) VALUES (:i, :l, :t, :b)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body)',
                [':i' => $id, ':l' => $lang, ':t' => mb_substr((string) ($t['title'] ?? ''), 0, 200), ':b' => (string) ($t['body'] ?? '')]
            );
        }
        Audit::log('story_save', 'community_stories', $id);
        Cache::bump();
        Response::json(['ok' => true, 'id' => $id]);
    }

    case 'story_delete': {
        $id = Validate::uuid($body, 'id', true);
        Database::run('DELETE FROM community_stories WHERE id = :i', [':i' => $id]);
        Audit::log('story_delete', 'community_stories', $id);
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'metric_save': {
        $id = (int) (Validate::num($body, 'id', 1, 100000) ?? 0);
        $row = Database::one('SELECT * FROM impact_metrics WHERE id = :i', [':i' => $id]);
        if ($row === null) {
            Response::error('Metric not found.', 404);
        }
        $value = Validate::num($body, 'value', -1000000, 1000000);
        $position = Validate::num($body, 'position', 0, 100);
        $visible = Validate::bool($body, 'is_visible');
        $sample = Validate::bool($body, 'is_sample');
        $sets = [];
        $params = [':i' => $id];
        if ($value !== null) {
            $sets[] = 'value = :v';
            $params[':v'] = round($value, 2);
        }
        if ($position !== null) {
            $sets[] = 'position = :p';
            $params[':p'] = (int) $position;
        }
        if ($visible !== null) {
            $sets[] = 'is_visible = :vis';
            $params[':vis'] = (int) $visible;
        }
        if ($sample !== null) {
            $sets[] = 'is_sample = :s';
            $params[':s'] = (int) $sample;
        }
        if ($sets !== []) {
            Database::run('UPDATE impact_metrics SET ' . implode(', ', $sets) . ' WHERE id = :i', $params);
        }
        $labels = is_array($body['labels'] ?? null) ? $body['labels'] : [];
        foreach ($labels as $lang => $label) {
            if (!in_array($lang, ['de', 'en'], true) || !is_string($label)) {
                continue;
            }
            Database::run(
                'INSERT INTO impact_metric_texts (metric_id, lang, label) VALUES (:i, :l, :lb)
                 ON DUPLICATE KEY UPDATE label = VALUES(label)',
                [':i' => $id, ':l' => $lang, ':lb' => mb_substr($label, 0, 120)]
            );
        }
        Audit::log('metric_save', 'impact_metrics', $id);
        Cache::bump();
        Response::json(['ok' => true]);
    }

    case 'settings_save': {
        // settings singleton
        $sets = [];
        $params = [':i' => 1];
        foreach (['request_enabled', 'bank_enabled'] as $k) {
            $v = Validate::bool($body, $k);
            if ($v !== null) {
                $sets[] = "$k = :$k";
                $params[":$k"] = (int) $v;
            }
        }
        foreach (['bank_holder' => 120, 'bank_iban' => 64, 'bank_bic' => 32, 'bank_name' => 120, 'bank_reference' => 120, 'office_email' => 200, 'contact_address' => 300] as $k => $max) {
            if (array_key_exists($k, $body)) {
                $sets[] = "$k = :$k";
                $params[":$k"] = Validate::str($body, $k, $max);
            }
        }
        if ($sets !== []) {
            Database::run('UPDATE site_settings SET ' . implode(', ', $sets) . ' WHERE id = :i', $params);
        }
        // bank instructions (per lang)
        $bankInst = is_array($body['bank_instructions'] ?? null) ? $body['bank_instructions'] : [];
        foreach ($bankInst as $lang => $text) {
            if (!in_array($lang, ['de', 'en'], true) || !is_string($text)) {
                continue;
            }
            Database::run(
                'INSERT INTO site_bank_instructions (bank_id, lang, instructions) VALUES (1, :l, :t)
                 ON DUPLICATE KEY UPDATE instructions = VALUES(instructions)',
                [':l' => $lang, ':t' => mb_substr($text, 0, 2000)]
            );
        }
        // legal/editorial pages
        $pages = is_array($body['pages'] ?? null) ? $body['pages'] : [];
        foreach ($pages as $page => $langs) {
            if (!in_array($page, ['privacy', 'imprint'], true) || !is_array($langs)) {
                continue;
            }
            foreach ($langs as $lang => $t) {
                if (!in_array($lang, ['de', 'en'], true) || !is_array($t)) {
                    continue;
                }
                Database::run(
                    'INSERT INTO site_page_texts (page, lang, title, body) VALUES (:p, :l, :t, :b)
                     ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body)',
                    [':p' => $page, ':l' => $lang, ':t' => mb_substr((string) ($t['title'] ?? ''), 0, 200), ':b' => (string) ($t['body'] ?? '')]
                );
            }
        }
        // instagram
        $instagram = is_array($body['instagram'] ?? null) ? $body['instagram'] : [];
        if ($instagram !== []) {
            if (isset($instagram['handle']) && is_string($instagram['handle'])) {
                Database::run('UPDATE site_instagram SET handle = :h WHERE id = 1', [':h' => mb_substr(ltrim(str_replace('@', '', $instagram['handle'])), 0, 80)]);
            }
            $ge = Validate::bool($instagram, 'grid_enabled');
            if ($ge !== null) {
                Database::run('UPDATE site_instagram SET grid_enabled = :g WHERE id = 1', [':g' => (int) $ge]);
            }
            $tiles = is_array($instagram['tiles'] ?? null) ? $instagram['tiles'] : [];
            if ($tiles !== []) {
                $db->beginTransaction();
                try {
                    Database::run('DELETE FROM site_instagram_tiles WHERE setting_id = 1');
                    $pos = 0;
                    foreach ($tiles as $tile) {
                        if (!is_array($tile)) {
                            continue;
                        }
                        Database::run(
                            'INSERT INTO site_instagram_tiles (setting_id, media_id, post_url, position, alt) VALUES (1, :m, :u, :p, :a)',
                            [':m' => isset($tile['media_id']) && is_string($tile['media_id']) ? $tile['media_id'] : null,
                             ':u' => isset($tile['post_url']) && is_string($tile['post_url']) ? mb_substr($tile['post_url'], 0, 300) : null,
                             ':p' => $pos,
                             ':a' => isset($tile['alt']) && is_string($tile['alt']) ? mb_substr($tile['alt'], 0, 200) : null]
                        );
                        $pos++;
                        if ($pos > 12) {
                            break;
                        }
                    }
                    $db->commit();
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        }
        Audit::log('settings_save', 'site_settings', '1');
        Cache::bump();
        Response::json(['ok' => true]);
    }
}

