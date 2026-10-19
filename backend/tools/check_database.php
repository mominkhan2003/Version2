<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../src/bootstrap_cli.php';

use Mcg\Database;

$requiredTables = [
    'homepage_cache',
    'homepage_sections',
    'homepage_section_texts',
    'homepage_section_images',
    'homepage_section_links',
    'homepage_link_texts',
    'homepage_list_items',
    'media_assets',
    'community_stories',
    'community_story_texts',
    'events',
    'event_translations',
    'event_locations',
    'activity_categories',
    'impact_metrics',
    'impact_metric_texts',
    'event_assignments',
    'donations',
    'donation_types',
    'expenses',
    'expense_categories',
    'site_settings',
    'site_bank_instructions',
    'site_instagram',
    'site_instagram_tiles',
    'site_page_texts',
];

try {
    $tables = array_map(
        static fn (array $row): string => (string) reset($row),
        Database::all('SHOW TABLES')
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Database configuration or connection failed. Check the private database.php and the PHP error log.\n");
    exit(1);
}

$missing = array_values(array_diff($requiredTables, $tables));
if ($missing !== []) {
    fwrite(STDERR, "Database connected, but schema tables are missing: " . implode(', ', $missing) . "\n");
    fwrite(STDERR, "Import database/schema.sql, then database/seed.sql with phpMyAdmin.\n");
    exit(2);
}

$version = Database::scalar('SELECT version FROM homepage_cache WHERE id = 1');
if ($version === false) {
    fwrite(STDERR, "Schema is present, but homepage seed data is missing. Import database/seed.sql.\n");
    exit(3);
}

fwrite(STDOUT, "PASS: database connection, homepage schema, and seed version are available.\n");
