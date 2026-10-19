<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../src/bootstrap_cli.php';

use Mcg\Database;

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "PHP extension pdo_mysql is not enabled. Enable it for the selected cPanel PHP version.\n");
    exit(1);
}

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
    Database::config();
    Database::conn();
    $tables = array_map(
        static fn (array $row): string => (string) reset($row),
        Database::all('SHOW TABLES')
    );
} catch (Throwable $e) {
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

if (Database::scalar('SELECT COUNT(*) FROM site_settings WHERE id = 1') === 0) {
    fwrite(STDERR, "Database schema is present, but site settings are not seeded. Import database/seed.sql.\n");
    exit(3);
}

fwrite(STDOUT, "PASS: database connection, homepage schema, and seed version are available.\n");
