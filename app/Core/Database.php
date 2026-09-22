<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database Connection Manager (Singleton Pattern)
 * Provides centralized PDO connection to secure360_v2
 */
class Database
{
    private static ?PDO $instance = null;
    private static ?string $connectedDbName = null;

    /**
     * Protected constructor to prevent direct instantiation
     */
    private function __construct()
    {
    }

    /**
     * Get singleton PDO connection instance
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $configPath = dirname(__DIR__) . '/Config/database.php';
            if (!file_exists($configPath)) {
                throw new RuntimeException("Database configuration file not found at: {$configPath}");
            }

            $config = require $configPath;
            $isProduction = (bool)($config['is_production'] ?? false);
            $isConfigMissing = (bool)($config['config_missing'] ?? false);
            $dbConfig = $config['connections']['mysql'] ?? [];
            $source = (string)($config['config_source'] ?? 'unknown');

            $host = (string)($dbConfig['host'] ?? '');
            $port = (int)($dbConfig['port'] ?? 3306);
            $database = (string)($dbConfig['database'] ?? '');
            $username = (string)($dbConfig['username'] ?? '');
            $password = (string)($dbConfig['password'] ?? '');
            $charset = (string)($dbConfig['charset'] ?? 'utf8mb4');

            // Production guard: Throw clear error if database variables are missing
            if ($isConfigMissing || empty($host) || empty($database)) {
                $missingMsg = "Database configuration is missing. Configure the Railway MySQL environment variables (DB_HOST/MYSQLHOST, DB_NAME/MYSQLDATABASE, DB_USER/MYSQLUSER, DB_PASS/MYSQLPASSWORD, or DATABASE_URL).";
                error_log("[Secure360 Database][FATAL] " . $missingMsg);
                throw new RuntimeException($missingMsg);
            }

            // Safe diagnostic logging (host, port, database, user, source - NEVER password)
            error_log(sprintf(
                "[Secure360 Database] Attempting connection: host=%s, port=%d, database=%s, user=%s (source: %s)",
                $host,
                $port,
                $database,
                $username,
                $source
            ));

            $dsn = sprintf("mysql:host=%s;port=%d;dbname=%s;charset=%s", $host, $port, $database, $charset);

            try {
                self::$instance = new PDO(
                    $dsn,
                    $username,
                    $password,
                    $dbConfig['options'] ?? [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
                    ]
                );
                self::$connectedDbName = $database;
                self::$instance->exec("SET time_zone = '+05:30'");
            } catch (PDOException $e) {
                // Log full error server-side for Railway deployment diagnostics
                error_log(sprintf(
                    "[Secure360 Database][ERROR] Connection failed: host=%s, port=%d, database=%s, error=%s",
                    $host,
                    $port,
                    $database,
                    $e->getMessage()
                ));

                if ($isProduction) {
                    throw new RuntimeException("Database connection failed. Please verify Railway database service availability and credentials.");
                }

                throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }

    /**
     * Safe connectivity test for health checks
     *
     * @return array{connected: bool, database: string, error: ?string}
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT DATABASE() as db, 1 as status");
            $row = $stmt->fetch();

            return [
                'connected' => true,
                'database' => $row['db'] ?? self::$connectedDbName ?? 'secure360_v2',
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'database' => self::$connectedDbName ?? 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reset connection instance (useful for testing or reconnecting)
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$connectedDbName = null;
    }
}
