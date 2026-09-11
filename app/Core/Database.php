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
            $dbConfig = $config['connections']['mysql'] ?? [];

            $host = $dbConfig['host'] ?? '127.0.0.1';
            $port = (int)($dbConfig['port'] ?? 3306);
            $database = $dbConfig['database'] ?? 'secure360_v2';
            $charset = $dbConfig['charset'] ?? 'utf8mb4';

            $dsn = sprintf("mysql:host=%s;port=%d;dbname=%s;charset=%s", $host, $port, $database, $charset);

            try {
                self::$instance = new PDO(
                    $dsn,
                    $dbConfig['username'] ?? 'root',
                    $dbConfig['password'] ?? '',
                    $dbConfig['options'] ?? [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
                self::$connectedDbName = $database;
            } catch (PDOException $e) {
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
