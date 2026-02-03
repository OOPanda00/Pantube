<?php
/**
 * Pantube Application Core
 * Responsible for bootstrapping the app
 * PHP 7.4
 */

class App
{
    /**
     * Global config
     */
    public static array $config = [];

    /**
     * Start the application
     */
    public static function run(): void
    {
        // Load configuration
        self::$config = require __DIR__ . '/../config/config.php';

        // Error handling
        self::handleErrors();

        // Autoload core, controllers, models
        spl_autoload_register(function ($class) {
            $basePath = self::$config['paths']['app'];

            $paths = [
                $basePath . '/core/' . $class . '.php',
                $basePath . '/controllers/' . $class . '.php',
                $basePath . '/models/' . $class . '.php',
            ];

            foreach ($paths as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });

        // Start session securely
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Error & exception handling
     */
    private static function handleErrors(): void
    {
        if (self::$config['env'] === 'development') {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(0);
        }

        if (self::$config['errors']['log']) {
            ini_set('log_errors', '1');
            ini_set('error_log', self::$config['errors']['log_file']);
        }
    }
}
