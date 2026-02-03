<?php
/**
 * Pantube Database Configuration
 * Reads from environment variables
 * PHP Version: 7.4
 * DB: MySQL/MariaDB
 */

// Ensure config is loaded first
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

return [
    // Database host
    'host' => env('DB_HOST', 'localhost'),

    // Database name
    'database' => env('DB_NAME', 'pandaa'),

    // Database user
    'username' => env('DB_USERNAME', 'root'),

    // Database password
    'password' => env('DB_PASSWORD', '123456'),

    // Charset
    'charset' => env('DB_CHARSET', 'utf8mb4'),

    // PDO options
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
