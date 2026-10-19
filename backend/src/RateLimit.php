<?php
declare(strict_types=1);

namespace Mcg;

/** Simple DB-backed rate limiter (public forms + login attempts). */
final class RateLimit
{
    public static function ipHash(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return hash('sha256', trim($ip) . '|mcg');
    }

    /**
     * Returns true when allowed; records the attempt otherwise.
     * Past attempts are cleaned lazily.
     */
    public static function hit(string $bucket, ?string $subject = null, int $max = 10, int $windowSeconds = 900): bool
    {
        $db = Database::conn();
        $db->prepare('DELETE FROM rate_limits WHERE created_at < (NOW(6) - INTERVAL ? DAY)')
            ->execute([max(1, (int) ceil($windowSeconds / 86400) + 1)]);
        $sql = 'SELECT COUNT(*) FROM rate_limits WHERE bucket = :b AND ip_hash = :ip AND created_at >= (NOW(6) - INTERVAL :win SECOND)';
        $params = [':b' => $bucket, ':ip' => self::ipHash(), ':win' => $windowSeconds];
        if ($subject !== null) {
            $sql .= ' AND (subject = :s OR subject IS NULL)';
            $params[':s'] = $subject;
        }
        $count = (int) Database::scalar($sql, $params);
        if ($count >= $max) {
            return false;
        }
        Database::run(
            'INSERT INTO rate_limits (bucket, ip_hash, subject) VALUES (:b, :ip, :s)',
            [':b' => $bucket, ':ip' => self::ipHash(), ':s' => $subject]
        );
        return true;
    }
}
