<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use PDO;

/**
 * Admin Dashboard Controller
 * Connects directly to secure360_v2 database for real operational metrics
 */
class AdminDashboardController extends Controller
{
    public function index(): void
    {
        $orgId = Auth::organisationId() ?? 1; // Default to tenant 1 for local development
        $db = Database::getConnection();

        // 1. Total Customers / Clients
        $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE organization_id = :org_id AND deleted_at IS NULL");
        $stmt->execute(['org_id' => $orgId]);
        $totalCustomers = (int)$stmt->fetchColumn();

        // 2. Active Sites
        $stmt = $db->prepare("SELECT COUNT(*) FROM sites WHERE organization_id = :org_id AND deleted_at IS NULL");
        $stmt->execute(['org_id' => $orgId]);
        $totalSites = (int)$stmt->fetchColumn();

        // 3. Registered Guards
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM guards g
             JOIN users u ON g.user_id = u.id
             WHERE u.organization_id = :org_id AND g.deleted_at IS NULL AND u.deleted_at IS NULL"
        );
        $stmt->execute(['org_id' => $orgId]);
        $totalGuards = (int)$stmt->fetchColumn();

        // 4. Active Contracts
        $stmt = $db->prepare("SELECT COUNT(*) FROM contracts WHERE organization_id = :org_id AND status = 0 AND deleted_at IS NULL");
        $stmt->execute(['org_id' => $orgId]);
        $activeContracts = (int)$stmt->fetchColumn();

        // 5. Open / Today's Attendance
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM attendance 
             WHERE organization_id = :org_id AND DATE(check_in_at) = CURDATE() AND status = 0"
        );
        $stmt->execute(['org_id' => $orgId]);
        $activeCheckIns = (int)$stmt->fetchColumn();

        // 6. Recent Attendance / Duty Feed
        $stmt = $db->prepare(
            "SELECT att.*, u.full_name as guard_name, u.employee_code, s.site_name
             FROM attendance att
             JOIN guards g ON att.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             LEFT JOIN sites s ON att.site_id = s.id
             WHERE att.organization_id = :org_id
             ORDER BY att.check_in_at DESC
             LIMIT 6"
        );
        $stmt->execute(['org_id' => $orgId]);
        $recentAttendance = $stmt->fetchAll();

        // 7. Recent Activity Audit Trail
        $stmt = $db->prepare(
            "SELECT a.*, u.full_name as user_name
             FROM activities a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.organization_id = :org_id
             ORDER BY a.created_at DESC
             LIMIT 8"
        );
        $stmt->execute(['org_id' => $orgId]);
        $recentActivities = $stmt->fetchAll();

        $this->render('admin/dashboard/index', [
            'pageTitle' => 'Admin Operations Dashboard - Secure360',
            'user' => Auth::user(),
            'metrics' => [
                'totalCustomers' => $totalCustomers,
                'totalSites' => $totalSites,
                'totalGuards' => $totalGuards,
                'activeContracts' => $activeContracts,
                'activeCheckIns' => $activeCheckIns,
            ],
            'recentAttendance' => $recentAttendance,
            'recentActivities' => $recentActivities,
        ], 'layouts/admin');
    }
}
