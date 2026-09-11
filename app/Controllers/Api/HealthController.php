<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;

/**
 * API Health Check Controller
 * Verifies that PHP backend, routing engine, and MySQL database connection are operational
 */
class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     */
    public function check(): void
    {
        $dbStatus = Database::testConnection();

        if ($dbStatus['connected']) {
            $this->json([
                'success' => true,
                'message' => 'Secure360 API is running',
                'database' => 'connected',
                'database_name' => $dbStatus['database'],
                'php_version' => PHP_VERSION,
                'timestamp' => time(),
            ], 200);
        } else {
            $this->json([
                'success' => false,
                'message' => 'Secure360 API is running but database is disconnected',
                'database' => 'disconnected',
                'error' => $dbStatus['error'],
                'timestamp' => time(),
            ], 503);
        }
    }
}
