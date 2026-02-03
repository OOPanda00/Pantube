<?php
/**
 * Cache Manager with Redis & File fallback
 */

class Cache
{
    private static $driver = null;
    private static $config = [];
    private static $enabled = true;

    public static function init(): void
    {
        self::$config = App::$config['cache'];
        $driver = self::$config['driver'];

        try {
            switch ($driver) {
                case 'redis':
                    self::initRedis();
                    break;
                case 'file':
                    self::initFile();
                    break;
                case 'apc':
                    self::initAPC();
                    break;
                default:
                    self::$enabled = false;
            }
        } catch (Exception $e) {
            self::$enabled = false;
            error_log('Cache init failed: ' . $e->getMessage());
        }
    }

    public static function get(string $key, $default = null)
    {
        if (!self::$enabled || !self::$driver) return $default;

        try {
            $value = self::$driver->get(self::prefix($key));
            return $value !== false ? unserialize($value) : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!self::$enabled || !self::$driver) return false;

        try {
            $ttl = $ttl ?? self::$config['ttl']['default'] ?? 3600;
            return self::$driver->setex(
                self::prefix($key),
                $ttl,
                serialize($value)
            );
        } catch (Exception $e) {
            return false;
        }
    }

    public static function delete(string $key): bool
    {
        if (!self::$enabled || !self::$driver) return false;

        try {
            return self::$driver->del(self::prefix($key)) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function clear(): bool
    {
        if (!self::$enabled || !self::$driver) return false;

        try {
            if (self::$config['driver'] === 'redis') {
                $keys = self::$driver->keys(self::prefix('*'));
                if ($keys) self::$driver->del($keys);
            } elseif (self::$config['driver'] === 'file') {
                foreach (glob(self::$config['file']['path'] . '/' . self::prefix('*')) as $file) {
                    unlink($file);
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function video(int $id, callable $callback)
    {
        $key = "video_$id";
        $cached = self::get($key);
        if ($cached !== null) return $cached;

        $data = $callback();
        self::set($key, $data, self::$config['ttl']['video'] ?? 3600);
        return $data;
    }

    public static function user(int $id, callable $callback)
    {
        $key = "user_$id";
        $cached = self::get($key);
        if ($cached !== null) return $cached;

        $data = $callback();
        self::set($key, $data, self::$config['ttl']['user'] ?? 1800);
        return $data;
    }

    public static function comments(int $id, callable $callback)
    {
        $key = "comments_$id";
        $cached = self::get($key);
        if ($cached !== null) return $cached;

        $data = $callback();
        self::set($key, $data, self::$config['ttl']['comments'] ?? 900);
        return $data;
    }

    private static function initRedis(): void
    {
        if (!class_exists('Redis')) {
            throw new Exception('Redis extension missing');
        }

        $redis = new Redis();
        $cfg = self::$config['redis'];

        if (!$redis->connect($cfg['host'], $cfg['port'], 2)) {
            throw new Exception('Redis connection failed');
        }

        if ($cfg['password']) {
            $redis->auth($cfg['password']);
        }

        $redis->select($cfg['database']);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);

        self::$driver = $redis;
    }

    private static function initFile(): void
    {
        $path = App::$config['paths']['root'] . '/storage/cache';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        self::$config['file'] = ['path' => $path];

        self::$driver = new class($path) {
            private $path;

            public function __construct(string $path) {
                $this->path = $path;
            }

            public function get(string $key) {
                $file = $this->path . '/' . md5($key);
                if (!file_exists($file)) return false;

                $data = unserialize(file_get_contents($file));
                if ($data['expires'] < time()) {
                    unlink($file);
                    return false;
                }
                return $data['value'];
            }

            public function setex(string $key, int $ttl, $value): bool {
                $file = $this->path . '/' . md5($key);
                $data = [
                    'value' => $value,
                    'expires' => time() + $ttl,
                ];
                return file_put_contents($file, serialize($data)) !== false;
            }

            public function del(string $key): int {
                $file = $this->path . '/' . md5($key);
                if (file_exists($file)) {
                    unlink($file);
                    return 1;
                }
                return 0;
            }
        };
    }

    private static function initAPC(): void
    {
        if (!function_exists('apcu_fetch')) {
            throw new Exception('APCu missing');
        }

        self::$driver = new class() {
            public function get(string $key) {
                return apcu_fetch($key);
            }
            public function setex(string $key, int $ttl, $value): bool {
                return apcu_store($key, $value, $ttl);
            }
            public function del(string $key): int {
                return apcu_delete($key) ? 1 : 0;
            }
        };
    }

    private static function prefix(string $key): string
    {
        return self::$config['prefix'] . $key;
    }
}
