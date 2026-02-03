<?php
/**
 * Two-Factor Authentication using TOTP
 * RFC 6238 compliant
 */

class TwoFA
{
    private static $algorithm = 'sha1';
    private static $digits = 6;
    private static $period = 30;
    private static $issuer = 'Pantube';

    public static function generateSecret(): string
    {
        $secret = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }

        return $secret;
    }

    public static function getQRCodeUrl(string $email, string $secret): string
    {
        $cfg = App::$config['twofa'];

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d&algorithm=%s',
            rawurlencode($cfg['issuer'] ?? self::$issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($cfg['issuer'] ?? self::$issuer),
            $cfg['digits'] ?? self::$digits,
            $cfg['period'] ?? self::$period,
            self::$algorithm
        );
    }

    public static function verify(string $secret, string $code): bool
    {
        $cfg = App::$config['twofa'];
        $window = $cfg['window'] ?? 1;
        $timestamp = floor(time() / ($cfg['period'] ?? self::$period));

        for ($i = -$window; $i <= $window; $i++) {
            if (self::generateCode($secret, $timestamp + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    private static function generateCode(string $secret, int $timestamp): string
    {
        $hash = hash_hmac(
            self::$algorithm,
            pack('J', $timestamp),
            self::base32Decode($secret),
            true
        );

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $mod = pow(10, App::$config['twofa']['digits'] ?? self::$digits);

        return str_pad($value % $mod, App::$config['twofa']['digits'] ?? self::$digits, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $out = '';

        foreach (str_split(strtoupper($secret)) as $c) {
            if ($c === '=') continue;
            $val = strpos($map, $c);
            if ($val === false) {
                throw new Exception('Invalid base32 char');
            }

            $buffer = ($buffer << 5) | $val;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $out;
    }

    public static function isEnabled(int $userId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT twofa_secret FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return !empty($stmt->fetch()['twofa_secret']);
    }

    public static function enable(int $userId, string $secret): bool
    {
        $db = Database::getInstance();
        return $db->prepare('UPDATE users SET twofa_secret = ? WHERE id = ?')
                  ->execute([$secret, $userId]);
    }

    public static function disable(int $userId): bool
    {
        $db = Database::getInstance();
        return $db->prepare('UPDATE users SET twofa_secret = NULL WHERE id = ?')
                  ->execute([$userId]);
    }
}
