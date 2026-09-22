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

        // 2. Total & Active Sites
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

        // 4. Assigned Guards (Guards with active post assignments)
        $stmt = $db->prepare(
            "SELECT COUNT(DISTINCT a.guard_id) 
             FROM contract_guard_assignments a
             JOIN sites s ON a.site_id = s.id
             WHERE s.organization_id = :org_id AND a.status = 0 AND a.deleted_at IS NULL"
        );
        $stmt->execute(['org_id' => $orgId]);
        $assignedGuards = (int)$stmt->fetchColumn();

        // 5. Guards Currently On Duty (status = 0 and no checkout)
        $stmt = $db->prepare(
            "SELECT COUNT(DISTINCT guard_id) 
             FROM attendance 
             WHERE organization_id = :org_id AND status = 0 AND check_out_at IS NULL"
        );
        $stmt->execute(['org_id' => $orgId]);
        $onDutyGuards = (int)$stmt->fetchColumn();

        // 6. Active Contracts
        $stmt = $db->prepare("SELECT COUNT(*) FROM contracts WHERE organization_id = :org_id AND status = 0 AND deleted_at IS NULL");
        $stmt->execute(['org_id' => $orgId]);
        $activeContracts = (int)$stmt->fetchColumn();

        // 7. Today's Attendance Breakdown
        $stmt = $db->prepare(
            "SELECT 
                COUNT(*) as today_total,
                SUM(CASE WHEN status = 0 AND check_out_at IS NULL THEN 1 ELSE 0 END) as today_on_duty,
                SUM(CASE WHEN status = 1 OR check_out_at IS NOT NULL THEN 1 ELSE 0 END) as today_completed
             FROM attendance 
             WHERE organization_id = :org_id AND DATE(check_in_at) = CURDATE()"
        );
        $stmt->execute(['org_id' => $orgId]);
        $todayAttStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['today_total' => 0, 'today_on_duty' => 0, 'today_completed' => 0];

        $todayTotal = (int)($todayAttStats['today_total'] ?? 0);
        $todayCompleted = (int)($todayAttStats['today_completed'] ?? 0);

        // 8. Duty Sites with Coordinates for Map
        $stmtSites = $db->prepare(
            "SELECT s.id as site_id, s.site_name, s.site_code, s.site_address as address, s.latitude, s.longitude,
                    c.name as customer_name,
                    COUNT(DISTINCT a.guard_id) as guard_count
             FROM sites s
             JOIN customers c ON s.customer_id = c.id
             LEFT JOIN contract_guard_assignments a ON s.id = a.site_id AND a.status = 0 AND a.deleted_at IS NULL
             WHERE s.organization_id = :org_id 
               AND s.deleted_at IS NULL
               AND s.latitude IS NOT NULL 
               AND s.longitude IS NOT NULL
             GROUP BY s.id, s.site_name, s.site_code, s.site_address, s.latitude, s.longitude, c.name
             ORDER BY s.site_name ASC"
        );
        $stmtSites->execute(['org_id' => $orgId]);
        $dutySites = $stmtSites->fetchAll(PDO::FETCH_ASSOC);

        // 9. Guard Live Locations for Map
        $stmtLive = $db->prepare(
            "SELECT gll.id, gll.guard_id, gll.latitude, gll.longitude, gll.accuracy_meters, gll.address, gll.recorded_at,
                    u.full_name as guard_name, u.employee_code as guard_badge,
                    s.site_name, s.site_code,
                    att.status as attendance_status, att.check_in_at, att.check_out_at
             FROM (
                 SELECT MAX(id) as max_id
                 FROM guard_live_locations
                 WHERE organization_id = :org_id
                 GROUP BY guard_id
             ) latest
             JOIN guard_live_locations gll ON gll.id = latest.max_id
             JOIN guards g ON gll.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             LEFT JOIN attendance att ON gll.attendance_id = att.id
             LEFT JOIN sites s ON att.site_id = s.id"
        );
        $stmtLive->execute(['org_id' => $orgId]);
        $liveRows = $stmtLive->fetchAll(PDO::FETCH_ASSOC);

        $stmtAttLoc = $db->prepare(
            "SELECT att.id as attendance_id, att.guard_id, 
                    att.check_in_latitude as latitude, att.check_in_longitude as longitude, 
                    att.check_in_address as address, att.check_in_at as recorded_at,
                    att.status as attendance_status, att.check_in_at, att.check_out_at,
                    u.full_name as guard_name, u.employee_code as guard_badge,
                    s.site_name, s.site_code
             FROM (
                 SELECT MAX(id) as max_id
                 FROM attendance
                 WHERE organization_id = :org_id
                   AND check_in_latitude IS NOT NULL 
                   AND check_in_longitude IS NOT NULL
                 GROUP BY guard_id
             ) latest
             JOIN attendance att ON att.id = latest.max_id
             JOIN guards g ON att.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             LEFT JOIN sites s ON att.site_id = s.id"
        );
        $stmtAttLoc->execute(['org_id' => $orgId]);
        $attLocRows = $stmtAttLoc->fetchAll(PDO::FETCH_ASSOC);

        $guardLocations = [];
        $recordedGuardIds = [];

        foreach ($liveRows as $row) {
            $gId = (int)$row['guard_id'];
            $guardLocations[] = [
                'guard_id' => $gId,
                'guard_name' => $row['guard_name'],
                'guard_badge' => $row['guard_badge'],
                'site_name' => $row['site_name'] ?? 'Unassigned Site',
                'status' => (int)($row['attendance_status'] ?? 0),
                'status_label' => ((int)($row['attendance_status'] ?? 0) === 0) ? 'On Duty' : (((int)($row['attendance_status'] ?? 0) === 1) ? 'Completed' : 'Cancelled'),
                'latitude' => (float)$row['latitude'],
                'longitude' => (float)$row['longitude'],
                'accuracy' => $row['accuracy_meters'] ? (float)$row['accuracy_meters'] : null,
                'address' => $row['address'] ?? null,
                'last_update' => $row['recorded_at'],
            ];
            $recordedGuardIds[$gId] = true;
        }

        foreach ($attLocRows as $row) {
            $gId = (int)$row['guard_id'];
            if (!isset($recordedGuardIds[$gId])) {
                $guardLocations[] = [
                    'guard_id' => $gId,
                    'guard_name' => $row['guard_name'],
                    'guard_badge' => $row['guard_badge'],
                    'site_name' => $row['site_name'] ?? 'Unassigned Site',
                    'status' => (int)$row['attendance_status'],
                    'status_label' => ((int)$row['attendance_status'] === 0) ? 'On Duty' : (((int)$row['attendance_status'] === 1) ? 'Completed' : 'Cancelled'),
                    'latitude' => (float)$row['latitude'],
                    'longitude' => (float)$row['longitude'],
                    'accuracy' => null,
                    'address' => $row['address'] ?? null,
                    'last_update' => $row['recorded_at'],
                ];
                $recordedGuardIds[$gId] = true;
            }
        }

        // 10. Recent Attendance Records
        $stmt = $db->prepare(
            "SELECT att.*, u.full_name as guard_name, u.employee_code, s.site_name, c.name as customer_name
             FROM attendance att
             JOIN guards g ON att.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             LEFT JOIN sites s ON att.site_id = s.id
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE att.organization_id = :org_id
             ORDER BY att.check_in_at DESC
             LIMIT 6"
        );
        $stmt->execute(['org_id' => $orgId]);
        $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 11. Recent Activity Audit Trail
        $stmt = $db->prepare(
            "SELECT a.*, u.full_name as user_name
             FROM activities a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.organization_id = :org_id
             ORDER BY a.created_at DESC
             LIMIT 6"
        );
        $stmt->execute(['org_id' => $orgId]);
        $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $offDutyGuards = max(0, $totalGuards - $onDutyGuards);

        $subService = new \App\Services\SubscriptionService();
        $subDetails = $subService->getSubscriptionDetails($orgId);

        $this->render('admin/dashboard/index', [
            'pageTitle' => 'Admin Operations Dashboard - Secure360',
            'user' => Auth::user(),
            'metrics' => [
                'totalCustomers' => $totalCustomers,
                'totalSites' => $totalSites,
                'totalGuards' => $totalGuards,
                'assignedGuards' => $assignedGuards,
                'onDutyGuards' => $onDutyGuards,
                'offDutyGuards' => $offDutyGuards,
                'activeContracts' => $activeContracts,
                'todayTotal' => $todayTotal,
                'todayCompleted' => $todayCompleted,
            ],
            'dutySites' => $dutySites,
            'guardLocations' => $guardLocations,
            'recentAttendance' => $recentAttendance,
            'recentActivities' => $recentActivities,
            'subDetails' => $subDetails,
        ], 'layouts/admin');
    }
}
