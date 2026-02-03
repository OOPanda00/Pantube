<?php
/**
 * Pantube - Database Core
 * PDO Singleton Connection
 * PHP 7.4 Compatible
 */

class Database
{
    /**
     * @var PDO|null
     */
    private static $instance = null;

    /**
     * Prevent direct object creation
     */
    private function __construct() {}

    /**
     * Prevent object cloning
     */
    private function __clone() {}

    /**
     * Get PDO instance
     *
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {

            $config = require __DIR__ . '/../config/database.php';

            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {

                // Log error securely
                error_log(
                    '[' . date('Y-m-d H:i:s') . '] DB ERROR: ' . $e->getMessage() . PHP_EOL,
                    3,
                    __DIR__ . '/../../logs/error.log'
                );

                // Generic error for user
                http_response_code(500);
                exit('Database connection error.');
            }
        }

        return self::$instance;
    }
}
