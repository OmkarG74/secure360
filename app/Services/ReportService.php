<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Secure360 Operational Report Service
 * Powers the unified Operational Reports & Analytics module.
 * Provides data querying, server-side pagination, summary metrics calculation,
 * and cascading filter synchronization for all 6 report types.
 */
class ReportService
{
    private PDO $db;

    public const REPORT_ALL_OPERATIONS = 'all_operations';
    public const REPORT_ATTENDANCE = 'attendance';
    public const REPORT_GUARDS = 'guards';
    public const REPORT_SITES_CLIENTS = 'sites_clients';
    public const REPORT_SHIFTS = 'shifts';
    public const REPORT_CONTRACTS = 'contracts';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

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
                // Monday of current week to today / Sunday
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
                return [$startOfWeek, $endOfWeek, 'this_week'];

            case 'last_month':
                $startOfLastMonth = date('Y-m-01', strtotime('first day of last month'));
                $endOfLastMonth = date('Y-m-t', strtotime('last day of last month'));
                return [$startOfLastMonth, $endOfLastMonth, 'last_month'];

            case 'custom':
                $from = !empty($rawFrom) ? $rawFrom : date('Y-m-01');
                $to = !empty($rawTo) ? $rawTo : $today;
                return [$from, $to, 'custom'];

            case 'all':
                return [null, null, 'all'];

            case 'this_month':
            default:
                // Default per reference design: First of current month to today
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                return [$startOfMonth, $endOfMonth, 'this_month'];
        }
    }

    /**
     * Get paginated report records and calculated summary cards
     */
    public function getReportData(int $orgId, array $filters, int $page = 1, int $pageSize = 25): array
    {
        $reportType = $filters['report_type'] ?? self::REPORT_ALL_OPERATIONS;
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $offset = ($page - 1) * $pageSize;

        switch ($reportType) {
            case self::REPORT_ATTENDANCE:
                return $this->queryAttendanceReport($orgId, $filters, $pageSize, $offset);

            case self::REPORT_GUARDS:
                return $this->queryGuardsReport($orgId, $filters, $pageSize, $offset);

            case self::REPORT_SITES_CLIENTS:
                return $this->querySitesClientsReport($orgId, $filters, $pageSize, $offset);

            case self::REPORT_SHIFTS:
                return $this->queryShiftsReport($orgId, $filters, $pageSize, $offset);

            case self::REPORT_CONTRACTS:
                return $this->queryContractsReport($orgId, $filters, $pageSize, $offset);

            case self::REPORT_ALL_OPERATIONS:
            default:
                return $this->queryAllOperationsReport($orgId, $filters, $pageSize, $offset);
        }
    }

    /**
     * Return zeroed-out summary cards for empty or invalid filter states
     */
    public function getEmptySummary(string $reportType): array
    {
        switch ($reportType) {
            case self::REPORT_ATTENDANCE:
                return [
                    'card1' => ['label' => 'Total Records', 'value' => 0, 'sub' => 'Attendance entries'],
                    'card2' => ['label' => 'On Duty', 'value' => 0, 'sub' => 'Active sessions'],
                    'card3' => ['label' => 'Completed', 'value' => 0, 'sub' => 'Checked out'],
                    'card4' => ['label' => 'Cancelled', 'value' => 0, 'sub' => 'Voided sessions'],
                ];
            case self::REPORT_GUARDS:
                return [
                    'card1' => ['label' => 'Total Guards', 'value' => 0, 'sub' => 'Registered personnel'],
                    'card2' => ['label' => 'Active', 'value' => 0, 'sub' => 'Active status'],
                    'card3' => ['label' => 'Inactive', 'value' => 0, 'sub' => 'Inactive status'],
                    'card4' => ['label' => 'Assigned', 'value' => 0, 'sub' => 'Rostered to posts'],
                ];
            case self::REPORT_SITES_CLIENTS:
                return [
                    'card1' => ['label' => 'Total Sites', 'value' => 0, 'sub' => 'Duty posts registered'],
                    'card2' => ['label' => 'Active', 'value' => 0, 'sub' => 'Active sites'],
                    'card3' => ['label' => 'Inactive', 'value' => 0, 'sub' => 'Inactive sites'],
                    'card4' => ['label' => 'Guards Assigned', 'value' => 0, 'sub' => 'Deployed security staff'],
                ];
            case self::REPORT_SHIFTS:
                return [
                    'card1' => ['label' => 'Total Records', 'value' => 0, 'sub' => 'Shift duty logs'],
                    'card2' => ['label' => 'On Duty', 'value' => 0, 'sub' => 'Active sessions'],
                    'card3' => ['label' => 'Completed', 'value' => 0, 'sub' => 'Completed shifts'],
                    'card4' => ['label' => 'Cancelled', 'value' => 0, 'sub' => 'Voided sessions'],
                ];
            case self::REPORT_CONTRACTS:
                return [
                    'card1' => ['label' => 'Total Contracts', 'value' => 0, 'sub' => 'Agreements registered'],
                    'card2' => ['label' => 'Active', 'value' => 0, 'sub' => 'Currently in force'],
                    'card3' => ['label' => 'Expired', 'value' => 0, 'sub' => 'Ended or terminated'],
                    'card4' => ['label' => 'Expiring Soon', 'value' => 0, 'sub' => 'Within next 30 days'],
                ];
            case self::REPORT_ALL_OPERATIONS:
            default:
                return [
                    'card1' => ['label' => 'Total Records', 'value' => 0, 'sub' => 'In selected period'],
                    'card2' => ['label' => 'On Duty', 'value' => 0, 'sub' => 'Active sessions'],
                    'card3' => ['label' => 'Completed', 'value' => 0, 'sub' => 'Successfully checked out'],
                    'card4' => ['label' => 'Cancelled', 'value' => 0, 'sub' => 'Voided sessions'],
                ];
        }
    }

    /**
     * Get full unpaginated records matching filters for Excel / PDF exports
     */
    public function getFullReportData(int $orgId, array $filters): array
    {
        $reportType = $filters['report_type'] ?? self::REPORT_ALL_OPERATIONS;

        // Run with large limit (export ceiling) to prevent memory exhaustion while exporting full matching dataset
        $exportLimit = 10000;
        switch ($reportType) {
            case self::REPORT_ATTENDANCE:
                $result = $this->queryAttendanceReport($orgId, $filters, $exportLimit, 0);
                break;
            case self::REPORT_GUARDS:
                $result = $this->queryGuardsReport($orgId, $filters, $exportLimit, 0);
                break;
            case self::REPORT_SITES_CLIENTS:
                $result = $this->querySitesClientsReport($orgId, $filters, $exportLimit, 0);
                break;
            case self::REPORT_SHIFTS:
                $result = $this->queryShiftsReport($orgId, $filters, $exportLimit, 0);
                break;
            case self::REPORT_CONTRACTS:
                $result = $this->queryContractsReport($orgId, $filters, $exportLimit, 0);
                break;
            case self::REPORT_ALL_OPERATIONS:
            default:
                $result = $this->queryAllOperationsReport($orgId, $filters, $exportLimit, 0);
                break;
        }

        return $result['records'] ?? [];
    }

    /**
     * Fetch cascading filter dropdown options scoped to organization
     */
    public function getFilterOptions(int $orgId, ?int $clientId = null, ?int $siteId = null): array
    {
        // 1. All Clients
        $stmtClients = $this->db->prepare(
            "SELECT id, name, client_code FROM customers 
             WHERE organization_id = :org_id AND deleted_at IS NULL 
             ORDER BY name ASC"
        );
        $stmtClients->execute(['org_id' => $orgId]);
        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

        // 2. Sites (filtered by client if specified)
        $siteSql = "SELECT id, customer_id, site_name, site_code FROM sites 
                    WHERE organization_id = :org_id AND deleted_at IS NULL";
        $siteParams = ['org_id' => $orgId];
        if (!empty($clientId)) {
            $siteSql .= " AND customer_id = :customer_id";
            $siteParams['customer_id'] = $clientId;
        }
        $siteSql .= " ORDER BY site_name ASC";
        $stmtSites = $this->db->prepare($siteSql);
        $stmtSites->execute($siteParams);
        $sites = $stmtSites->fetchAll(PDO::FETCH_ASSOC);

        // 3. Guards (filtered strictly by client and/or site cascading relationship)
        $guardParams = ['org_id' => $orgId];

        if (!empty($siteId)) {
            // Case B & C: Site specified (with or without client) -> only guards assigned to this site
            $guardSql = "SELECT DISTINCT g.id as guard_id, u.full_name, u.employee_code 
                         FROM guards g 
                         JOIN users u ON g.user_id = u.id 
                         JOIN contract_guard_assignments cga ON cga.guard_id = g.id AND cga.deleted_at IS NULL 
                         WHERE u.organization_id = :org_id 
                           AND u.deleted_at IS NULL 
                           AND cga.site_id = :site_id";
            $guardParams['site_id'] = $siteId;
        } elseif (!empty($clientId)) {
            // Case A: Client specified, Site = All -> only guards assigned to sites of this client
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
            // Case D: Client = All, Site = All -> all guards available to this organisation
            $guardSql = "SELECT DISTINCT g.id as guard_id, u.full_name, u.employee_code 
                         FROM guards g 
                         JOIN users u ON g.user_id = u.id 
                         WHERE u.organization_id = :org_id 
                           AND u.deleted_at IS NULL";
        }
        $guardSql .= " ORDER BY u.full_name ASC";
        $stmtGuards = $this->db->prepare($guardSql);
        $stmtGuards->execute($guardParams);
        $guards = $stmtGuards->fetchAll(PDO::FETCH_ASSOC);

        return [
            'clients' => $clients,
            'sites' => $sites,
            'guards' => $guards,
        ];
    }

    // =========================================================================
    // 1. ALL OPERATIONS REPORT
    // =========================================================================
    private function queryAllOperationsReport(int $orgId, array $filters, int $limit, int $offset): array
    {
        $params = ['org_id' => $orgId];
        $where = "att.organization_id = :org_id";

        $this->applyOperationalFilters($where, $params, $filters);

        // Count total matching records
        $countSql = "SELECT COUNT(*) FROM attendance att
                     JOIN guards g ON att.guard_id = g.id
                     JOIN users u ON g.user_id = u.id
                     LEFT JOIN sites s ON att.site_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                     LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                     WHERE {$where}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        // Fetch records
        $sql = "SELECT att.id, att.check_in_at, att.check_out_at, att.status as attendance_status_code,
                       att.check_in_latitude, att.check_in_longitude, att.check_out_latitude, att.check_out_longitude,
                       u.full_name as guard_name, u.employee_code as guard_badge,
                       s.site_name, s.site_code, s.latitude as site_lat, s.longitude as site_lng,
                       c.name as customer_name, c.client_code,
                       cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end
                FROM attendance att
                JOIN guards g ON att.guard_id = g.id
                JOIN users u ON g.user_id = u.id
                LEFT JOIN sites s ON att.site_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                WHERE {$where}
                ORDER BY att.check_in_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rawRecords as $row) {
            $records[] = $this->formatOperationalRow($row);
        }

        // Summary metrics
        $metricsSql = "SELECT 
                          SUM(CASE WHEN att.status = 0 AND att.check_out_at IS NULL THEN 1 ELSE 0 END) as on_duty_count,
                          SUM(CASE WHEN att.status = 1 OR att.check_out_at IS NOT NULL THEN 1 ELSE 0 END) as completed_count,
                          SUM(CASE WHEN att.status = 2 THEN 1 ELSE 0 END) as cancelled_count
                       FROM attendance att
                       JOIN guards g ON att.guard_id = g.id
                       JOIN users u ON g.user_id = u.id
                       LEFT JOIN sites s ON att.site_id = s.id
                       LEFT JOIN customers c ON s.customer_id = c.id
                       LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                       LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                       WHERE {$where}";
        $stmtMetrics = $this->db->prepare($metricsSql);
        $stmtMetrics->execute($params);
        $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'records' => $records,
            'total_records' => $totalRecords,
            'summary' => [
                'card1' => ['label' => 'Total Records', 'value' => $totalRecords, 'sub' => 'In selected period'],
                'card2' => ['label' => 'On Duty', 'value' => (int)($metrics['on_duty_count'] ?? 0), 'sub' => 'Active sessions'],
                'card3' => ['label' => 'Completed', 'value' => (int)($metrics['completed_count'] ?? 0), 'sub' => 'Successfully checked out'],
                'card4' => ['label' => 'Cancelled', 'value' => (int)($metrics['cancelled_count'] ?? 0), 'sub' => 'Voided sessions'],
            ],
        ];
    }

    // =========================================================================
    // 2. ATTENDANCE REPORT
    // =========================================================================
    private function queryAttendanceReport(int $orgId, array $filters, int $limit, int $offset): array
    {
        $params = ['org_id' => $orgId];
        $where = "att.organization_id = :org_id";

        $this->applyOperationalFilters($where, $params, $filters);

        $countSql = "SELECT COUNT(*) FROM attendance att
                     JOIN guards g ON att.guard_id = g.id
                     JOIN users u ON g.user_id = u.id
                     LEFT JOIN sites s ON att.site_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                     LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                     WHERE {$where}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $sql = "SELECT att.id, att.check_in_at, att.check_out_at, att.status as attendance_status_code,
                       att.check_in_latitude, att.check_in_longitude, att.check_out_latitude, att.check_out_longitude,
                       u.full_name as guard_name, u.employee_code as guard_badge,
                       s.site_name, s.site_code, s.latitude as site_lat, s.longitude as site_lng,
                       c.name as customer_name, c.client_code,
                       cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end
                FROM attendance att
                JOIN guards g ON att.guard_id = g.id
                JOIN users u ON g.user_id = u.id
                LEFT JOIN sites s ON att.site_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                WHERE {$where}
                ORDER BY att.check_in_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rawRecords as $row) {
            $records[] = $this->formatOperationalRow($row);
        }

        // Summary counts across all filtered records (not just current page)
        $metricsSql = "SELECT 
                          SUM(CASE WHEN att.status = 0 AND att.check_out_at IS NULL THEN 1 ELSE 0 END) as on_duty_count,
                          SUM(CASE WHEN att.status = 1 OR att.check_out_at IS NOT NULL THEN 1 ELSE 0 END) as completed_count,
                          SUM(CASE WHEN att.status = 2 THEN 1 ELSE 0 END) as cancelled_count
                       FROM attendance att
                       JOIN guards g ON att.guard_id = g.id
                       JOIN users u ON g.user_id = u.id
                       LEFT JOIN sites s ON att.site_id = s.id
                       LEFT JOIN customers c ON s.customer_id = c.id
                       LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                       LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                       WHERE {$where}";
        $stmtMetrics = $this->db->prepare($metricsSql);
        $stmtMetrics->execute($params);
        $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'records' => $records,
            'total_records' => $totalRecords,
            'summary' => [
                'card1' => ['label' => 'Total Records', 'value' => $totalRecords, 'sub' => 'Attendance entries'],
                'card2' => ['label' => 'On Duty', 'value' => (int)($metrics['on_duty_count'] ?? 0), 'sub' => 'Active sessions'],
                'card3' => ['label' => 'Completed', 'value' => (int)($metrics['completed_count'] ?? 0), 'sub' => 'Checked out'],
                'card4' => ['label' => 'Cancelled', 'value' => (int)($metrics['cancelled_count'] ?? 0), 'sub' => 'Voided sessions'],
            ],
        ];
    }

    // =========================================================================
    // 3. GUARDS REPORT
    // =========================================================================
    private function queryGuardsReport(int $orgId, array $filters, int $limit, int $offset): array
    {
        $params = ['org_id' => $orgId];
        $where = "att.organization_id = :org_id";

        $this->applyOperationalFilters($where, $params, $filters);

        $countSql = "SELECT COUNT(*) FROM attendance att
                     JOIN guards g ON att.guard_id = g.id
                     JOIN users u ON g.user_id = u.id
                     LEFT JOIN sites s ON att.site_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                     LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                     WHERE {$where}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $sql = "SELECT att.id, att.check_in_at, att.check_out_at, att.status as attendance_status_code,
                       att.check_in_latitude, att.check_in_longitude,
                       u.full_name as guard_name, u.employee_code as guard_badge, u.status as guard_user_status,
                       s.site_name, s.site_code, s.latitude as site_lat, s.longitude as site_lng,
                       c.name as customer_name, c.client_code,
                       cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end,
                       cga.status as assignment_status
                FROM attendance att
                JOIN guards g ON att.guard_id = g.id
                JOIN users u ON g.user_id = u.id
                LEFT JOIN sites s ON att.site_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                WHERE {$where}
                ORDER BY att.check_in_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rawRecords as $row) {
            $formatted = $this->formatOperationalRow($row);
            $formatted['assignment_status_label'] = ((int)($row['assignment_status'] ?? 0) === 0) ? 'Assigned' : 'Unassigned';
            $records[] = $formatted;
        }

        // Global metrics for guards
        $totalGuardsStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT g.id) as total_guards,
                    SUM(CASE WHEN u.status = 0 THEN 1 ELSE 0 END) as active_guards,
                    SUM(CASE WHEN u.status = 1 THEN 1 ELSE 0 END) as inactive_guards
             FROM guards g 
             JOIN users u ON g.user_id = u.id 
             WHERE u.organization_id = :org_id AND u.deleted_at IS NULL"
        );
        $totalGuardsStmt->execute(['org_id' => $orgId]);
        $guardCounts = $totalGuardsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $assignedStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT cga.guard_id) 
             FROM contract_guard_assignments cga 
             JOIN contracts c ON cga.contract_id = c.id 
             WHERE c.organization_id = :org_id AND cga.status = 0 AND cga.deleted_at IS NULL"
        );
        $assignedStmt->execute(['org_id' => $orgId]);
        $assignedCount = (int)$assignedStmt->fetchColumn();

        return [
            'records' => $records,
            'total_records' => $totalRecords,
            'summary' => [
                'card1' => ['label' => 'Total Guards', 'value' => (int)($guardCounts['total_guards'] ?? 0), 'sub' => 'Registered personnel'],
                'card2' => ['label' => 'Active', 'value' => (int)($guardCounts['active_guards'] ?? 0), 'sub' => 'Active status'],
                'card3' => ['label' => 'Inactive', 'value' => (int)($guardCounts['inactive_guards'] ?? 0), 'sub' => 'Inactive status'],
                'card4' => ['label' => 'Assigned', 'value' => $assignedCount, 'sub' => 'Rostered to posts'],
            ],
        ];
    }

    // =========================================================================
    // 4. SITES & CLIENTS REPORT
    // =========================================================================
    private function querySitesClientsReport(int $orgId, array $filters, int $limit, int $offset): array
    {
        $params = ['org_id' => $orgId];
        $where = "att.organization_id = :org_id";

        $this->applyOperationalFilters($where, $params, $filters);

        $countSql = "SELECT COUNT(*) FROM attendance att
                     JOIN guards g ON att.guard_id = g.id
                     JOIN users u ON g.user_id = u.id
                     LEFT JOIN sites s ON att.site_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                     LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                     WHERE {$where}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $sql = "SELECT att.id, att.check_in_at, att.check_out_at, att.status as attendance_status_code,
                       att.check_in_latitude, att.check_in_longitude,
                       u.full_name as guard_name, u.employee_code as guard_badge,
                       s.site_name, s.site_code, s.status as site_status, s.latitude as site_lat, s.longitude as site_lng,
                       c.name as customer_name, c.client_code,
                       cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end
                FROM attendance att
                JOIN guards g ON att.guard_id = g.id
                JOIN users u ON g.user_id = u.id
                LEFT JOIN sites s ON att.site_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                WHERE {$where}
                ORDER BY att.check_in_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rawRecords as $row) {
            $formatted = $this->formatOperationalRow($row);
            $formatted['site_status_label'] = ((int)($row['site_status'] ?? 0) === 0) ? 'Active' : 'Inactive';
            $records[] = $formatted;
        }

        $siteCountsStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT s.id) as total_sites,
                    SUM(CASE WHEN s.status = 0 THEN 1 ELSE 0 END) as active_sites,
                    SUM(CASE WHEN s.status = 1 THEN 1 ELSE 0 END) as inactive_sites
             FROM sites s 
             WHERE s.organization_id = :org_id AND s.deleted_at IS NULL"
        );
        $siteCountsStmt->execute(['org_id' => $orgId]);
        $siteCounts = $siteCountsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $siteAssignedStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT cga.guard_id) 
             FROM contract_guard_assignments cga 
             JOIN sites s ON cga.site_id = s.id 
             WHERE s.organization_id = :org_id AND cga.status = 0 AND cga.deleted_at IS NULL"
        );
        $siteAssignedStmt->execute(['org_id' => $orgId]);
        $siteAssignedCount = (int)$siteAssignedStmt->fetchColumn();

        return [
            'records' => $records,
            'total_records' => $totalRecords,
            'summary' => [
                'card1' => ['label' => 'Total Sites', 'value' => (int)($siteCounts['total_sites'] ?? 0), 'sub' => 'Duty posts registered'],
                'card2' => ['label' => 'Active', 'value' => (int)($siteCounts['active_sites'] ?? 0), 'sub' => 'Active sites'],
                'card3' => ['label' => 'Inactive', 'value' => (int)($siteCounts['inactive_sites'] ?? 0), 'sub' => 'Inactive sites'],
                'card4' => ['label' => 'Guards Assigned', 'value' => $siteAssignedCount, 'sub' => 'Deployed security staff'],
            ],
        ];
    }

    // =========================================================================
    // 5. SHIFTS REPORT
    // =========================================================================
    private function queryShiftsReport(int $orgId, array $filters, int $limit, int $offset): array
    {
        $params = ['org_id' => $orgId];
        $where = "att.organization_id = :org_id";

        $this->applyOperationalFilters($where, $params, $filters);

        $countSql = "SELECT COUNT(*) FROM attendance att
                     JOIN guards g ON att.guard_id = g.id
                     JOIN users u ON g.user_id = u.id
                     LEFT JOIN sites s ON att.site_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                     LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                     WHERE {$where}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $sql = "SELECT att.id, att.check_in_at, att.check_out_at, att.status as attendance_status_code,
                       att.check_in_latitude, att.check_in_longitude,
                       u.full_name as guard_name, u.employee_code as guard_badge,
                       s.site_name, s.site_code, s.latitude as site_lat, s.longitude as site_lng,
                       c.name as customer_name, c.client_code,
                       cs.shift_name, cs.start_time as shift_start, cs.end_time as shift_end
                FROM attendance att
                JOIN guards g ON att.guard_id = g.id
                JOIN users u ON g.user_id = u.id
                LEFT JOIN sites s ON att.site_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                WHERE {$where}
                ORDER BY att.check_in_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rawRecords as $row) {
            $records[] = $this->formatOperationalRow($row);
        }

        $metricsSql = "SELECT 
                          SUM(CASE WHEN att.status = 0 AND att.check_out_at IS NULL THEN 1 ELSE 0 END) as on_duty_count,
                          SUM(CASE WHEN att.status = 1 OR att.check_out_at IS NOT NULL THEN 1 ELSE 0 END) as completed_count,
                          SUM(CASE WHEN att.status = 2 THEN 1 ELSE 0 END) as cancelled_count
                       FROM attendance att
                       LEFT JOIN contract_guard_assignments cga ON att.assignment_id = cga.id
                       LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
                       LEFT JOIN sites s ON att.site_id = s.id
                       WHERE {$where}";
        $stmtMetrics = $this->db->prepare($metricsSql);
        $stmtMetrics->execute($params);
        $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'records' => $records,
            'total_records' => $totalRecords,
            'summary' => [
                'card1' => ['label' => 'Total Records', 'value' => $totalRecords, 'sub' => 'Shift duty logs'],
                'card2' => ['label' => 'On Duty', 'value' => (int)($metrics['on_duty_count'] ?? 0), 'sub' => 'Active sessions'],
                'card3' => ['label' => 'Completed', 'value' => (int)($metrics['completed_count'] ?? 0), 'sub' => 'Completed shifts'],
                'card4' => ['label' => 'Cancelled', 'value' => (int)($metrics['cancelled_count'] ?? 0), 'sub' => 'Voided sessions'],
            ],
        ];
    }

    // =========================================================================
    // 6. CONTRACTS REPORT
    // =========================================================================
    private function queryContractsReport(int $orgId, array $filters, int $limit, int $offset): array
    {
        $params = ['org_id' => $orgId];
        $where = "ctr.organization_id = :org_id AND ctr.deleted_at IS NULL";

        // Client filter
        if (!empty($filters['customer_id'])) {
            $where .= " AND ctr.customer_id = :customer_id";
            $params['customer_id'] = (int)$filters['customer_id'];
        }

        // Site filter
        if (!empty($filters['site_id'])) {
            $where .= " AND (ctr.site_id = :site_id OR EXISTS(SELECT 1 FROM contract_guard_assignments cga WHERE cga.contract_id = ctr.id AND cga.site_id = :site_id AND cga.deleted_at IS NULL))";
            $params['site_id'] = (int)$filters['site_id'];
        }

        // Date filter (Contract validity boundaries)
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        if (!empty($fromDate) && !empty($toDate)) {
            $where .= " AND ctr.start_date <= :to_date AND (ctr.end_date IS NULL OR ctr.end_date >= :from_date)";
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }

        // Status filter
        $status = $filters['status'] ?? 'all';
        if ($status === 'active') {
            $where .= " AND ctr.status = 0 AND (ctr.end_date IS NULL OR ctr.end_date > DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY))";
        } elseif ($status === 'expiring_soon') {
            $where .= " AND ctr.status = 0 AND ctr.end_date IS NOT NULL AND ctr.end_date >= CURRENT_DATE AND ctr.end_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)";
        } elseif ($status === 'expired') {
            $where .= " AND (ctr.status = 1 OR (ctr.end_date IS NOT NULL AND ctr.end_date < CURRENT_DATE))";
        }

        // Search term
        if (!empty($filters['search'])) {
            $where .= " AND (ctr.contract_code LIKE :search OR c.name LIKE :search OR s.site_name LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $countSql = "SELECT COUNT(*) FROM contracts ctr
                     JOIN customers c ON ctr.customer_id = c.id
                     LEFT JOIN sites s ON ctr.site_id = s.id
                     WHERE {$where}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $sql = "SELECT ctr.id, ctr.contract_code, ctr.start_date, ctr.end_date, ctr.required_guard_count, ctr.status as contract_status_code,
                       c.name as customer_name, c.client_code,
                       s.site_name, s.site_code,
                       (SELECT COUNT(DISTINCT cga.guard_id) FROM contract_guard_assignments cga WHERE cga.contract_id = ctr.id AND cga.status = 0 AND cga.deleted_at IS NULL) as assigned_guards
                FROM contracts ctr
                JOIN customers c ON ctr.customer_id = c.id
                LEFT JOIN sites s ON ctr.site_id = s.id
                WHERE {$where}
                ORDER BY ctr.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $expiringThreshold = date('Y-m-d', strtotime('+30 days'));

        $records = [];
        foreach ($rawRecords as $row) {
            $endDate = $row['end_date'];
            $statusLabel = 'Active';
            $statusClass = 'status-present';

            if ((int)$row['contract_status_code'] === 1 || (!empty($endDate) && $endDate < $today)) {
                $statusLabel = 'Expired';
                $statusClass = 'status-missing';
            } elseif (!empty($endDate) && $endDate >= $today && $endDate <= $expiringThreshold) {
                $statusLabel = 'Expiring Soon';
                $statusClass = 'status-late';
            }

            $records[] = [
                'id' => (int)$row['id'],
                'contract_code' => $row['contract_code'],
                'customer_name' => $row['customer_name'] ?? '—',
                'site_name' => $row['site_name'] ?? 'Multiple Sites',
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'] ?: 'Ongoing',
                'guard_limit' => (int)$row['required_guard_count'],
                'assigned_guards' => (int)$row['assigned_guards'],
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
            ];
        }

        // Summary counts
        $metricsSql = "SELECT 
                          SUM(CASE WHEN ctr.status = 0 AND (ctr.end_date IS NULL OR ctr.end_date > DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)) THEN 1 ELSE 0 END) as active_count,
                          SUM(CASE WHEN ctr.status = 0 AND ctr.end_date IS NOT NULL AND ctr.end_date >= CURRENT_DATE AND ctr.end_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon_count,
                          SUM(CASE WHEN ctr.status = 1 OR (ctr.end_date IS NOT NULL AND ctr.end_date < CURRENT_DATE) THEN 1 ELSE 0 END) as expired_count
                       FROM contracts ctr
                       JOIN customers c ON ctr.customer_id = c.id
                       LEFT JOIN sites s ON ctr.site_id = s.id
                       WHERE {$where}";
        $stmtMetrics = $this->db->prepare($metricsSql);
        $stmtMetrics->execute($params);
        $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'records' => $records,
            'total_records' => $totalRecords,
            'summary' => [
                'card1' => ['label' => 'Total Contracts', 'value' => $totalRecords, 'sub' => 'Agreements registered'],
                'card2' => ['label' => 'Active', 'value' => (int)($metrics['active_count'] ?? 0), 'sub' => 'Currently in force'],
                'card3' => ['label' => 'Expired', 'value' => (int)($metrics['expired_count'] ?? 0), 'sub' => 'Ended or terminated'],
                'card4' => ['label' => 'Expiring Soon', 'value' => (int)($metrics['expiring_soon_count'] ?? 0), 'sub' => 'Within next 30 days'],
            ],
        ];
    }

    // =========================================================================
    // COMMON FILTER APPLIER & ROW FORMATTER
    // =========================================================================
    private function applyOperationalFilters(string &$where, array &$params, array $filters): void
    {
        // Date boundaries
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        if (!empty($fromDate) && !empty($toDate)) {
            $where .= " AND DATE(att.check_in_at) BETWEEN :from_date AND :to_date";
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }

        // Client filter
        if (!empty($filters['customer_id'])) {
            $where .= " AND s.customer_id = :customer_id";
            $params['customer_id'] = (int)$filters['customer_id'];
        }

        // Site filter
        if (!empty($filters['site_id'])) {
            $where .= " AND att.site_id = :site_id";
            $params['site_id'] = (int)$filters['site_id'];
        }

        // Guard filter
        if (!empty($filters['guard_id'])) {
            $where .= " AND att.guard_id = :guard_id";
            $params['guard_id'] = (int)$filters['guard_id'];
        }

        // Status filter
        $status = $filters['status'] ?? 'all';
        $reportType = $filters['report_type'] ?? self::REPORT_ALL_OPERATIONS;

        if ($reportType === self::REPORT_GUARDS) {
            if ($status === 'active') {
                $where .= " AND u.status = 0";
            } elseif ($status === 'inactive') {
                $where .= " AND u.status = 1";
            }
        } elseif ($reportType === self::REPORT_SITES_CLIENTS) {
            if ($status === 'active') {
                $where .= " AND s.status = 0";
            } elseif ($status === 'inactive') {
                $where .= " AND s.status = 1";
            }
        } else {
            // all_operations, attendance, shifts
            if ($status === 'on_duty') {
                $where .= " AND att.status = 0 AND att.check_out_at IS NULL";
            } elseif ($status === 'completed') {
                $where .= " AND (att.status = 1 OR att.check_out_at IS NOT NULL)";
            } elseif ($status === 'cancelled') {
                $where .= " AND att.status = 2";
            }
        }

        // Search term
        if (!empty($filters['search'])) {
            $where .= " AND (u.full_name LIKE :search OR u.employee_code LIKE :search OR s.site_name LIKE :search OR c.name LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }
    }

    private function formatOperationalRow(array $row): array
    {
        $checkInAt = $row['check_in_at'] ?? null;
        $checkOutAt = $row['check_out_at'] ?? null;
        $shiftStart = $row['shift_start'] ?? null;
        $shiftEnd = $row['shift_end'] ?? null;
        $attStatusCode = (int)($row['attendance_status_code'] ?? 0);

        $dateFormatted = $checkInAt ? date('Y-m-d', strtotime($checkInAt)) : '—';
        $checkInTime = $checkInAt ? date('H:i', strtotime($checkInAt)) : '—';
        $checkOutTime = $checkOutAt ? date('H:i', strtotime($checkOutAt)) : '—';
        $scheduledStart = $shiftStart ? substr($shiftStart, 0, 5) : '—';
        $scheduledEnd = $shiftEnd ? substr($shiftEnd, 0, 5) : '—';

        if ($attStatusCode === 2) {
            $attStatus = 'Cancelled';
            $attClass = 'status-cancelled';
            $shiftStatus = 'Cancelled';
            $shiftClass = 'status-cancelled';
        } elseif ($attStatusCode === 1 || !empty($checkOutAt)) {
            $attStatus = 'Completed';
            $attClass = 'status-completed';
            $shiftStatus = 'Completed';
            $shiftClass = 'status-completed';
        } else {
            $attStatus = 'On Duty';
            $attClass = 'status-on-duty';
            $shiftStatus = 'On Duty';
            $shiftClass = 'status-on-duty';
        }

        // GPS Verification
        $hasCheckInGps = (isset($row['check_in_latitude']) && $row['check_in_latitude'] !== null && isset($row['check_in_longitude']) && $row['check_in_longitude'] !== null);
        $hasSiteGps = (!empty($row['site_lat']) && !empty($row['site_lng']));
        $gpsStatus = 'No GPS';
        $gpsClass = 'gps-none';

        if ($hasCheckInGps) {
            if ($hasSiteGps) {
                $dist = $this->calculateDistanceMeters(
                    (float)$row['check_in_latitude'],
                    (float)$row['check_in_longitude'],
                    (float)$row['site_lat'],
                    (float)$row['site_lng']
                );
                if ($dist <= 150.0) {
                    $gpsStatus = 'Verified (' . round($dist) . 'm)';
                    $gpsClass = 'gps-verified';
                } else {
                    $gpsStatus = 'Outside (' . round($dist) . 'm)';
                    $gpsClass = 'gps-outside';
                }
            } else {
                $gpsStatus = 'Captured';
                $gpsClass = 'gps-verified';
            }
        }

        return [
            'id' => (int)$row['id'],
            'date' => $dateFormatted,
            'guard_name' => $row['guard_name'] ?? '—',
            'guard_badge' => $row['guard_badge'] ?? '—',
            'customer_name' => $row['customer_name'] ?? '—',
            'site_name' => $row['site_name'] ?? '—',
            'shift_name' => $row['shift_name'] ?? 'Standard Shift',
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'scheduled_time' => ($scheduledStart !== '—' && $scheduledEnd !== '—') ? "{$scheduledStart} - {$scheduledEnd}" : '—',
            'check_in' => $checkInTime,
            'check_out' => $checkOutTime,
            'gps_status' => $gpsStatus,
            'gps_class' => $gpsClass,
            'attendance_status' => $attStatus,
            'attendance_class' => $attClass,
            'shift_status' => $shiftStatus,
            'shift_class' => $shiftClass,
        ];
    }

    private function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
