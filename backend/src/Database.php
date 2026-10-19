<?php
declare(strict_types=1);

namespace Mcg;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;
    /** @var array<string,mixed>|null */
    private static ?array $config = null;

    /**
     * Configuration search order:
     *  1. Environment variable MCG_CONFIG_DIR (absolute dir that contains database.php)
     *  2. backend/config/database.php  (default layout)
     * database.example.php is never loaded.
     */
    public static function config(): array
    {
        if (self::$config === null) {
            $candidates = [];
            if (getenv('MCG_CONFIG_DIR') !== false) {
                $candidates[] = rtrim(getenv('MCG_CONFIG_DIR'), '/\\') . '/database.php';
            }
            $here = \dirname(__DIR__);                     // .../backend
            $candidates[] = $here . '/config/database.php';                       // backend/config  (standard layout)
            $candidates[] = \dirname($here) . '/backend/config/database.php';     // public root alternative
            foreach ($candidates as $file) {
                if (is_file($file)) {
                    $cfg = require $file;
                    if (!is_array($cfg)) {
                        throw new RuntimeException('Invalid database configuration file.');
                    }
                    self::$config = $cfg;
                    break;
                }
            }
            if (self::$config === null) {
                throw new RuntimeException(
                    'Database configuration missing. Copy backend/config/database.example.php to database.php outside public_html.'
                );
            }
        }
        return self::$config;
    }

    /** Reusable connection within the whole request lifecycle. */
    public static function conn(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $cfg = self::config();
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            (string) ($cfg['port'] ?? '3306'),
            $cfg['database'],
            (string) ($cfg['charset'] ?? 'utf8mb4')
        );
        try {
            self::$pdo = new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => sprintf("SET NAMES %s COLLATE %s", $cfg['charset'] ?? 'utf8mb4', ($cfg['charset'] ?? 'utf8mb4') . '_unicode_ci'),
            ]);
        } catch (PDOException $e) {
            error_log('[mcg] DB connect failed: ' . $e->getMessage()); // detail only in logs, never in responses
            throw new RuntimeException('Database is temporarily unavailable.');
        }
        return self::$pdo;
    }

    /** @param array<string,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $stmt->bindValue($name, $value);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = self::run($sql, $params)->fetchAll();
        return $rows;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::run($sql, $params)->fetchColumn();
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
