<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use PDO;

/**
 * Organisation Admin Reports Controller
 * Aggregates operational attendance, site coverage, and guard telemetry from secure360_v2
 */
class ReportController extends Controller
{
    public function index(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $db = Database::getConnection();

        $preset = $this->request->query('preset', 'today');
        $rawFrom = $this->request->query('from_date', '');
        $rawTo = $this->request->query('to_date', '');
        $searchQuery = trim((string)$this->request->query('search', ''));

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        switch ($preset) {
            case 'yesterday':
                $fromDate = $yesterday;
                $toDate = $yesterday;
                break;
            case 'specific':
                $fromDate = !empty($rawFrom) ? $rawFrom : $today;
                $toDate = $fromDate;
                break;
            case 'custom':
                $fromDate = !empty($rawFrom) ? $rawFrom : date('Y-m-d', strtotime('-7 days'));
                $toDate = !empty($rawTo) ? $rawTo : $today;
                if ($fromDate > $toDate) {
                    $temp = $fromDate;
                    $fromDate = $toDate;
                    $toDate = $temp;
                }
                break;
            case 'all':
                $fromDate = null;
                $toDate = null;
                break;
            case 'today':
            default:
                $preset = 'today';
                $fromDate = $today;
                $toDate = $today;
                break;
        }

        // 1. Attendance Summary Metrics
        $statsSql = "SELECT 
                        COUNT(*) as total_records,
                        SUM(CASE WHEN att.status = 0 THEN 1 ELSE 0 END) as on_duty,
                        SUM(CASE WHEN att.status = 1 THEN 1 ELSE 0 END) as completed,
                        COUNT(DISTINCT att.site_id) as active_sites,
                        COUNT(DISTINCT att.guard_id) as active_guards
                     FROM attendance att
                     JOIN guards g ON att.guard_id = g.id
                     JOIN users u ON g.user_id = u.id
                     LEFT JOIN sites s ON att.site_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     WHERE att.organization_id = :org_id";
        
        $statsParams = ['org_id' => $orgId];

        if ($fromDate !== null && $toDate !== null) {
            $statsSql .= " AND DATE(att.check_in_at) BETWEEN :from_date AND :to_date";
            $statsParams['from_date'] = $fromDate;
            $statsParams['to_date'] = $toDate;
        }

        if ($searchQuery !== '') {
            $statsSql .= " AND (u.full_name LIKE :search_stats OR u.employee_code LIKE :search_stats OR s.site_name LIKE :search_stats OR c.name LIKE :search_stats)";
            $statsParams['search_stats'] = '%' . $searchQuery . '%';
        }

        $stmt = $db->prepare($statsSql);
        $stmt->execute($statsParams);
        $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_records' => 0,
            'on_duty' => 0,
            'completed' => 0,
            'active_sites' => 0,
            'active_guards' => 0,
        ];

        // 2. Site Coverage & Guard Deployments
        $covSql = "SELECT 
                    s.id as site_id,
                    s.site_name,
                    s.site_code,
                    c.name as customer_name,
                    COUNT(DISTINCT a.guard_id) as assigned_guards,
                    COUNT(DISTINCT cs.id) as active_shifts
                  FROM sites s
                  JOIN customers c ON s.customer_id = c.id
                  LEFT JOIN contract_guard_assignments a ON s.id = a.site_id AND a.status = 0
                  LEFT JOIN contract_shifts cs ON a.contract_shift_id = cs.id
                  WHERE s.organization_id = :org_id AND s.deleted_at IS NULL";
        
        $covParams = ['org_id' => $orgId];

        if ($searchQuery !== '') {
            $covSql .= " AND (s.site_name LIKE :cov_search OR s.site_code LIKE :cov_search OR c.name LIKE :cov_search)";
            $covParams['cov_search'] = '%' . $searchQuery . '%';
        }

        $covSql .= " GROUP BY s.id, s.site_name, s.site_code, c.name ORDER BY s.site_name ASC";

        $stmt = $db->prepare($covSql);
        $stmt->execute($covParams);
        $siteCoverage = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Attendance Logs for Selected Date Range and Search
        $logsSql = "SELECT 
                        att.*,
                        u.full_name as guard_name,
                        u.employee_code,
                        s.site_name,
                        c.name as customer_name
                    FROM attendance att
                    JOIN guards g ON att.guard_id = g.id
                    JOIN users u ON g.user_id = u.id
                    LEFT JOIN sites s ON att.site_id = s.id
                    LEFT JOIN customers c ON s.customer_id = c.id
                    WHERE att.organization_id = :org_id";
        
        $logsParams = ['org_id' => $orgId];

        if ($fromDate !== null && $toDate !== null) {
            $logsSql .= " AND DATE(att.check_in_at) BETWEEN :from_date AND :to_date";
            $logsParams['from_date'] = $fromDate;
            $logsParams['to_date'] = $toDate;
        }

        if ($searchQuery !== '') {
            $logsSql .= " AND (u.full_name LIKE :search_logs OR u.employee_code LIKE :search_logs OR s.site_name LIKE :search_logs OR c.name LIKE :search_logs)";
            $logsParams['search_logs'] = '%' . $searchQuery . '%';
        }

        $logsSql .= " ORDER BY att.check_in_at DESC LIMIT 100";

        $stmt = $db->prepare($logsSql);
        $stmt->execute($logsParams);
        $attendanceLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('admin/reports/index', [
            'pageTitle' => 'Operational Reports & Analytics - Secure360',
            'preset' => $preset,
            'fromDate' => $fromDate ?? '',
            'toDate' => $toDate ?? '',
            'searchQuery' => $searchQuery,
            'attendanceStats' => $attendanceStats,
            'siteCoverage' => $siteCoverage,
            'attendanceLogs' => $attendanceLogs,
        ], 'layouts/admin');
    }
}
