<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../src/bootstrap_cli.php';

use Mcg\Database;
use Mcg\Mappers;

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "PHP extension pdo_mysql is not enabled. Enable it for the selected cPanel PHP version.\n");
    exit(1);
}

$requiredColumns = [
    'homepage_cache' => ['id', 'version'],
    'homepage_sections' => ['id', 'slug', 'kind', 'position', 'is_visible'],
    'homepage_section_texts' => ['section_id', 'lang', 'field_key', 'value'],
    'homepage_section_images' => ['section_id', 'role', 'position', 'media_id'],
    'homepage_section_links' => ['id', 'section_id', 'link_key', 'href'],
    'homepage_link_texts' => ['link_id', 'lang', 'label'],
    'homepage_list_items' => ['section_id', 'lang', 'group_key', 'position', 'value'],
    'media_assets' => ['id', 'replaced_at', 'is_deleted'],
    'community_stories' => ['id', 'position', 'is_visible', 'media_id'],
    'community_story_texts' => ['story_id', 'lang', 'title', 'body'],
    'events' => ['id', 'event_date', 'start_time', 'end_time', 'volunteers_needed', 'status', 'is_published', 'is_demo', 'cover_media_id', 'meeting_point', 'location_id', 'activity_category_id'],
    'event_translations' => ['event_id', 'lang', 'title', 'description', 'extra_info'],
    'event_locations' => ['id', 'name'],
    'activity_categories' => ['id', 'slug'],
    'impact_metrics' => ['id', 'metric_key', 'value', 'is_sample', 'is_visible', 'position'],
    'impact_metric_texts' => ['metric_id', 'lang', 'label'],
    'event_assignments' => ['event_id'],
    'donations' => ['amount', 'type_id'],
    'donation_types' => ['id', 'slug'],
    'expenses' => ['amount', 'category_id', 'spent_on', 'description', 'created_at'],
    'expense_categories' => ['id', 'slug'],
    'site_settings' => ['id', 'request_enabled', 'bank_enabled', 'bank_holder', 'bank_iban', 'bank_bic', 'bank_name', 'bank_reference', 'office_email', 'contact_address'],
    'site_bank_instructions' => ['bank_id', 'lang', 'instructions'],
    'site_instagram' => ['id', 'handle', 'grid_enabled'],
    'site_instagram_tiles' => ['setting_id', 'position', 'post_url', 'alt', 'media_id'],
    'site_page_texts' => ['page', 'lang', 'title', 'body'],
];

try {
    Database::config();
    Database::conn();
    $tables = array_map(
        static fn (array $row): string => (string) reset($row),
        Database::all('SHOW TABLES')
    );
    $missingTables = array_values(array_diff(array_keys($requiredColumns), $tables));
    if ($missingTables !== []) {
        fwrite(STDERR, "Database is missing required tables: " . implode(', ', $missingTables) . "\n");
        fwrite(STDERR, "For a new empty database only, import database/schema.sql followed by database/seed.sql. For existing production data, use a reviewed migration.\n");
        exit(2);
    }

    foreach ($requiredColumns as $table => $columns) {
        $actualColumns = array_column(Database::all("SHOW COLUMNS FROM `$table`"), 'Field');
        $missingColumns = array_values(array_diff($columns, $actualColumns));
        if ($missingColumns !== []) {
            fwrite(STDERR, "Database table '$table' is missing columns: " . implode(', ', $missingColumns) . "\n");
            fwrite(STDERR, "Back up production data and apply a reviewed migration matching database/schema.sql; do not re-import schema.sql into an existing database.\n");
            exit(2);
        }
    }
} catch (Throwable $e) {
    error_log('[mcg] database preflight failed: ' . $e->getMessage());
    if ($e instanceof PDOException || $e->getPrevious() instanceof PDOException) {
        $pdoError = $e instanceof PDOException ? $e : $e->getPrevious();
        $state = (string) ($pdoError->errorInfo[0] ?? $pdoError->getCode());
        $driverCode = $pdoError->errorInfo[1] ?? 'unknown';
        fwrite(STDERR, "MySQL connection failed (SQLSTATE $state, driver code $driverCode). Verify the cPanel hostname, database/user prefixes, password, port, and user privileges.\n");
    } else {
        fwrite(STDERR, $e->getMessage() . "\n");
    }
    fwrite(STDERR, "If those settings are correct, check the cPanel PHP error log for the detailed server-side error.\n");
    exit(1);
}

$version = Database::scalar('SELECT version FROM homepage_cache WHERE id = 1');
if ($version === false) {
    fwrite(STDERR, "Schema is present, but homepage seed data is missing. On a new database only, import database/seed.sql after database/schema.sql.\n");
    exit(3);
}

if (Database::scalar('SELECT COUNT(*) FROM site_settings WHERE id = 1') === 0) {
    fwrite(STDERR, "Database schema is present, but site settings are not seeded. On a new database only, import database/seed.sql after database/schema.sql.\n");
    exit(3);
}

foreach (['de', 'en'] as $lang) {
    try {
        Mappers::publicData($lang);
    } catch (Throwable $e) {
        error_log('[mcg] homepage preflight failed for lang=' . $lang . ': ' . $e->getMessage());
        $pdoError = $e instanceof PDOException ? $e : $e->getPrevious();
        if ($pdoError instanceof PDOException) {
            $state = (string) ($pdoError->errorInfo[0] ?? $pdoError->getCode());
            $driverCode = $pdoError->errorInfo[1] ?? 'unknown';
            fwrite(STDERR, "Public homepage query failed for lang=$lang (SQLSTATE $state, driver code $driverCode). The detailed error was written to the PHP error log.\n");
        } else {
            fwrite(STDERR, "Public homepage query failed for lang=$lang. The detailed error was written to the PHP error log.\n");
        }
        fwrite(STDERR, "Check the matching PHP error-log entry for the failing public data step; do not re-import schema.sql or seed.sql into an existing production database.\n");
        exit(4);
    }
}

fwrite(STDOUT, "PASS: database connection, homepage schema, seed data, and public homepage queries (de/en) are available.\n");
