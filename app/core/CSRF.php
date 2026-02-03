<?php
/**
 * CSRF Protection Core - Enhanced
 */

class CSRF
{
    private static $token_name = '_csrf_token';
    private static $header_name = 'X-CSRF-TOKEN';

    public static function generate(): string
    {
        if (empty($_SESSION[self::$token_name])) {
            $token = bin2hex(random_bytes(32));
            $session_id = session_id();
            $token_hash = hash_hmac('sha256', $token . $session_id, self::getAppKey());

            $_SESSION[self::$token_name] = [
                'token' => $token,
                'hash' => $token_hash,
                'created_at' => time(),
                'uses' => 0,
            ];
        }

        if (
            $_SESSION[self::$token_name]['uses'] > 10 ||
            (time() - $_SESSION[self::$token_name]['created_at']) > 3600
        ) {
            self::destroy();
            return self::generate();
        }

        $_SESSION[self::$token_name]['uses']++;
        return $_SESSION[self::$token_name]['token'];
    }

    public static function validate(?string $token): bool
    {
        if (!isset($_SESSION[self::$token_name])) {
            return false;
        }

        if (!$token) {
            $headers = getallheaders();
            $token = $headers[self::$header_name] ?? null;
            if (!$token) {
                return false;
            }
        }

        $stored = $_SESSION[self::$token_name];
        $expected = hash_hmac(
            'sha256',
            $token . session_id(),
            self::getAppKey()
        );

        if (!hash_equals($stored['hash'], $expected)) {
            return false;
        }

        if ((time() - $stored['created_at']) > 7200) {
            self::destroy();
            return false;
        }

        return true;
    }

    public static function destroy(): void
    {
        unset($_SESSION[self::$token_name]);
    }

    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' .
            htmlspecialchars(self::generate(), ENT_QUOTES, 'UTF-8') .
            '">';
    }

    public static function header(): string
    {
        return self::$header_name . ': ' . self::generate();
    }

    private static function getAppKey(): string
    {
        $key = $_ENV['APP_KEY'] ?? 'default-key-change-in-production';

        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }
}
