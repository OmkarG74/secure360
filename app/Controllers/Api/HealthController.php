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

    /**
     * GET /api/v1/timezone-diagnostic
     * Diagnostic endpoint reporting current PHP, MySQL, UTC, IST times and latest attendance timestamp
     */
    public function timezoneDiagnostic(): void
    {
        $db = Database::getConnection();
        $tzRow = $db->query("SELECT @@global.time_zone AS global_tz, @@session.time_zone AS session_tz, NOW() AS mysql_now, UTC_TIMESTAMP() AS mysql_utc, CURTIME() as mysql_curtime")->fetch();

        $utcNow = new \DateTime('now', new \DateTimeZone('UTC'));
        $istNow = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));

        $latestAtt = $db->query("SELECT id, guard_id, site_id, check_in_at, check_out_at, created_at, status FROM attendance ORDER BY id DESC LIMIT 1")->fetch();

        $attFormatted = null;
        if ($latestAtt) {
            $attFormatted = [
                'id' => (int)$latestAtt['id'],
                'raw_check_in_at' => $latestAtt['check_in_at'],
                'raw_check_out_at' => $latestAtt['check_out_at'],
                'raw_created_at' => $latestAtt['created_at'],
                'format_datetime_check_in' => format_datetime($latestAtt['check_in_at']),
                'format_datetime_check_out' => format_datetime($latestAtt['check_out_at']),
                'format_time_check_in' => format_time($latestAtt['check_in_at']),
            ];
        }

        $this->json([
            'success' => true,
            'message' => 'Timezone diagnostic report',
            'data' => [
                'php' => [
                    'date_default_timezone' => date_default_timezone_get(),
                    'ini_date_timezone' => ini_get('date.timezone') ?: '(unset)',
                    'current_time' => date('Y-m-d H:i:s'),
                    'app_config_timezone' => config('app.timezone'),
                ],
                'mysql' => [
                    'session_time_zone' => $tzRow['session_tz'] ?? null,
                    'global_time_zone' => $tzRow['global_tz'] ?? null,
                    'current_time_now' => $tzRow['mysql_now'] ?? null,
                    'curtime' => $tzRow['mysql_curtime'] ?? null,
                    'utc_timestamp' => $tzRow['mysql_utc'] ?? null,
                ],
                'reference_times' => [
                    'utc_time' => $utcNow->format('Y-m-d H:i:s'),
                    'asia_kolkata_time' => $istNow->format('Y-m-d H:i:s'),
                ],
                'latest_attendance' => $attFormatted,
            ],
            'status_code' => 200,
        ], 200);
    }
}

