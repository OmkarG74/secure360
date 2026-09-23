<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\ExcelExportService;
use App\Services\PdfReportService;
use App\Services\ReportService;
use PDO;

/**
 * Organisation Admin Reports Controller
 * Powers the unified Operational Reports & Analytics module.
 * Provides data querying, server-side pagination, summary metrics calculation,
 * cascading filter synchronization, and full Excel/PDF exports.
 */
class ReportController extends Controller
{
    private ReportService $reportService;
    private ExcelExportService $excelService;
    private PdfReportService $pdfService;

    public function __construct(
        ?\App\Core\Request $request = null,
        ?\App\Core\Response $response = null,
        ?ReportService $reportService = null,
        ?ExcelExportService $excelService = null,
        ?PdfReportService $pdfService = null
    ) {
        parent::__construct($request, $response);
        $this->reportService = $reportService ?? new ReportService();
        $this->excelService = $excelService ?? new ExcelExportService();
        $this->pdfService = $pdfService ?? new PdfReportService();
    }

    /**
     * Main Operational Reports Dashboard View
     */
    public function index(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $reportType = (string)$this->request->query('report_type', ReportService::REPORT_ALL_OPERATIONS);
        $preset = (string)$this->request->query('preset', 'this_month');
        $rawFrom = $this->request->query('from_date');
        $rawTo = $this->request->query('to_date');
        $customerId = $this->request->query('customer_id') ? (int)$this->request->query('customer_id') : null;
        $siteId = $this->request->query('site_id') ? (int)$this->request->query('site_id') : null;
        $guardId = $this->request->query('guard_id') ? (int)$this->request->query('guard_id') : null;
        $status = (string)$this->request->query('status', 'all');
        $search = trim((string)$this->request->query('search', ''));
        $page = max(1, (int)$this->request->query('page', 1));
        $pageSize = max(1, min(100, (int)$this->request->query('per_page', 25)));

        // Validate and sanitize Client -> Site -> Guard cascading relationships
        $this->validateFilterRelationships($orgId, $customerId, $siteId, $guardId);

        // Sanitize status based on selected report type
        $status = $this->sanitizeStatusForReportType($reportType, $status);

        // Resolve and validate dates
        [$fromDate, $toDate, $resolvedPreset] = $this->reportService->resolveDatePreset($preset, $rawFrom, $rawTo);
        $dateError = null;

        if ($preset === 'custom') {
            if (!empty($rawFrom) && !empty($rawTo)) {
                $timeFrom = strtotime($rawFrom);
                $timeTo = strtotime($rawTo);
                if ($timeFrom === false || $timeTo === false) {
                    $dateError = 'Invalid Date Range: Please select valid calendar dates.';
                } elseif ($rawFrom > $rawTo) {
                    $dateError = 'Invalid Date Range: "From Date" cannot be after "To Date".';
                }
            } elseif (empty($rawFrom) || empty($rawTo)) {
                $dateError = 'Invalid Date Range: Both "From Date" and "To Date" must be provided.';
            }
        }

        $filters = [
            'report_type' => $reportType,
            'preset' => $resolvedPreset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'customer_id' => $customerId,
            'site_id' => $siteId,
            'guard_id' => $guardId,
            'status' => $status,
            'search' => $search,
        ];

        // Fetch cascading dropdown options
        $filterOptions = $this->reportService->getFilterOptions($orgId, $customerId, $siteId);

        // Fetch paginated report data & summary cards (or empty state if date validation failed)
        if ($dateError !== null) {
            $reportData = [
                'records' => [],
                'total_records' => 0,
                'summary' => $this->reportService->getEmptySummary($reportType),
            ];
        } else {
            $reportData = $this->reportService->getReportData($orgId, $filters, $page, $pageSize);
        }

        $this->render('admin/reports/index', [
            'pageTitle' => 'Operational Reports & Analytics - Secure360',
            'reportType' => $reportType,
            'preset' => $resolvedPreset,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'customerId' => $customerId,
            'siteId' => $siteId,
            'guardId' => $guardId,
            'status' => $status,
            'search' => $search,
            'dateError' => $dateError,
            'filterOptions' => $filterOptions,
            'records' => $reportData['records'],
            'totalRecords' => $reportData['total_records'],
            'summary' => $reportData['summary'],
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'queryParams' => $_GET,
        ], 'layouts/admin');
    }

    /**
     * Export full filtered records to genuine OpenXML Excel (.xlsx)
     */
    public function exportExcel(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $filters = $this->extractFiltersFromRequest();
        if ($filters['error'] !== null) {
            $this->setFlash('danger', $filters['error']);
            $this->redirect(url('/admin/reports?' . http_build_query($_GET)));
            return;
        }

        $meta = $this->buildExportMeta($orgId, $filters);

        // Fetch ALL matching records (unpaginated)
        $records = $this->reportService->getFullReportData($orgId, $filters);

        $this->excelService->exportOperationalReport($filters['report_type'], $records, $meta);
    }

    /**
     * Export full filtered records to genuine landscape PDF (.pdf)
     */
    public function exportPdf(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $filters = $this->extractFiltersFromRequest();
        if ($filters['error'] !== null) {
            $this->setFlash('danger', $filters['error']);
            $this->redirect(url('/admin/reports?' . http_build_query($_GET)));
            return;
        }

        $meta = $this->buildExportMeta($orgId, $filters);

        // Fetch ALL matching records (unpaginated)
        $records = $this->reportService->getFullReportData($orgId, $filters);

        $this->pdfService->exportOperationalReport($filters['report_type'], $records, $meta);
    }

    /**
     * AJAX endpoint to return dynamically cascading filter options
     */
    public function ajaxFilterOptions(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $customerId = $this->request->query('customer_id') ? (int)$this->request->query('customer_id') : null;
        $siteId = $this->request->query('site_id') ? (int)$this->request->query('site_id') : null;
        $dummyGuard = null;

        // Validate relationship before fetching options
        $this->validateFilterRelationships($orgId, $customerId, $siteId, $dummyGuard);

        $options = $this->reportService->getFilterOptions($orgId, $customerId, $siteId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $options,
            'resolved_customer_id' => $customerId,
            'resolved_site_id' => $siteId,
        ]);
        exit;
    }

    /**
     * Extract and validate filter parameters from incoming request
     */
    private function extractFiltersFromRequest(): array
    {
        $orgId = Auth::organisationId() ?? 1;
        $reportType = (string)$this->request->query('report_type', ReportService::REPORT_ALL_OPERATIONS);
        $preset = (string)$this->request->query('preset', 'this_month');
        $rawFrom = $this->request->query('from_date');
        $rawTo = $this->request->query('to_date');
        $customerId = $this->request->query('customer_id') ? (int)$this->request->query('customer_id') : null;
        $siteId = $this->request->query('site_id') ? (int)$this->request->query('site_id') : null;
        $guardId = $this->request->query('guard_id') ? (int)$this->request->query('guard_id') : null;
        $status = (string)$this->request->query('status', 'all');
        $search = trim((string)$this->request->query('search', ''));

        // Validate and sanitize Client -> Site -> Guard cascading relationships
        $this->validateFilterRelationships($orgId, $customerId, $siteId, $guardId);

        // Sanitize status based on selected report type
        $status = $this->sanitizeStatusForReportType($reportType, $status);

        [$fromDate, $toDate, $resolvedPreset] = $this->reportService->resolveDatePreset($preset, $rawFrom, $rawTo);
        $error = null;
        if ($preset === 'custom') {
            if (!empty($rawFrom) && !empty($rawTo)) {
                $timeFrom = strtotime($rawFrom);
                $timeTo = strtotime($rawTo);
                if ($timeFrom === false || $timeTo === false) {
                    $error = 'Invalid Date Range: Please select valid calendar dates.';
                } elseif ($rawFrom > $rawTo) {
                    $error = 'Invalid Date Range: "From Date" cannot be after "To Date".';
                }
            } elseif (empty($rawFrom) || empty($rawTo)) {
                $error = 'Invalid Date Range: Both "From Date" and "To Date" must be provided.';
            }
        }

        return [
            'report_type' => $reportType,
            'preset' => $resolvedPreset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'customer_id' => $customerId,
            'site_id' => $siteId,
            'guard_id' => $guardId,
            'status' => $status,
            'search' => $search,
            'error' => $error,
        ];
    }

    /**
     * Validate and sanitize client -> site -> guard filter relationships.
     * Prevents invalid combinations, cross-client data leakage, and ID manipulation.
     */
    private function validateFilterRelationships(int $orgId, ?int &$customerId, ?int &$siteId, ?int &$guardId): void
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
                    // Site does not belong to selected Customer -> reject/clear invalid site
                    $siteId = null;
                } elseif ($customerId === null) {
                    // Site automatically implies its parent Customer
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
                    // Guard must have assignment at this specific Site
                    $stmtA = $db->prepare("SELECT id FROM contract_guard_assignments WHERE guard_id = :guard_id AND site_id = :site_id AND deleted_at IS NULL");
                    $stmtA->execute(['guard_id' => $guardId, 'site_id' => $siteId]);
                    if (!$stmtA->fetchColumn()) {
                        $guardId = null;
                    }
                } elseif ($customerId !== null) {
                    // Guard must have assignment at a Site belonging to this Customer
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
     * Sanitize status value to ensure it strictly belongs to supported statuses for the report type.
     */
    private function sanitizeStatusForReportType(string $reportType, string $status): string
    {
        if ($status === 'all') {
            return 'all';
        }

        switch ($reportType) {
            case ReportService::REPORT_CONTRACTS:
                $allowed = ['active', 'expiring_soon', 'expired'];
                break;
            case ReportService::REPORT_GUARDS:
            case ReportService::REPORT_SITES_CLIENTS:
                $allowed = ['active', 'inactive'];
                break;
            case ReportService::REPORT_ATTENDANCE:
            case ReportService::REPORT_SHIFTS:
            case ReportService::REPORT_ALL_OPERATIONS:
            default:
                $allowed = ['on_duty', 'completed', 'cancelled'];
                break;
        }

        return in_array($status, $allowed, true) ? $status : 'all';
    }

    /**
     * Build descriptive metadata array for export headers
     */
    private function buildExportMeta(int $orgId, array $filters): array
    {
        $db = Database::getConnection();

        // 1. Organisation Name
        $stmtOrg = $db->prepare("SELECT name FROM organizations WHERE id = :id");
        $stmtOrg->execute(['id' => $orgId]);
        $orgName = (string)$stmtOrg->fetchColumn() ?: 'Apex Security';

        // 2. Client Name
        $clientName = 'All Clients';
        if (!empty($filters['customer_id'])) {
            $stmtC = $db->prepare("SELECT name FROM customers WHERE id = :id AND organization_id = :org_id");
            $stmtC->execute(['id' => $filters['customer_id'], 'org_id' => $orgId]);
            $clientName = (string)$stmtC->fetchColumn() ?: 'Selected Client';
        }

        // 3. Site Name
        $siteName = 'All Sites';
        if (!empty($filters['site_id'])) {
            $stmtS = $db->prepare("SELECT site_name FROM sites WHERE id = :id AND organization_id = :org_id");
            $stmtS->execute(['id' => $filters['site_id'], 'org_id' => $orgId]);
            $siteName = (string)$stmtS->fetchColumn() ?: 'Selected Site';
        }

        // 4. Guard Name
        $guardName = 'All Guards';
        if (!empty($filters['guard_id'])) {
            $stmtG = $db->prepare("SELECT u.full_name FROM guards g JOIN users u ON g.user_id = u.id WHERE g.id = :id AND u.organization_id = :org_id");
            $stmtG->execute(['id' => $filters['guard_id'], 'org_id' => $orgId]);
            $guardName = (string)$stmtG->fetchColumn() ?: 'Selected Guard';
        }

        // 5. Preset Label
        $presetMap = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'custom' => 'Custom Date Range',
        ];
        $presetLabel = $presetMap[$filters['preset']] ?? ucfirst($filters['preset']);

        // 6. Report Type Label
        $reportTypeMap = [
            ReportService::REPORT_ALL_OPERATIONS => 'All Operations',
            ReportService::REPORT_ATTENDANCE => 'Attendance',
            ReportService::REPORT_GUARDS => 'Guards',
            ReportService::REPORT_SITES_CLIENTS => 'Sites & Clients',
            ReportService::REPORT_SHIFTS => 'Shifts',
            ReportService::REPORT_CONTRACTS => 'Contracts',
        ];
        $reportTypeLabel = $reportTypeMap[$filters['report_type']] ?? 'All Operations';

        // 7. Status Label
        $statusMap = [
            'all' => 'All Statuses',
            'present' => 'Present',
            'late' => 'Late',
            'missing_checkout' => 'Missing Checkout',
            'active' => 'Active',
            'completed' => 'Completed',
            'expiring_soon' => 'Expiring Soon',
            'expired' => 'Expired',
        ];
        $statusLabel = $statusMap[$filters['status']] ?? ucfirst($filters['status']);

        $currentUser = Auth::user();
        $userName = $currentUser ? ($currentUser['full_name'] ?? 'Admin') : 'Admin';

        return [
            'report_title' => 'Secure360 - ' . $reportTypeLabel . ' Report',
            'report_type_label' => $reportTypeLabel,
            'organization_name' => $orgName,
            'preset_label' => $presetLabel,
            'from_date' => $filters['from_date'] ?? date('Y-m-d'),
            'to_date' => $filters['to_date'] ?? date('Y-m-d'),
            'client_name' => $clientName,
            'site_name' => $siteName,
            'guard_name' => $guardName,
            'status_label' => $statusLabel,
            'search' => $filters['search'] ?? '',
            'generated_by' => $userName,
        ];
    }
}

