<?php
/**
 * Pantube Global Configuration
 * Environment Variables Version
 * PHP 7.4
 */

// Helper function to parse size strings (must be defined BEFORE use)
function parse_size($size) {
    $size = strtoupper(trim($size));
    
    // Define units mapping
    $units = [
        'B'  => 1,
        'KB' => 1024,
        'MB' => 1024 * 1024,
        'GB' => 1024 * 1024 * 1024,
        'TB' => 1024 * 1024 * 1024 * 1024,
    ];
    
    // Extract numeric part and unit part
    if (preg_match('/^([\d.]+)\s*([A-Z]+)$/', $size, $matches)) {
        $number = (float) $matches[1];
        $unit = $matches[2];
        
        if (isset($units[$unit])) {
            return (int) ($number * $units[$unit]);
        }
    }
    
    // If just a number, return it as is
    if (is_numeric($size)) {
        return (int) $size;
    }
    
    return 0;
}

// Load environment from .env file
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Helper function to get env with default
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

return [

    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false) === 'true',
    'name' => env('APP_NAME', 'Pantube'),
    'url' => env('APP_URL', 'https://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'paths' => [
        'root'    => realpath(__DIR__ . '/../..'),
        'app'     => realpath(__DIR__ . '/..'),
        'public'  => realpath(__DIR__ . '/../../public'),
        'views'   => realpath(__DIR__ . '/../views'),
        'uploads' => realpath(__DIR__ . '/../../public/uploads'),
        'logs'    => realpath(__DIR__ . '/../../logs'),
        'backup'  => env('BACKUP_PATH', '/var/backups/pantube'),
    ],

    'cdn' => [
        'enabled' => env('CDN_ENABLED', false) === 'true',
        'url' => env('CDN_URL', ''),
        'videos' => env('CDN_VIDEOS_PATH', '/videos'),
        'images' => env('CDN_IMAGES_PATH', '/images'),
        'assets' => env('CDN_ASSETS_PATH', '/assets'),
    ],

    'errors' => [
        'display' => env('APP_DEBUG', false) === 'true',
        'log'     => true,
        'log_file'=> __DIR__ . '/../../logs/error.log',
        'sentry_dsn' => env('SENTRY_DSN', ''),
    ],

    'upload' => [
        'max_video_size' => parse_size(env('UPLOAD_MAX_SIZE', '500MB')),
        'max_image_size' => parse_size('10MB'),
        'allowed_video_types' => explode(',', env('UPLOAD_VIDEO_TYPES', 'mp4')),
        'allowed_image_types' => explode(',', env('UPLOAD_IMAGE_TYPES', 'jpg,jpeg,png')),
        'chunk_size' => 10 * 1024 * 1024,
    ],

    'twofa' => [
        'enabled' => env('TWOFA_ENABLED', false) === 'true',
        'issuer' => env('TWOFA_ISSUER', 'Pantube'),
        'digits' => (int) env('TWOFA_DIGITS', 6),
        'period' => (int) env('TWOFA_PERIOD', 30),
        'window' => (int) env('TWOFA_WINDOW', 1),
    ],

    'cache' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'prefix' => env('CACHE_PREFIX', 'pantube_'),
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_CACHE_DB', 1),
        ],
        'ttl' => [
            'video' => 3600,
            'user' => 1800,
            'comments' => 900,
        ],
    ],

    'video' => [
        'queue' => env('VIDEO_QUEUE_ENABLED', false) === 'true',
        'formats' => explode(',', env('VIDEO_FORMATS', 'mp4')),
        'resolutions' => array_map('intval', explode(',', env('VIDEO_RESOLUTIONS', '360,720'))),
        'bitrates' => explode(',', env('VIDEO_BITRATES', '500k,1000k')),
        'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
        'watermark' => [
            'enabled' => env('VIDEO_WATERMARK_ENABLED', false) === 'true',
            'path' => env('VIDEO_WATERMARK_PATH', ''),
            'position' => 'bottom-right',
            'opacity' => 0.5,
        ],
    ],

    'pwa' => [
        'enabled' => env('PWA_ENABLED', false) === 'true',
        'name' => env('PWA_NAME', 'Pantube'),
        'short_name' => env('PWA_SHORT_NAME', 'Pantube'),
        'theme_color' => env('PWA_THEME_COLOR', '#ff4757'),
        'background_color' => env('PWA_BACKGROUND_COLOR', '#ffffff'),
    ],

];

