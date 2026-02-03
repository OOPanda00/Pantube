<?php
/**
 * Authentication & Authorization Core - Enhanced
 */

class Auth
{
    private static $maxAttempts = 5;
    private static $lockoutTime = 900;
    private static $sessionTimeout = 7200;

    public static function check(): bool
    {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        if (
            isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > self::$sessionTimeout
        ) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;

        $id = (int)$_SESSION['user']['id'];

        return Cache::user($id, function () use ($id) {
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        });
    }

    public static function validate(string $email, string $password): ?array
    {
        if (self::getAttempts($email) >= self::$maxAttempts) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            self::logAttempt($email, false, 'Invalid credentials');
            return null;
        }

        if (($user['account_status'] ?? 'active') !== 'active') {
            self::logAttempt($email, false, 'Account inactive');
            return null;
        }

        return $user;
    }

    public static function login(array $user, bool $remember = false, ?string $code2fa = null): bool
    {
        if (TwoFA::isEnabled($user['id'])) {
            if (!$code2fa || !TwoFA::verify($user['twofa_secret'], $code2fa)) {
                self::logAttempt($user['email'], false, 'Invalid 2FA');
                return false;
            }
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        $_SESSION['last_activity'] = time();

        self::clearAttempts($user['email']);
        self::logAttempt($user['email'], true, 'Login success');

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
    }

    private static function logAttempt(string $id, bool $success, string $reason = ''): void
    {
        $db = Database::getInstance();
        $db->prepare(
            'INSERT INTO login_attempts (ip_address, user_identifier, success, reason) VALUES (?, ?, ?, ?)'
        )->execute([
            $_SERVER['REMOTE_ADDR'] ?? '',
            $id,
            $success ? 1 : 0,
            $reason
        ]);

        if (!$success) {
            $key = 'login_attempts_' . md5($id);
            Cache::set($key, self::getAttempts($id) + 1, self::$lockoutTime);
        }
    }

    private static function getAttempts(string $id): int
    {
        return Cache::get('login_attempts_' . md5($id), 0);
    }

    private static function clearAttempts(string $id): void
    {
        Cache::delete('login_attempts_' . md5($id));
    }

    public static function isAdmin(): bool
    {
        return self::check() && ($_SESSION['user']['role'] ?? '') === 'admin';
    }

    public static function isOwner(): bool
    {
        return self::check() && ($_SESSION['user']['role'] ?? '') === 'owner';
    }

    public static function canManageAdmins(): bool
    {
        return self::check() && ($_SESSION['user']['role'] ?? '') === 'owner';
    }
}
