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

        // 1. Attendance Summary (Today, Active, Completed)
        $stmt = $db->prepare(
            "SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as on_duty,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN DATE(check_in_at) = CURDATE() THEN 1 ELSE 0 END) as today_checkins
             FROM attendance
             WHERE organization_id = :org_id"
        );
        $stmt->execute(['org_id' => $orgId]);
        $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_records' => 0,
            'on_duty' => 0,
            'completed' => 0,
            'today_checkins' => 0,
        ];

        // 2. Site Coverage & Guard Deployments
        $stmt = $db->prepare(
            "SELECT 
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
             WHERE s.organization_id = :org_id AND s.deleted_at IS NULL
             GROUP BY s.id, s.site_name, s.site_code, c.name
             ORDER BY s.site_name ASC"
        );
        $stmt->execute(['org_id' => $orgId]);
        $siteCoverage = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Recent Shift Attendance Details
        $stmt = $db->prepare(
            "SELECT 
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
             WHERE att.organization_id = :org_id
             ORDER BY att.check_in_at DESC
             LIMIT 15"
        );
        $stmt->execute(['org_id' => $orgId]);
        $attendanceLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('admin/reports/index', [
            'pageTitle' => 'Operational Reports & Analytics - Secure360',
            'attendanceStats' => $attendanceStats,
            'siteCoverage' => $siteCoverage,
            'attendanceLogs' => $attendanceLogs,
        ], 'layouts/admin');
    }
}
