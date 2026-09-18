<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use PDO;

/**
 * Guard Attendance Management Controller
 * Connected to secure360_v2 database
 */
class AttendanceController extends Controller
{
    public function index(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $db = Database::getConnection();

        // 1. Fetch complete attendance records with joined relations (ONLY_FULL_GROUP_BY compatible)
        $stmtRecords = $db->prepare(
            "SELECT att.*, 
                    u.full_name as guard_name, u.employee_code as guard_badge, u.photo_url as guard_photo,
                    s.site_name, s.site_code, s.site_address, s.latitude as site_latitude, s.longitude as site_longitude,
                    c.contract_code, cust.name as customer_name,
                    cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end
             FROM attendance att
             JOIN guards g ON att.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             LEFT JOIN sites s ON att.site_id = s.id
             LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
             LEFT JOIN contracts c ON c.id = COALESCE(
                 cga.contract_id,
                 (SELECT c2.id FROM contracts c2 WHERE c2.site_id = att.site_id AND c2.status = 0 AND c2.deleted_at IS NULL LIMIT 1)
             )
             LEFT JOIN contract_shifts cs ON cs.id = cga.contract_shift_id
             LEFT JOIN customers cust ON cust.id = COALESCE(s.customer_id, c.customer_id)
             WHERE att.organization_id = :org_id
             ORDER BY att.check_in_at DESC
             LIMIT 500"
        );
        $stmtRecords->execute(['org_id' => $orgId]);
        $records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch duty sites and assigned guards for the Map
        $stmtSites = $db->prepare(
            "SELECT s.id as site_id, s.site_name, s.site_code, s.site_address, s.latitude, s.longitude,
                    cust.name as customer_name, cust.client_code,
                    c.contract_code,
                    cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end,
                    u.full_name as guard_name, u.employee_code as guard_badge, g.id as guard_id
             FROM sites s
             JOIN customers cust ON s.customer_id = cust.id
             LEFT JOIN contracts c ON c.site_id = s.id AND c.status = 0 AND c.deleted_at IS NULL
             LEFT JOIN contract_guard_assignments cga ON cga.site_id = s.id AND cga.status = 0 AND cga.deleted_at IS NULL
             LEFT JOIN guards g ON cga.guard_id = g.id AND g.deleted_at IS NULL
             LEFT JOIN users u ON g.user_id = u.id AND u.deleted_at IS NULL
             LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id AND cs.deleted_at IS NULL
             WHERE s.organization_id = :org_id AND s.deleted_at IS NULL
             ORDER BY s.site_name ASC"
        );
        $stmtSites->execute(['org_id' => $orgId]);
        $siteRows = $stmtSites->fetchAll(PDO::FETCH_ASSOC);

        $dutySites = [];
        foreach ($siteRows as $row) {
            $sId = (int)$row['site_id'];
            if (!isset($dutySites[$sId])) {
                $dutySites[$sId] = [
                    'id' => $sId,
                    'name' => $row['site_name'],
                    'code' => $row['site_code'],
                    'address' => $row['site_address'],
                    'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
                    'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
                    'customer_name' => $row['customer_name'],
                    'contract_code' => $row['contract_code'],
                    'shift_name' => $row['shift_name'],
                    'shift_start' => $row['shift_start'],
                    'shift_end' => $row['shift_end'],
                    'guards' => [],
                ];
            }
            if (!empty($row['guard_name'])) {
                $guardKey = (int)$row['guard_id'];
                if (!isset($dutySites[$sId]['guards'][$guardKey])) {
                    $dutySites[$sId]['guards'][$guardKey] = [
                        'id' => $guardKey,
                        'name' => $row['guard_name'],
                        'badge' => $row['guard_badge'],
                    ];
                }
            }
        }
        foreach ($dutySites as &$site) {
            $site['guards'] = array_values($site['guards']);
        }
        $dutySites = array_values($dutySites);

        // 3. Fetch guard latest GPS/live location telemetry for the Map
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

        $this->render('admin/attendance/index', [
            'pageTitle' => 'Guard Attendance & Duty Telemetry',
            'organisationId' => $orgId,
            'records' => $records,
            'dutySites' => $dutySites,
            'guardLocations' => $guardLocations,
        ], 'layouts/admin');
    }
}

