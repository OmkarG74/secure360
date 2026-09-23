<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

/**
 * Guard Attendance Management Controller
 * Connected to secure360_v2 database
 */
class AttendanceController extends Controller
{
    /**
     * Resolves human date preset into standard [fromDate, toDate] strings (YYYY-MM-DD)
     */
    public function resolveDatePreset(string $preset, ?string $rawFrom = null, ?string $rawTo = null): array
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        switch ($preset) {
            case 'today':
                return [$today, $today, 'today'];

            case 'yesterday':
                return [$yesterday, $yesterday, 'yesterday'];

            case 'this_week':
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
                return [$startOfWeek, $endOfWeek, 'this_week'];

            case 'last_month':
                $startOfLastMonth = date('Y-m-01', strtotime('first day of last month'));
                $endOfLastMonth = date('Y-m-t', strtotime('last day of last month'));
                return [$startOfLastMonth, $endOfLastMonth, 'last_month'];

            case 'custom':
                $from = !empty($rawFrom) ? $rawFrom : $today;
                $to = !empty($rawTo) ? $rawTo : $today;
                return [$from, $to, 'custom'];

            case 'this_month':
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                return [$startOfMonth, $endOfMonth, 'this_month'];

            case 'all':
            default:
                return [null, null, 'all'];
        }
    }

    /**
     * Validates and sanitizes Client -> Site -> Guard cascading filter relationships
     */
    public function validateFilterRelationships(int $orgId, ?int &$customerId, ?int &$siteId, ?int &$guardId): void
    {
        $db = Database::getConnection();

        // 1. Validate Customer
        if ($customerId !== null) {
            $stmtC = $db->prepare("SELECT id FROM customers WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL");
            $stmtC->execute(['id' => $customerId, 'org_id' => $orgId]);
            if (!$stmtC->fetchColumn()) {
                $customerId = null;
            }
        }

        // 2. Validate Site
        if ($siteId !== null) {
            $stmtS = $db->prepare("SELECT id, customer_id FROM sites WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL");
            $stmtS->execute(['id' => $siteId, 'org_id' => $orgId]);
            $siteRow = $stmtS->fetch(PDO::FETCH_ASSOC);

            if (!$siteRow) {
                $siteId = null;
            } else {
                $siteCustId = (int)$siteRow['customer_id'];
                if ($customerId !== null && $customerId !== $siteCustId) {
                    $siteId = null;
                } elseif ($customerId === null) {
                    $customerId = $siteCustId;
                }
            }
        }

        // 3. Validate Guard
        if ($guardId !== null) {
            $stmtG = $db->prepare("SELECT g.id FROM guards g JOIN users u ON g.user_id = u.id WHERE g.id = :id AND u.organization_id = :org_id AND u.deleted_at IS NULL");
            $stmtG->execute(['id' => $guardId, 'org_id' => $orgId]);
            if (!$stmtG->fetchColumn()) {
                $guardId = null;
            } else {
                if ($siteId !== null) {
                    $stmtA = $db->prepare("SELECT id FROM contract_guard_assignments WHERE guard_id = :guard_id AND site_id = :site_id AND deleted_at IS NULL");
                    $stmtA->execute(['guard_id' => $guardId, 'site_id' => $siteId]);
                    if (!$stmtA->fetchColumn()) {
                        $guardId = null;
                    }
                } elseif ($customerId !== null) {
                    $stmtA = $db->prepare("SELECT cga.id FROM contract_guard_assignments cga JOIN sites s ON cga.site_id = s.id WHERE cga.guard_id = :guard_id AND s.customer_id = :customer_id AND cga.deleted_at IS NULL");
                    $stmtA->execute(['guard_id' => $guardId, 'customer_id' => $customerId]);
                    if (!$stmtA->fetchColumn()) {
                        $guardId = null;
                    }
                }
            }
        }
    }

    /**
     * Fetch cascading filter dropdown options
     */
    public function getFilterOptions(int $orgId, ?int $clientId = null, ?int $siteId = null): array
    {
        $db = Database::getConnection();

        // 1. Clients
        $stmtClients = $db->prepare(
            "SELECT id, name, client_code FROM customers 
             WHERE organization_id = :org_id AND deleted_at IS NULL 
             ORDER BY name ASC"
        );
        $stmtClients->execute(['org_id' => $orgId]);
        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

        // 2. Sites (filtered by client if selected)
        $siteSql = "SELECT id, customer_id, site_name, site_code FROM sites 
                    WHERE organization_id = :org_id AND deleted_at IS NULL";
        $siteParams = ['org_id' => $orgId];
        if (!empty($clientId)) {
            $siteSql .= " AND customer_id = :customer_id";
            $siteParams['customer_id'] = $clientId;
        }
        $siteSql .= " ORDER BY site_name ASC";
        $stmtSites = $db->prepare($siteSql);
        $stmtSites->execute($siteParams);
        $sites = $stmtSites->fetchAll(PDO::FETCH_ASSOC);

        // 3. Guards (filtered strictly by site or client cascading relationship)
        $guardParams = ['org_id' => $orgId];
        if (!empty($siteId)) {
            $guardSql = "SELECT DISTINCT g.id as guard_id, u.full_name, u.employee_code 
                         FROM guards g 
                         JOIN users u ON g.user_id = u.id 
                         JOIN contract_guard_assignments cga ON cga.guard_id = g.id AND cga.deleted_at IS NULL 
                         WHERE u.organization_id = :org_id 
                           AND u.deleted_at IS NULL 
                           AND cga.site_id = :site_id";
            $guardParams['site_id'] = $siteId;
        } elseif (!empty($clientId)) {
            $guardSql = "SELECT DISTINCT g.id as guard_id, u.full_name, u.employee_code 
                         FROM guards g 
                         JOIN users u ON g.user_id = u.id 
                         JOIN contract_guard_assignments cga ON cga.guard_id = g.id AND cga.deleted_at IS NULL 
                         JOIN sites s ON cga.site_id = s.id AND s.deleted_at IS NULL 
                         WHERE u.organization_id = :org_id 
                           AND u.deleted_at IS NULL 
                           AND s.customer_id = :customer_id";
            $guardParams['customer_id'] = $clientId;
        } else {
            $guardSql = "SELECT DISTINCT g.id as guard_id, u.full_name, u.employee_code 
                         FROM guards g 
                         JOIN users u ON g.user_id = u.id 
                         WHERE u.organization_id = :org_id 
                           AND u.deleted_at IS NULL";
        }
        $guardSql .= " ORDER BY u.full_name ASC";
        $stmtGuards = $db->prepare($guardSql);
        $stmtGuards->execute($guardParams);
        $guards = $stmtGuards->fetchAll(PDO::FETCH_ASSOC);

        return [
            'clients' => $clients,
            'sites' => $sites,
            'guards' => $guards,
        ];
    }

    /**
     * AJAX endpoint for cascading filter dropdowns
     */
    public function ajaxFilterOptions(?Request $request = null, ?Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $customerId = $this->request->query('customer_id') ? (int)$this->request->query('customer_id') : null;
        $siteId = $this->request->query('site_id') ? (int)$this->request->query('site_id') : null;
        $dummyGuard = null;

        $this->validateFilterRelationships($orgId, $customerId, $siteId, $dummyGuard);
        $options = $this->getFilterOptions($orgId, $customerId, $siteId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $options,
            'resolved_customer_id' => $customerId,
            'resolved_site_id' => $siteId,
        ]);
        exit;
    }

    public function index(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $db = Database::getConnection();

        // 1. Extract filter parameters
        $preset = (string)$this->request->query('preset', 'all');
        $rawFrom = $this->request->query('from_date');
        $rawTo = $this->request->query('to_date');
        $customerId = $this->request->query('customer_id') ? (int)$this->request->query('customer_id') : null;
        $siteId = $this->request->query('site_id') ? (int)$this->request->query('site_id') : null;
        $guardId = $this->request->query('guard_id') ? (int)$this->request->query('guard_id') : null;
        $status = (string)$this->request->query('status', 'all');
        $search = trim((string)$this->request->query('search', ''));

        // 2. Validate and sanitize Client -> Site -> Guard relationships
        $this->validateFilterRelationships($orgId, $customerId, $siteId, $guardId);

        // 3. Resolve date range
        [$fromDate, $toDate, $resolvedPreset] = $this->resolveDatePreset($preset, $rawFrom, $rawTo);

        // 4. Build shared attendance WHERE conditions
        $where = ["att.organization_id = :org_id"];
        $params = ['org_id' => $orgId];

        if ($fromDate !== null && $toDate !== null) {
            $where[] = "att.check_in_at >= :start_dt AND att.check_in_at <= :end_dt";
            $params['start_dt'] = $fromDate . ' 00:00:00';
            $params['end_dt'] = $toDate . ' 23:59:59';
        }

        if ($guardId !== null) {
            $where[] = "att.guard_id = :guard_id";
            $params['guard_id'] = $guardId;
        }

        if ($siteId !== null) {
            $where[] = "att.site_id = :site_id";
            $params['site_id'] = $siteId;
        } elseif ($customerId !== null) {
            $where[] = "s.customer_id = :customer_id";
            $params['customer_id'] = $customerId;
        }

        if ($status !== 'all' && in_array($status, ['0', '1', '2'], true)) {
            $where[] = "att.status = :status";
            $params['status'] = (int)$status;
        }

        if ($search !== '') {
            $where[] = "(u.full_name LIKE :search OR u.employee_code LIKE :search OR s.site_name LIKE :search OR cust.name LIKE :search OR c.contract_code LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        // 5. Fetch attendance records matching the exact filter conditions
        $stmtRecords = $db->prepare(
            "SELECT att.*, 
                    DATE(att.check_in_at) as att_date,
                    u.full_name as guard_name, u.employee_code as guard_badge, u.photo_url as guard_photo,
                    s.site_name, s.site_code, s.site_address, s.latitude as site_latitude, s.longitude as site_longitude,
                    c.contract_code, cust.name as customer_name, cust.id as customer_id,
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
             WHERE {$whereSql}
             ORDER BY att.check_in_at DESC
             LIMIT 500"
        );
        $stmtRecords->execute($params);
        $records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);

        // 6. Fetch duty sites matching the current filter scope for the Map
        $siteWhere = ["s.organization_id = :org_id AND s.deleted_at IS NULL"];
        $siteParams = ['org_id' => $orgId];
        if ($siteId !== null) {
            $siteWhere[] = "s.id = :site_id";
            $siteParams['site_id'] = $siteId;
        } elseif ($customerId !== null) {
            $siteWhere[] = "s.customer_id = :customer_id";
            $siteParams['customer_id'] = $customerId;
        }
        $siteWhereSql = implode(' AND ', $siteWhere);

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
             WHERE {$siteWhereSql}
             ORDER BY s.site_name ASC"
        );
        $stmtSites->execute($siteParams);
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
        foreach ($dutySites as &$siteItem) {
            $siteItem['guards'] = array_values($siteItem['guards']);
        }
        $dutySites = array_values($dutySites);

        // 7. Compute Historical Guard Locations for each relevant guard for each selected calendar day
        // Priority 1: Telemetry in guard_live_locations linked to the duty session
        // Priority 2: Attendance checkout GPS
        // Priority 3: Attendance check-in GPS
        $guardLocations = [];

        if (!empty($records)) {
            $attIds = array_column($records, 'id');
            $attIdPlaceholders = implode(',', array_fill(0, count($attIds), '?'));

            // Fetch all telemetry rows linked to these attendance sessions
            $stmtTelemetry = $db->prepare(
                "SELECT gll.id, gll.guard_id, gll.attendance_id, gll.latitude, gll.longitude,
                        gll.accuracy_meters, gll.address, gll.recorded_at,
                        DATE(gll.recorded_at) as rec_date
                 FROM guard_live_locations gll
                 WHERE gll.attendance_id IN ({$attIdPlaceholders})
                 ORDER BY gll.recorded_at ASC"
            );
            $stmtTelemetry->execute($attIds);
            $telemetryRows = $stmtTelemetry->fetchAll(PDO::FETCH_ASSOC);

            // Group latest telemetry by [guard_id][rec_date]
            $latestTelemetryByGuardDate = [];
            foreach ($telemetryRows as $tRow) {
                $gId = (int)$tRow['guard_id'];
                $d = $tRow['rec_date'];
                $latestTelemetryByGuardDate[$gId][$d] = $tRow; // Overwrites with later timestamp because sorted ASC
            }

            // Also check telemetry submitted during the shift window if attendance_id was not explicitly set
            $guardDateTelemetry = [];
            $guardIds = array_unique(array_column($records, 'guard_id'));
            if (!empty($guardIds) && $fromDate !== null && $toDate !== null) {
                $gPlaceholders = implode(',', array_fill(0, count($guardIds), '?'));
                $stmtShiftTel = $db->prepare(
                    "SELECT gll.id, gll.guard_id, gll.attendance_id, gll.latitude, gll.longitude,
                            gll.accuracy_meters, gll.address, gll.recorded_at,
                            DATE(gll.recorded_at) as rec_date
                     FROM guard_live_locations gll
                     WHERE gll.guard_id IN ({$gPlaceholders})
                       AND gll.recorded_at >= ?
                       AND gll.recorded_at <= ?
                     ORDER BY gll.recorded_at ASC"
                );
                $shiftTelParams = array_merge($guardIds, [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
                $stmtShiftTel->execute($shiftTelParams);
                foreach ($stmtShiftTel->fetchAll(PDO::FETCH_ASSOC) as $stRow) {
                    $gId = (int)$stRow['guard_id'];
                    $d = $stRow['rec_date'];
                    $guardDateTelemetry[$gId][$d] = $stRow;
                }
            }

            // Group attendance records by [guard_id][att_date]
            $sessionsByGuardDate = [];
            foreach ($records as $att) {
                $gId = (int)$att['guard_id'];
                $d = (string)($att['att_date'] ?? substr($att['check_in_at'], 0, 10));
                $sessionsByGuardDate[$gId][$d][] = $att;
            }

            // For EACH guard and EACH calendar date, determine the LAST recorded valid location
            foreach ($sessionsByGuardDate as $gId => $dates) {
                foreach ($dates as $date => $daySessions) {
                    // Latest session of that date
                    $lastSession = $daySessions[0]; // sorted DESC in $records

                    $selectedLocation = null;

                    // Priority 1: Telemetry in guard_live_locations linked to attendance session or matching shift date
                    if (isset($latestTelemetryByGuardDate[$gId][$date])) {
                        $tel = $latestTelemetryByGuardDate[$gId][$date];
                        $selectedLocation = [
                            'source' => 'telemetry',
                            'guard_id' => $gId,
                            'guard_name' => $lastSession['guard_name'],
                            'guard_badge' => $lastSession['guard_badge'],
                            'site_name' => $lastSession['site_name'] ?? 'Unassigned Site',
                            'site_id' => $lastSession['site_id'] ? (int)$lastSession['site_id'] : null,
                            'customer_id' => $lastSession['customer_id'] ? (int)$lastSession['customer_id'] : null,
                            'customer_name' => $lastSession['customer_name'] ?? '',
                            'location_date' => $date,
                            'latitude' => (float)$tel['latitude'],
                            'longitude' => (float)$tel['longitude'],
                            'accuracy' => $tel['accuracy_meters'] ? (float)$tel['accuracy_meters'] : null,
                            'address' => $tel['address'] ?: ($lastSession['site_name'] ?? null),
                            'last_update' => $tel['recorded_at'],
                            'status' => (int)$lastSession['status'],
                            'status_label' => ((int)$lastSession['status'] === 0) ? 'On Duty' : (((int)$lastSession['status'] === 1) ? 'Completed' : 'Cancelled'),
                        ];
                    } elseif (isset($guardDateTelemetry[$gId][$date])) {
                        $tel = $guardDateTelemetry[$gId][$date];
                        $selectedLocation = [
                            'source' => 'telemetry',
                            'guard_id' => $gId,
                            'guard_name' => $lastSession['guard_name'],
                            'guard_badge' => $lastSession['guard_badge'],
                            'site_name' => $lastSession['site_name'] ?? 'Unassigned Site',
                            'site_id' => $lastSession['site_id'] ? (int)$lastSession['site_id'] : null,
                            'customer_id' => $lastSession['customer_id'] ? (int)$lastSession['customer_id'] : null,
                            'customer_name' => $lastSession['customer_name'] ?? '',
                            'location_date' => $date,
                            'latitude' => (float)$tel['latitude'],
                            'longitude' => (float)$tel['longitude'],
                            'accuracy' => $tel['accuracy_meters'] ? (float)$tel['accuracy_meters'] : null,
                            'address' => $tel['address'] ?: ($lastSession['site_name'] ?? null),
                            'last_update' => $tel['recorded_at'],
                            'status' => (int)$lastSession['status'],
                            'status_label' => ((int)$lastSession['status'] === 0) ? 'On Duty' : (((int)$lastSession['status'] === 1) ? 'Completed' : 'Cancelled'),
                        ];
                    }

                    // Priority 2: Attendance checkout GPS from the latest session of that day
                    if (!$selectedLocation) {
                        foreach ($daySessions as $s) {
                            if ($s['check_out_latitude'] !== null && $s['check_out_longitude'] !== null) {
                                $selectedLocation = [
                                    'source' => 'attendance_checkout',
                                    'guard_id' => $gId,
                                    'guard_name' => $s['guard_name'],
                                    'guard_badge' => $s['guard_badge'],
                                    'site_name' => $s['site_name'] ?? 'Unassigned Site',
                                    'site_id' => $s['site_id'] ? (int)$s['site_id'] : null,
                                    'customer_id' => $s['customer_id'] ? (int)$s['customer_id'] : null,
                                    'customer_name' => $s['customer_name'] ?? '',
                                    'location_date' => $date,
                                    'latitude' => (float)$s['check_out_latitude'],
                                    'longitude' => (float)$s['check_out_longitude'],
                                    'accuracy' => null,
                                    'address' => $s['check_out_address'] ?: ($s['site_name'] ?? null),
                                    'last_update' => $s['check_out_at'] ?: $s['check_in_at'],
                                    'status' => (int)$s['status'],
                                    'status_label' => ((int)$s['status'] === 0) ? 'On Duty' : (((int)$s['status'] === 1) ? 'Completed' : 'Cancelled'),
                                ];
                                break;
                            }
                        }
                    }

                    // Priority 3: Attendance check-in GPS from the latest session of that day
                    if (!$selectedLocation) {
                        foreach ($daySessions as $s) {
                            if ($s['check_in_latitude'] !== null && $s['check_in_longitude'] !== null) {
                                $selectedLocation = [
                                    'source' => 'attendance_checkin',
                                    'guard_id' => $gId,
                                    'guard_name' => $s['guard_name'],
                                    'guard_badge' => $s['guard_badge'],
                                    'site_name' => $s['site_name'] ?? 'Unassigned Site',
                                    'site_id' => $s['site_id'] ? (int)$s['site_id'] : null,
                                    'customer_id' => $s['customer_id'] ? (int)$s['customer_id'] : null,
                                    'customer_name' => $s['customer_name'] ?? '',
                                    'location_date' => $date,
                                    'latitude' => (float)$s['check_in_latitude'],
                                    'longitude' => (float)$s['check_in_longitude'],
                                    'accuracy' => null,
                                    'address' => $s['check_in_address'] ?: ($s['site_name'] ?? null),
                                    'last_update' => $s['check_in_at'],
                                    'status' => (int)$s['status'],
                                    'status_label' => ((int)$s['status'] === 0) ? 'On Duty' : (((int)$s['status'] === 1) ? 'Completed' : 'Cancelled'),
                                ];
                                break;
                            }
                        }
                    }

                    if ($selectedLocation) {
                        $guardLocations[] = $selectedLocation;
                    }
                }
            }
        }

        // 8. Fetch cascading filter options for view
        $filterOptions = $this->getFilterOptions($orgId, $customerId, $siteId);

        $this->render('admin/attendance/index', [
            'pageTitle' => 'Guard Attendance & Duty Telemetry',
            'organisationId' => $orgId,
            'records' => $records,
            'dutySites' => $dutySites,
            'guardLocations' => $guardLocations,
            'filterOptions' => $filterOptions,
            'preset' => $resolvedPreset,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'customerId' => $customerId,
            'siteId' => $siteId,
            'guardId' => $guardId,
            'status' => $status,
            'search' => $search,
        ], 'layouts/admin');
    }

    /**
     * Display detailed attendance record with manual selfie inspection
     */
    public function show(?Request $request = null, ?Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $id = (int)($params['id'] ?? 0);

        if ($id <= 0) {
            $this->setFlash('error', 'Invalid attendance record identifier.');
            $this->redirect('/admin/attendance');
            return;
        }

        $db = Database::getConnection();

        // 1. Fetch complete attendance record with joined guard, user, site, customer, contract, shift, assignment
        $stmt = $db->prepare(
            "SELECT att.*, 
                    u.full_name as guard_name, u.employee_code as guard_badge, u.photo_url as guard_photo,
                    u.phone as guard_phone, u.email as guard_email,
                    g.status as guard_status,
                    s.site_name, s.site_code, s.site_address, s.zone_gate,
                    s.latitude as site_latitude, s.longitude as site_longitude,
                    cust.name as customer_name, cust.client_code,
                    c.contract_code, c.extra_notes as contract_notes,
                    cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end,
                    cga.status as assignment_status, cga.notes as assignment_notes
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
             WHERE att.id = :id AND att.organization_id = :org_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $this->setFlash('error', 'Attendance record not found or access denied.');
            $this->redirect('/admin/attendance');
            return;
        }

        // 2. Fetch Check-In Selfie
        $checkinSelfie = null;
        if (!empty($record['selfie_id'])) {
            $sStmt = $db->prepare("SELECT * FROM selfies WHERE id = :id AND organization_id = :org_id LIMIT 1");
            $sStmt->execute(['id' => (int)$record['selfie_id'], 'org_id' => $orgId]);
            $checkinSelfie = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$checkinSelfie) {
            $sStmt = $db->prepare(
                "SELECT * FROM selfies 
                 WHERE attendance_id = :att_id AND organization_id = :org_id AND verification_status = 'checkin'
                 ORDER BY id ASC LIMIT 1"
            );
            $sStmt->execute(['att_id' => $id, 'org_id' => $orgId]);
            $checkinSelfie = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$checkinSelfie) {
            $sStmt = $db->prepare(
                "SELECT * FROM selfies 
                 WHERE attendance_id = :att_id AND organization_id = :org_id
                 ORDER BY id ASC LIMIT 1"
            );
            $sStmt->execute(['att_id' => $id, 'org_id' => $orgId]);
            $checkinSelfie = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 3. Fetch Check-Out Selfie
        $checkoutSelfie = null;
        $coStmt = $db->prepare(
            "SELECT * FROM selfies 
             WHERE attendance_id = :att_id AND organization_id = :org_id AND verification_status = 'checkout'
             ORDER BY id DESC LIMIT 1"
        );
        $coStmt->execute(['att_id' => $id, 'org_id' => $orgId]);
        $checkoutSelfie = $coStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$checkoutSelfie && $checkinSelfie) {
            $coStmt2 = $db->prepare(
                "SELECT * FROM selfies 
                 WHERE attendance_id = :att_id AND organization_id = :org_id AND id != :ci_id
                 ORDER BY id DESC LIMIT 1"
            );
            $coStmt2->execute(['att_id' => $id, 'org_id' => $orgId, 'ci_id' => $checkinSelfie['id']]);
            $checkoutSelfie = $coStmt2->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 4. Calculate Distances and Geofence Verification Status
        $siteLat = $record['site_latitude'] !== null ? (float)$record['site_latitude'] : null;
        $siteLng = $record['site_longitude'] !== null ? (float)$record['site_longitude'] : null;

        $checkinDistanceMeters = null;
        $checkinLocationVerified = null;
        if ($siteLat !== null && $siteLng !== null && $record['check_in_latitude'] !== null && $record['check_in_longitude'] !== null) {
            $checkinDistanceMeters = round(geo_distance_meters((float)$record['check_in_latitude'], (float)$record['check_in_longitude'], $siteLat, $siteLng), 1);
            $checkinLocationVerified = $checkinDistanceMeters <= 200.0;
        }

        $checkoutDistanceMeters = null;
        $checkoutLocationVerified = null;
        if ($siteLat !== null && $siteLng !== null && $record['check_out_latitude'] !== null && $record['check_out_longitude'] !== null) {
            $checkoutDistanceMeters = round(geo_distance_meters((float)$record['check_out_latitude'], (float)$record['check_out_longitude'], $siteLat, $siteLng), 1);
            $checkoutLocationVerified = $checkoutDistanceMeters <= 200.0;
        }

        // 5. Calculate Duty Duration
        $durationFormatted = 'Not Available';
        $isDurationActive = false;
        if (!empty($record['check_in_at'])) {
            $checkInTs = strtotime($record['check_in_at']);
            if (!empty($record['check_out_at'])) {
                $checkOutTs = strtotime($record['check_out_at']);
                $diff = max(0, $checkOutTs - $checkInTs);
                $hrs = floor($diff / 3600);
                $mins = floor(($diff % 3600) / 60);
                $durationFormatted = "{$hrs}h {$mins}m";
            } elseif ((int)$record['status'] === 0) {
                $diff = max(0, time() - $checkInTs);
                $hrs = floor($diff / 3600);
                $mins = floor(($diff % 3600) / 60);
                $durationFormatted = "{$hrs}h {$mins}m (Active)";
                $isDurationActive = true;
            }
        }

        $this->render('admin/attendance/show', [
            'pageTitle' => 'Attendance Details #' . $record['id'],
            'organisationId' => $orgId,
            'record' => $record,
            'checkinSelfie' => $checkinSelfie,
            'checkoutSelfie' => $checkoutSelfie,
            'checkinDistanceMeters' => $checkinDistanceMeters,
            'checkinLocationVerified' => $checkinLocationVerified,
            'checkoutDistanceMeters' => $checkoutDistanceMeters,
            'checkoutLocationVerified' => $checkoutLocationVerified,
            'durationFormatted' => $durationFormatted,
            'isDurationActive' => $isDurationActive,
        ], 'layouts/admin');
    }
}

