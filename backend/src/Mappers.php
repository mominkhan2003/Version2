<?php
declare(strict_types=1);

namespace Mcg;

/**
 * Payload assembly for the public site and the team planner.
 * All queries are batched per entity table to avoid N+1 patterns;
 * aggregates (event counts, finances) are computed by grouped queries.
 */
final class Mappers
{
    /** @return array{lang:string, value:string} */
    private static function withLang(string $lang, string $fallback = 'de'): array
    {
        return ['lang' => in_array($lang, ['de', 'en'], true) ? $lang : $fallback];
    }

    // ------------------------------------------------------------------
    //  PUBLIC BUNDLE (homepage)
    // ------------------------------------------------------------------
    /** @return array<string,mixed> */
    public static function publicData(string $lang = 'de'): array
    {
        [$sections, $lists, $stories, $events, $metrics, $counts, $finance, $settings, $instagram, $pages] = [
            self::cmsSections($lang),
            self::cmsLists($lang),
            self::cmsStories($lang),
            self::publicEvents($lang),
            self::impactMetrics($lang),
            self::eventCounts(),
            self::publicFinances(),
            self::settings($lang),
            self::instagramTiles(),
            self::pageTexts($lang),
        ];
        return [
            'version'  => Cache::version(),
            'sections' => $sections,
            'lists'    => $lists,
            'stories'  => $stories,
            'events'   => $events,
            'metrics'  => $metrics,
            'counts'   => $counts,
            'finances' => $finance,
            'settings' => $settings,
            'instagram' => $instagram,
            'pages'    => $pages,
        ];
    }

    /** Sections with all translatable copy + images + links (single round trips). @return list<array<string,mixed>> */
    private static function cmsSections(string $lang): array
    {
        $rows = Database::all(
            'SELECT s.id, s.slug, s.kind, s.position, s.is_visible, t.field_key, t.value
             FROM homepage_sections s
             LEFT JOIN homepage_section_texts t ON t.section_id = s.id AND t.lang = :lang
             ORDER BY s.position', [':lang' => $lang]
        );
        $imageRows = Database::all(
            'SELECT si.section_id, si.role, si.position, m.id, m.replaced_at
             FROM homepage_section_images si
             JOIN media_assets m ON m.id = si.media_id AND m.is_deleted = 0
             ORDER BY si.role, si.position'
        );
        $linkRows = Database::all(
            'SELECT l.section_id, l.link_key, l.href, lt.label
             FROM homepage_section_links l
             LEFT JOIN homepage_link_texts lt ON lt.link_id = l.id AND lt.lang = :lang
             ORDER BY l.id', [':lang' => $lang]
        );
        $byId = [];
        foreach ($rows as $r) {
            $id = (string) $r['id'];
            if (!isset($byId[$id])) {
                $byId[$id] = [
                    'id' => $id, 'slug' => (string) $r['slug'], 'kind' => (string) $r['kind'],
                    'position' => (int) $r['position'], 'is_visible' => (int) $r['is_visible'] === 1,
                    'texts' => [], 'images' => [], 'links' => [],
                ];
            }
            if ($r['field_key'] !== null) {
                $byId[$id]['texts'][(string) $r['field_key']] = (string) $r['value'];
            }
        }
        foreach ($imageRows as $r) {
            $id = (string) $r['section_id'];
            if (isset($byId[$id])) {
                $byId[$id]['images'][] = [
                    'role' => $r['role'], 'position' => (int) $r['position'],
                    'url' => Media::url((string) $r['id'], isset($r['replaced_at']) ? (string) $r['replaced_at'] : null),
                ];
            }
        }
        foreach ($linkRows as $r) {
            $id = (string) $r['section_id'];
            if (isset($byId[$id]) && $r['link_key'] !== null) {
                $byId[$id]['links'][$r['link_key']] = [
                    'href' => (string) $r['href'],
                    'label' => $r['label'] !== null ? (string) $r['label'] : '',
                ];
            }
        }
        return array_values($byId);
    }

    /** @return array<string, list<string>> group_key -> ordered values */
    private static function cmsLists(string $lang): array
    {
        $rows = Database::all(
            'SELECT li.section_id, s.slug, li.group_key, li.value
             FROM homepage_list_items li JOIN homepage_sections s ON s.id = li.section_id
             WHERE li.lang = :lang ORDER BY li.position', [':lang' => $lang]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['group_key']][] = (string) $r['value'];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function cmsStories(string $lang): array
    {
        $rows = Database::all(
            'SELECT st.id, st.position, st.is_visible, st.media_id, t.title, t.body, m.replaced_at
             FROM community_stories st
             LEFT JOIN community_story_texts t ON t.story_id = st.id AND t.lang = :lang
             LEFT JOIN media_assets m ON m.id = st.media_id AND m.is_deleted = 0
             WHERE st.is_visible = 1 ORDER BY st.position', [':lang' => $lang]
        );
        return array_map(static function (array $r) use ($lang): array {
            return [
                'id'        => (string) $r['id'],
                'title'     => (string) ($r['title'] ?? ''),
                'body'      => (string) ($r['body'] ?? ''),
                'media_id'  => $r['media_id'] !== null ? (string) $r['media_id'] : null,
                'image_url' => $r['media_id'] !== null ? Media::url((string) $r['media_id'], isset($r['replaced_at']) ? (string) $r['replaced_at'] : null) : null,
            ];
        }, $rows);
    }

    /** @return list<array<string,mixed>> upcoming published events (legacy row shape + extras) */
    private static function publicEvents(string $lang = 'de'): array
    {
        $rows = Database::all(
            'SELECT e.id, e.event_date, e.start_time, e.end_time, e.volunteers_needed, e.status, e.is_demo, e.cover_media_id,
                    e.meeting_point, l.name AS location, c.slug AS activity_type, et.title, et.description, et.extra_info, m.replaced_at AS cover_replaced
             FROM events e
             LEFT JOIN event_translations et ON et.event_id = e.id AND et.lang = :lang
             LEFT JOIN event_locations l ON l.id = e.location_id
             LEFT JOIN activity_categories c ON c.id = e.activity_category_id
             LEFT JOIN media_assets m ON m.id = e.cover_media_id AND m.is_deleted = 0
             WHERE e.is_published = 1 AND e.status <> "cancelled" AND e.event_date >= CURDATE()
             ORDER BY e.event_date, e.start_time', [':lang' => $lang]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (string) $r['id'],
                'event_date' => (string) $r['event_date'],
                'title' => (string) ($r['title'] ?? ''),
                'activity_type' => (string) $r['activity_type'],
                'meeting_point' => $r['meeting_point'] !== null ? (string) $r['meeting_point'] : null,
                'location' => $r['location'] !== null ? (string) $r['location'] : null,
                'start_time' => $r['start_time'] !== null ? substr((string) $r['start_time'], 0, 5) : null,
                'end_time' => $r['end_time'] !== null ? substr((string) $r['end_time'], 0, 5) : null,
                'volunteers_needed' => $r['volunteers_needed'] !== null ? (int) $r['volunteers_needed'] : null,
                'extra_info' => $r['extra_info'] !== null ? (string) $r['extra_info'] : null,
                'description' => $r['description'] !== null ? (string) $r['description'] : null,
                'cover_url' => $r['cover_media_id'] !== null
                    ? Media::url((string) $r['cover_media_id'], isset($r['cover_replaced']) ? (string) $r['cover_replaced'] : null)
                    : null,
                'status' => (string) $r['status'],
                'is_demo' => (bool) $r['is_demo'],
            ];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function impactMetrics(string $lang): array
    {
        // visible rows once; both-language labels delivered with German fallback
        $rows = Database::all(
            'SELECT id, metric_key, value, is_sample FROM impact_metrics WHERE is_visible = 1 ORDER BY position'
        );
        $de = [];
        $en = [];
        foreach (Database::all('SELECT metric_id, label, lang FROM impact_metric_texts') as $r) {
            if ($r['lang'] === 'de') {
                $de[(int) $r['metric_id']] = (string) $r['label'];
            } elseif ($r['lang'] === 'en') {
                $en[(int) $r['metric_id']] = (string) $r['label'];
            }
        }
        foreach ($de as $mid => $label) {
            $en[$mid] = $en[$mid] ?? $label; // fall back to German when translation missing
        }
        return array_map(static function (array $r) use ($de, $en): array {
            $id = (int) $r['id'];
            return [
                'id'        => (string) $r['id'],
                'key'       => (string) $r['metric_key'],
                'value'     => (float) $r['value'],
                'label_de'  => $de[$id] ?? '',
                'label_en'  => $en[$id] ?? '',
                'is_sample' => (bool) $r['is_sample'],
            ];
        }, $rows);
    }

    /** @return list<array<string,mixed>> */
    private static function eventCounts(): array
    {
        $rows = Database::all(
            'SELECT a.event_id, COUNT(*) AS assigned
             FROM event_assignments a JOIN events e ON e.id = a.event_id
             WHERE e.is_published = 1 GROUP BY a.event_id'
        );
        return array_map(static fn (array $r): array => [
            'event_id' => (string) $r['event_id'], 'assigned' => (int) $r['assigned'],
        ], $rows);
    }

    /**
     * Public finance aggregates — privacy-aware sums ONLY, never donor rows.
     * received = actually received/confirmed donations (donations table),
     * spent = recorded expenses, available = received - spent.
     * @return array<string,mixed>
     */
    private static function publicFinances(): array
    {
        $received     = (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM donations WHERE type_id = (SELECT id FROM donation_types WHERE slug="money")');
        $receivedCnt  = (int) Database::scalar('SELECT COUNT(*) FROM donations');
        $spent        = (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM expenses');
        $byCategory   = Database::all(
            'SELECT c.slug AS category, SUM(e.amount) AS total
             FROM expenses e JOIN expense_categories c ON c.id = e.category_id
             GROUP BY c.slug ORDER BY total DESC'
        );
        $recent = Database::all(
            'SELECT c.slug AS category, e.spent_on, e.description, e.amount
             FROM expenses e JOIN expense_categories c ON c.id = e.category_id
             ORDER BY e.spent_on DESC, e.created_at DESC LIMIT 6'
        );
        foreach ($byCategory as &$r) {
            $r['total'] = (float) $r['total'];
        }
        unset($r);
        foreach ($recent as &$r) {
            $r['amount'] = (float) $r['amount'];
        }
        unset($r);
        return [
            'received'       => round($received, 2),
            'received_count' => $receivedCnt,
            'spent'          => round($spent, 2),
            'available'      => round($received - $spent, 2),
            'by_category'    => array_map(static fn (array $r): array => [
                'category' => (string) $r['category'], 'total' => (float) $r['total'],
            ], $byCategory),
            'recent'         => array_map(static fn (array $r): array => [
                'spent_on'   => (string) $r['spent_on'],
                'category'   => (string) $r['category'],
                'description' => $r['description'] !== null ? (string) $r['description'] : '',
                'amount'     => (float) $r['amount'],
            ], $recent),
            // flat list used by the homepage transparency section
            'expenses'       => array_map(static fn (array $r): array => [
                'category'    => (string) $r['category'],
                'description' => $r['description'] !== null ? (string) $r['description'] : '',
                'amount'      => (float) $r['amount'],
            ], $recent),
        ];
    }

    /** Public settings subset (no secrets). @return array<string,mixed> */
    private static function settings(string $lang = 'de'): array
    {
        $s = Database::one('SELECT * FROM site_settings WHERE id = 1');
        if ($s === null) {
            return [];
        }
        $instruction = Database::one(
            'SELECT instructions FROM site_bank_instructions WHERE bank_id = 1 AND lang = :l',
            [':l' => $lang]
        );
        $ig = Database::one('SELECT handle FROM site_instagram WHERE id = 1');
        $igPosts = array_map(
            static fn (array $r): string => (string) $r['post_url'],
            Database::all('SELECT post_url FROM site_instagram_tiles WHERE setting_id = 1 AND post_url IS NOT NULL ORDER BY position LIMIT 9')
        );
        return [
            'request_enabled'  => (bool) $s['request_enabled'],
            'bank_enabled'     => (bool) $s['bank_enabled'],
            'bank_holder'      => $s['bank_holder'],
            'bank_iban'        => $s['bank_iban'],
            'bank_bic'         => $s['bank_bic'],
            'bank_name'        => $s['bank_name'],
            'bank_reference'   => $s['bank_reference'],
            'bank_instructions' => $instruction !== null && $instruction['instructions'] !== null ? (string) $instruction['instructions'] : null,
            'contact_email'    => $s['office_email'],
            'contact_address'  => $s['contact_address'],
            'instagram_handle' => $ig['handle'] ?? 'mensacomunistagraz',
            'instagram_posts'  => $igPosts,
        ];
    }

    /** @return array<string,mixed> */
    private static function instagramTiles(): array
    {
        $row = Database::one('SELECT handle, grid_enabled FROM site_instagram WHERE id = 1');
        $tiles = Database::all(
            'SELECT t.position, t.post_url, t.alt, m.id AS media_id, m.replaced_at
             FROM site_instagram_tiles t LEFT JOIN media_assets m ON m.id = t.media_id AND m.is_deleted = 0
             WHERE t.setting_id = 1 ORDER BY t.position LIMIT 9'
        );
        return [
            'instagram_handle' => $row['handle'] ?? 'mensacomunistagraz',
            'grid_enabled'     => (bool) ($row['grid_enabled'] ?? true),
            'tiles'            => array_map(static fn (array $r): array => [
                'position' => (int) $r['position'],
                'post_url' => $r['post_url'] !== null ? (string) $r['post_url'] : null,
                'alt'      => $r['alt'] !== null ? (string) $r['alt'] : null,
                'image_url' => $r['media_id'] !== null ? Media::url((string) $r['media_id'], isset($r['replaced_at']) ? (string) $r['replaced_at'] : null) : null,
            ], $tiles),
        ];
    }

    /** @return array<string,array{title:string,body:string}> */
    private static function pageTexts(string $lang): array
    {
        $rows = Database::all(
            'SELECT page, title, body FROM site_page_texts WHERE lang = :l',
            [':l' => $lang]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['page']] = ['title' => (string) $r['title'], 'body' => (string) $r['body']];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    //  TEAM BUNDLE (planner)
    // ------------------------------------------------------------------
    /** @return array<string,mixed> */
    public static function teamData(string $userId, array $roles): array
    {
        $profile = Database::one('SELECT user_id, display_name, phone FROM profiles WHERE user_id = :i', [':i' => $userId]);
        $skills  = Database::all(
            'SELECT s.slug FROM profile_skills ps JOIN skills s ON s.id = ps.skill_id WHERE ps.user_id = :i ORDER BY s.slug',
            [':i' => $userId]
        );
        $events = Database::all(
            'SELECT e.id, e.event_date, e.start_time, e.end_time, e.volunteers_needed, e.status, e.is_published, e.is_demo, e.cover_media_id,
                    e.needs_review, e.review_note, e.internal_notes, e.location_id, e.meeting_point,
                    l.name AS location, c.slug AS activity_type, et.title, et.description, et.extra_info, m.replaced_at AS cover_replaced
             FROM events e
             LEFT JOIN (SELECT event_id, title, description, extra_info FROM event_translations WHERE lang = "de") et ON et.event_id = e.id
             LEFT JOIN event_locations l ON l.id = e.location_id
             LEFT JOIN activity_categories c ON c.id = e.activity_category_id
             LEFT JOIN media_assets m ON m.id = e.cover_media_id AND m.is_deleted = 0
             ORDER BY e.event_date, e.start_time'
        );
        $assignments = Database::all(
            'SELECT a.id, a.event_id, a.user_id, a.person_name, a.constraint_note, a.created_at
             FROM event_assignments a'
        );
        $profiles = Database::all(
            'SELECT p.user_id AS id, p.display_name, p.is_active AS active
             FROM profiles p WHERE p.is_active = 1 ORDER BY p.display_name'
        );
        $profileSkills = Database::all('SELECT ps.user_id, s.slug FROM profile_skills ps JOIN skills s ON s.id = ps.skill_id');
        $skillsByUser = [];
        foreach ($profileSkills as $r) {
            $skillsByUser[$r['user_id']][] = (string) $r['slug'];
        }
        $evOut = array_map(static function (array $r): array {
            return [
                'id' => (string) $r['id'],
                'event_date' => (string) $r['event_date'],
                'title' => (string) ($r['title'] ?? ''),
                'activity_type' => (string) $r['activity_type'],
                'meeting_point' => $r['meeting_point'] !== null ? (string) $r['meeting_point'] : null,
                'location' => $r['location'] !== null ? (string) $r['location'] : null,
                'location_id' => $r['location_id'] !== null ? (int) $r['location_id'] : null,
                'start_time' => $r['start_time'] !== null ? substr((string) $r['start_time'], 0, 5) : null,
                'end_time' => $r['end_time'] !== null ? substr((string) $r['end_time'], 0, 5) : null,
                'volunteers_needed' => $r['volunteers_needed'] !== null ? (int) $r['volunteers_needed'] : null,
                'extra_info' => $r['extra_info'] !== null ? (string) $r['extra_info'] : null,
                'description' => $r['description'] !== null ? (string) $r['description'] : null,
                'internal_notes' => $r['internal_notes'] !== null ? (string) $r['internal_notes'] : null,
                'needs_review' => (bool) $r['needs_review'],
                'review_note' => $r['review_note'] !== null ? (string) $r['review_note'] : null,
                'cover_url' => $r['cover_media_id'] !== null
                    ? Media::url((string) $r['cover_media_id'], isset($r['cover_replaced']) ? (string) $r['cover_replaced'] : null)
                    : null,
                'status' => (string) $r['status'],
                'published' => (bool) $r['is_published'],
                'is_demo' => (bool) $r['is_demo'],
            ];
        }, $events);
        return [
            'userId'     => $userId,
            'roles'      => $roles,
            'profile'    => $profile ? [
                'display_name' => $profile['display_name'],
                'phone'        => $profile['phone'],
                'skills'       => array_map(static fn (array $r): string => (string) $r['slug'], $skills),
            ] : null,
            'events'     => $evOut,
            'assignments'=> array_map(static fn (array $r): array => [
                'id' => (string) $r['id'],
                'event_id' => (string) $r['event_id'],
                'user_id' => $r['user_id'] !== null ? (string) $r['user_id'] : null,
                'person_name' => (string) $r['person_name'],
                'constraint_note' => $r['constraint_note'] !== null ? (string) $r['constraint_note'] : null,
            ], $assignments),
            'profiles'   => array_map(static fn (array $r): array => [
                'id' => (string) $r['id'],
                'display_name' => $r['display_name'],
                'skills' => $skillsByUser[$r['id']] ?? [],
                'active' => (bool) $r['active'],
            ], $profiles),
        ];
    }
}
