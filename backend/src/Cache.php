<?php
declare(strict_types=1);

namespace Mcg;

/** Homepage cache version: bump after every CMS write used as ETag input. */
final class Cache
{
    public static function version(): string
    {
        return (string) Database::scalar('SELECT version FROM homepage_cache WHERE id = 1');
    }

    public static function bump(): void
    {
        Database::run('UPDATE homepage_cache SET version = version + 1 WHERE id = 1');
    }
}
