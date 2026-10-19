<?php
declare(strict_types=1);

namespace Mcg;

/**
 * Session-based authentication with bcrypt password hashing and
 * server-side role authorization. All role checks happen HERE so that
 * every admin API endpoint is protected independently of the frontend.
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $me = null;

    public static function sessionStart(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || self::isBehindTlsProxy();
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 7,   // 7 days sliding lifetime; server data GC
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('mcgsid');
        session_start();
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - (int) $_SESSION['created'] > 60 * 60 * 24) { // rotate id daily
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    private static function isBehindTlsProxy(): bool
    {
        $fwd = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return $fwd === 'https';
    }

    // ---------------------------------------------------------------- login
    public static function login(string $email, string $password): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            Response::error('Please enter e-mail and password.', 422);
        }
        if (!RateLimit::hit('login', $email, 8, 15 * 60)) {
            Response::error('Too many attempts. Please wait a moment.', 429);
        }
        $user = Database::one(
            'SELECT u.id, u.email, u.password_hash, u.status, u.must_change_password, u.last_login_at
             FROM users u WHERE u.email = :e', [':e' => $email]
        );
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            // uniform failure message — no user enumeration
            Response::error('Sign-in failed. Check your e-mail and password.', 401);
        }
        if ($user['status'] !== 'active') {
            Response::error('This account is inactive. Ask an administrator.', 403);
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = :h WHERE id = :i', [
                ':h' => password_hash($password, PASSWORD_DEFAULT), ':i' => $user['id'],
            ]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (string) $user['id'];
        Database::run('UPDATE users SET last_login_at = NOW(6) WHERE id = :i', [':i' => $user['id']]);
        Audit::log('login', 'users', $user['id']);
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['uid'])) {
            Audit::log('logout', 'users', $_SESSION['uid']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ------------------------------------------------------------- signing up
    public static function signup(string $email, string $name, string $password): array
    {
        $email = strtolower(trim($email));
        $name  = trim($name);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
            Response::error('Please enter a valid e-mail address.', 422, ['email' => 'invalid']);
        }
        if ($name === '' || mb_strlen($name) > 80) {
            Response::error('Please enter your name.', 422, ['name' => 'invalid']);
        }
        if (strlen($password) < 8 || strlen($password) > 200) {
            Response::error('Password needs at least 8 characters.', 422, ['password' => 'weak']);
        }
        if (!RateLimit::hit('signup', $email, 3, 3600)) {
            Response::error('Too many attempts. Please try again later.', 429);
        }
        $exists = Database::scalar('SELECT COUNT(*) FROM users WHERE email = :e', [':e' => $email]);
        if ((int) $exists > 0) {
            Response::error('This e-mail is already registered. Sign in instead.', 409, ['email' => 'exists']);
        }
        $id = Database::uuid();
        $db = Database::conn();
        $db->beginTransaction();
        try {
            Database::run(
                'INSERT INTO users (id, email, password_hash, status, must_change_password) VALUES (:id, :e, :h, "active", 0)',
                [':id' => $id, ':e' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT)]
            );
            Database::run(
                'INSERT INTO profiles (user_id, display_name) VALUES (:id, :n)',
                [':id' => $id, ':n' => $name]
            );
            Database::run(
                'INSERT INTO user_roles (user_id, role_id) SELECT :id, id FROM roles WHERE slug = "volunteer"',
                [':id' => $id]
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        Audit::log('signup', 'users', $id);
        return ['id' => $id, 'email' => $email, 'name' => $name];
    }

    // ---------------------------------------------------------------- session
    public static function userId(): ?string
    {
        if (!empty($_SESSION['uid']) && is_string($_SESSION['uid'])) {
            return $_SESSION['uid'];
        }
        return null;
    }

    /** @return array<string,mixed>|null full session context (cached per request) */
    public static function me(): ?array
    {
        if (self::$me !== null) {
            return self::$me;
        }
        $uid = self::userId();
        if ($uid === null) {
            return null;
        }
        $profile = Database::one('SELECT * FROM profiles WHERE user_id = :i', [':i' => $uid]);
        if ($profile === null || (int) $profile['is_active'] !== 1) {
            // invalid session: profile gone or deactivated → treat as signed out
            self::logout();
            return null;
        }
        $roleRows = Database::all(
            'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :i',
            [':i' => $uid]
        );
        $roles = array_map(static fn (array $r): string => (string) $r['slug'], $roleRows);
        self::$me = [
            'userId'   => $uid,
            'profile'  => [
                'display_name' => $profile['display_name'],
                'phone'        => $profile['phone'],
                'is_active'    => (bool) $profile['is_active'],
            ],
            'roles'    => $roles,
            'isStaff'  => in_array('admin', $roles, true) || in_array('coordinator', $roles, true),
            'isAdmin'  => in_array('admin', $roles, true),
        ];
        return self::$me ?? null;
    }

    // ------------------------------------------------------------ requirements
    public static function requireAuth(): array
    {
        $me = self::me();
        if ($me === null) {
            Response::error('Unauthorized. Please sign in.', 401);
        }
        return $me;
    }

    public static function requireStaff(): array
    {
        $me = self::requireAuth();
        if (!$me['isStaff']) {
            Response::error('Forbidden. Staff role required.', 403);
        }
        return $me;
    }

    public static function requireAdmin(): array
    {
        $me = self::requireAuth();
        if (!$me['isAdmin']) {
            Response::error('Forbidden. Admin role required.', 403);
        }
        return $me;
    }

    // ----------------------------------------------------------- password reset
    public static function requestReset(string $email): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !RateLimit::hit('reset', $email, 3, 3600)) {
            return; // no feedback either way (enumeration protection)
        }
        $user = Database::one('SELECT id FROM users WHERE email = :e AND status = "active"', [':e' => $email]);
        if ($user === null) {
            return;
        }
        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        Database::run(
            'INSERT INTO password_resets (id, user_id, token_hash, expires_at) VALUES (:id, :u, :h, DATE_ADD(NOW(6), INTERVAL 1 HOUR))',
            [':id' => Database::uuid(), ':u' => $user['id'], ':h' => $hash]
        );
        $cfg = Database::config();
        $link = rtrim((string) ($cfg['base_url'] ?? ''), '/') . '/reset-password?token=' . $raw;
        $from = (string) ($cfg['mail_from'] ?? 'no-reply@localhost');
        $headers = "From: Mensa Comunista Graz <{$from}>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $body = "Passwort zurücksetzen / Reset your password\r\n\r\n" . $link . "\r\n\r\n"
            . "Der Link ist 1 Stunde gültig. Falls du das nicht warst, ignoriere diese E-Mail.\r\n"
            . "The link is valid for 1 hour. If this wasn't you, ignore this e-mail.\r\n";
        @mail($email, '=?UTF-8?B?' . base64_encode('Passwort zurücksetzen') . '?=', $body, $headers);
    }

    public static function confirmReset(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            Response::error('Password needs at least 8 characters.', 422, ['password' => 'weak']);
        }
        $hash = hash('sha256', $token);
        $row = Database::one(
            'SELECT pr.id, pr.user_id FROM password_resets pr
             WHERE pr.token_hash = :h AND pr.used_at IS NULL AND pr.expires_at > NOW(6)',
            [':h' => $hash]
        );
        if ($row === null) {
            Response::error('This reset link is invalid or expired.', 400);
        }
        $db = Database::conn();
        $db->beginTransaction();
        try {
            Database::run('UPDATE users SET password_hash = :h WHERE id = :i', [
                ':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':i' => $row['user_id'],
            ]);
            Database::run('UPDATE password_resets SET used_at = NOW(6) WHERE id = :i', [':i' => $row['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        Audit::log('password_reset', 'users', $row['user_id']);
    }

    public static function changePassword(string $current, string $newPassword): void
    {
        $me = self::requireAuth();
        if (strlen($newPassword) < 8) {
            Response::error('Password needs at least 8 characters.', 422, ['password' => 'weak']);
        }
        $row = Database::one('SELECT password_hash FROM users WHERE id = :i', [':i' => $me['userId']]);
        if ($row === null || !password_verify($current, (string) $row['password_hash'])) {
            Response::error('Current password is wrong.', 401, ['current' => 'wrong']);
        }
        Database::run('UPDATE users SET password_hash = :h WHERE id = :i', [
            ':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':i' => $me['userId'],
        ]);
        Audit::log('password_change', 'users', (string) $me['userId']);
    }
}
