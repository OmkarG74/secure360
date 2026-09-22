<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\PdfReportService;
use App\Services\SubscriptionService;

/**
 * Superadmin Dedicated Subscriptions Controller
 * Manages tenant subscriptions, capacity adjustments, duration extensions, renewals, and suspensions.
 */
class SubscriptionController extends Controller
{
    private Subscription $subscriptionModel;
    private SubscriptionService $subscriptionService;
    private Organization $orgModel;
    private SystemSetting $settingModel;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        parent::__construct($request, $response);
        $this->subscriptionModel = new Subscription();
        $this->subscriptionService = new SubscriptionService();
        $this->orgModel = new Organization();
        $this->settingModel = new SystemSetting();
    }

    /**
     * List all subscriptions globally with status tabs, search, and pagination
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $statusFilter = trim((string)$this->request->query('status', 'all'));
        if (!in_array($statusFilter, ['all', 'active', 'expiring', 'expired', 'suspended'], true)) {
            $statusFilter = 'all';
        }

        $search = trim((string)$this->request->query('q', ''));
        $page = max(1, (int)$this->request->query('page', 1));
        $pageSize = 10;

        $totalRecords = $this->subscriptionModel->countGlobalFiltered($statusFilter, $search);
        $totalPages = max(1, (int)ceil($totalRecords / $pageSize));
        $offset = ($page - 1) * $pageSize;

        $subscriptions = $this->subscriptionModel->allGlobalPaginated($pageSize, $offset, $statusFilter, $search);
        $counts = $this->subscriptionModel->getStatusCounts();
        $currency = $this->settingModel->getCurrency();

        $this->render('superadmin/subscriptions/index', [
            'pageTitle' => 'Organisation Subscriptions - Superadmin',
            'subscriptions' => $subscriptions,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'counts' => $counts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'pageSize' => $pageSize,
            'currency' => $currency,
        ], 'layouts/superadmin');
    }

    /**
     * Export filtered subscriptions dataset to Excel (.xlsx)
     * Ignores pagination and exports the complete filtered dataset.
     */
    public function exportExcel(Request $request = null, Response $response = null): void
    {
        $req = $request ?? $this->request;
        $statusFilter = trim((string)$req->query('status', 'all'));
        if (!in_array($statusFilter, ['all', 'active', 'expiring', 'expired', 'suspended'], true)) {
            $statusFilter = 'all';
        }
        $search = trim((string)$req->query('q', ''));

        $subscriptions = $this->subscriptionModel->allGlobalFiltered($statusFilter, $search);

        $statusLabels = [
            'all' => 'All',
            'active' => 'Active',
            'expiring' => 'Expiring Soon',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
        ];

        $user = Auth::user();
        $meta = [
            'status_filter' => $statusLabels[$statusFilter] ?? 'All',
            'search' => $search,
            'generated_by' => $user ? ($user['full_name'] . ' (' . $user['email'] . ')') : 'Superadmin',
        ];

        $exportService = new ExcelExportService();
        $exportService->exportSubscriptions($subscriptions, $meta);
    }

    /**
     * Export filtered subscriptions dataset to PDF Report
     * Ignores pagination and exports the complete filtered dataset.
     */
    public function exportPdf(Request $request = null, Response $response = null): void
    {
        $req = $request ?? $this->request;
        $statusFilter = trim((string)$req->query('status', 'all'));
        if (!in_array($statusFilter, ['all', 'active', 'expiring', 'expired', 'suspended'], true)) {
            $statusFilter = 'all';
        }
        $search = trim((string)$req->query('q', ''));

        $subscriptions = $this->subscriptionModel->allGlobalFiltered($statusFilter, $search);

        $statusLabels = [
            'all' => 'All',
            'active' => 'Active',
            'expiring' => 'Expiring Soon',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
        ];

        $user = Auth::user();
        $meta = [
            'status_filter' => $statusLabels[$statusFilter] ?? 'All',
            'search' => $search,
            'generated_by' => $user ? ($user['full_name'] . ' (' . $user['email'] . ')') : 'Superadmin',
        ];

        $pdfService = new PdfReportService();
        $pdfService->exportSubscriptions($subscriptions, $meta);
    }

    /**
     * Show subscription creation form for an existing organisation
     */
    public function createForm(Request $request = null, Response $response = null): void
    {
        $organizations = $this->orgModel->allActive();
        foreach ($organizations as &$o) {
            $existing = $this->subscriptionModel->findCurrentByOrganization((int)$o['id']);
            $o['has_active_sub'] = $existing && in_array($existing['calculated_status'], ['active', 'expiring_soon'], true);
            $o['current_sub_id'] = $existing ? (int)$existing['id'] : null;
        }
        unset($o);

        $defaultPrice = $this->settingModel->getDefaultPricePerGuard();
        $currency = $this->settingModel->getCurrency();
        $preselectedOrgId = (int)$this->request->query('org_id', 0);

        $this->render('superadmin/subscriptions/create', [
            'pageTitle' => 'Create Subscription - Superadmin',
            'organizations' => $organizations,
            'defaultPrice' => $defaultPrice,
            'currency' => $currency,
            'preselectedOrgId' => $preselectedOrgId,
        ], 'layouts/superadmin');
    }

    /**
     * Store new subscription for an existing organisation
     */
    public function store(Request $request = null, Response $response = null): void
    {
        $orgId = (int)$this->request->input('organization_id', 0);
        $guardLimit = max(1, (int)$this->request->input('guard_limit', 30));
        $priceInput = $this->request->input('price_per_guard');
        $pricePerGuard = ($priceInput !== null && $priceInput !== '' && (float)$priceInput >= 0)
            ? round((float)$priceInput, 2)
            : $this->settingModel->getDefaultPricePerGuard();

        $startDate = trim((string)$this->request->input('start_date', '')) ?: date('Y-m-d');
        $endDate = trim((string)$this->request->input('end_date', '')) ?: date('Y-m-d', strtotime('+1 year', strtotime($startDate)));

        if ($orgId <= 0) {
            $this->setFlash('error', 'Please select a valid organisation.');
            $this->redirect('/superadmin/subscriptions/create');
            return;
        }

        $org = $this->orgModel->find($orgId);
        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/subscriptions/create');
            return;
        }

        if (strtotime($endDate) <= strtotime($startDate)) {
            $this->setFlash('error', 'Subscription end date must be after the start date.');
            $this->redirect("/superadmin/subscriptions/create?org_id={$orgId}");
            return;
        }

        // Check for existing active subscription
        $existingSub = $this->subscriptionModel->findCurrentByOrganization($orgId);
        if ($existingSub && in_array($existingSub['calculated_status'], ['active', 'expiring_soon'], true)) {
            $this->setFlash('error', "Organisation '{$org['name']}' already has an active subscription (#SUB-{$existingSub['id']}). Please view or edit the existing subscription.");
            $this->redirect("/superadmin/subscriptions/{$existingSub['id']}");
            return;
        }

        try {
            $result = $this->subscriptionService->createInitialSubscription($orgId, [
                'guard_limit' => $guardLimit,
                'price_per_guard' => $pricePerGuard,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => trim((string)$this->request->input('notes', 'Subscription created by Superadmin')),
            ], Auth::id());

            $this->setFlash('success', "Subscription created successfully for '{$org['name']}' (Invoice #{$result['invoice_number']} generated).");
            $this->redirect("/superadmin/subscriptions/{$result['subscription_id']}");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to create subscription: ' . $e->getMessage());
            $this->redirect("/superadmin/subscriptions/create?org_id={$orgId}");
        }
    }

    /**
     * Show subscription details, capacity usage, audit history, and generated invoices
     */
    public function show(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $sub = $this->subscriptionModel->findWithDetails($id);

        if (!$sub) {
            $this->setFlash('error', 'Subscription not found.');
            $this->redirect('/superadmin/subscriptions');
            return;
        }

        $orgId = (int)$sub['organization_id'];
        $activeGuards = $this->subscriptionService->countActiveGuards($orgId);
        $guardLimit = (int)$sub['guard_limit'];
        $availableSlots = max(0, $guardLimit - $activeGuards);
        $usagePercent = $guardLimit > 0 ? min(100, round(($activeGuards / $guardLimit) * 100)) : 100;

        // Comprehensive history for this organization across versions
        $history = $this->subscriptionModel->getHistoryByOrganization($orgId);

        $userModel = new User();
        $admins = $userModel->findAdminsByOrganization($orgId);
        $primaryAdmin = !empty($admins) ? $admins[0] : null;

        // Fetch associated invoice generated for this subscription
        $db = Database::getConnection();
        $stmtInv = $db->prepare(
            "SELECT * FROM invoices WHERE subscription_id = :sub_id ORDER BY id DESC LIMIT 1"
        );
        $stmtInv->execute(['sub_id' => $id]);
        $subInvoice = $stmtInv->fetch() ?: null;

        $currency = $this->settingModel->getCurrency();

        $this->render('superadmin/subscriptions/show', [
            'pageTitle' => "Subscription #SUB-{$id} - {$sub['organization_name']}",
            'sub' => $sub,
            'primaryAdmin' => $primaryAdmin,
            'activeGuards' => $activeGuards,
            'availableSlots' => $availableSlots,
            'usagePercent' => $usagePercent,
            'history' => $history,
            'subInvoice' => $subInvoice,
            'currency' => $currency,
        ], 'layouts/superadmin');
    }

    /**
     * Show edit subscription form (Guard capacity modification or duration extension)
     */
    public function editForm(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $sub = $this->subscriptionModel->findWithDetails($id);

        if (!$sub) {
            $this->setFlash('error', 'Subscription not found.');
            $this->redirect('/superadmin/subscriptions');
            return;
        }

        $orgId = (int)$sub['organization_id'];
        $activeGuards = $this->subscriptionService->countActiveGuards($orgId);
        $currency = $this->settingModel->getCurrency();

        $userModel = new User();
        $admins = $userModel->findAdminsByOrganization($orgId);
        $primaryAdmin = !empty($admins) ? $admins[0] : null;

        $this->render('superadmin/subscriptions/edit', [
            'pageTitle' => "Edit Subscription #SUB-{$id} - {$sub['organization_name']}",
            'sub' => $sub,
            'primaryAdmin' => $primaryAdmin,
            'activeGuards' => $activeGuards,
            'currency' => $currency,
        ], 'layouts/superadmin');
    }

    /**
     * Process subscription updates (Guard capacity modification or duration extension)
     * Enforces Immutability & Versioning: Old subscription preserved, new subscription and invoice created.
     */
    public function update(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $sub = $this->subscriptionModel->findWithDetails($id);

        if (!$sub) {
            $this->setFlash('error', 'Subscription not found.');
            $this->redirect('/superadmin/subscriptions');
            return;
        }

        $orgId = (int)$sub['organization_id'];

        $newLimit = (int)$this->request->input('guard_limit', $sub['guard_limit']);
        $newPrice = (float)$this->request->input('price_per_guard', $sub['price_per_guard']);
        $newEndDate = trim((string)$this->request->input('new_end_date', ''));

        $isCapacityChanged = ($newLimit !== (int)$sub['guard_limit']) || (abs($newPrice - (float)$sub['price_per_guard']) > 0.001);
        $isEndDateChanged = ($newEndDate !== '' && strtotime($newEndDate) !== strtotime($sub['end_date']));
        $isCommercialChange = $isCapacityChanged || $isEndDateChanged;

        if (!$isCommercialChange) {
            $this->setFlash('info', 'No commercial modifications were made to the subscription.');
            $this->redirect("/superadmin/subscriptions/{$id}");
            return;
        }

        try {
            $res = $this->subscriptionService->createSubscriptionVersion($id, [
                'guard_limit' => $newLimit,
                'price_per_guard' => $newPrice,
                'end_date' => $newEndDate ?: $sub['end_date'],
            ], Auth::id());

            $this->setFlash(
                'success',
                "Subscription updated successfully. A new subscription and invoice have been created."
            );

            $this->redirect("/superadmin/subscriptions/{$res['new_subscription_id']}");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to update subscription: ' . $e->getMessage());
            $this->redirect("/superadmin/subscriptions/{$id}/edit");
        }
    }

    /**
     * Toggle subscription suspension status
     */
    public function toggleStatus(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);

        try {
            $res = $this->subscriptionService->toggleSubscriptionStatus($id, Auth::id());
            $this->setFlash('success', "Subscription #SUB-{$id} status changed to {$res['status_label']}.");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to update subscription status: ' . $e->getMessage());
        }

        $redirect = $this->request->input('redirect') ?? $this->request->query('redirect');
        if (!empty($redirect)) {
            $this->redirect($redirect);
            return;
        }

        $this->redirect("/superadmin/subscriptions/{$id}");
    }

    /**
     * Renew subscription
     */
    public function renew(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $sub = $this->subscriptionModel->findWithDetails($id);

        if (!$sub) {
            $this->setFlash('error', 'Subscription not found.');
            $this->redirect('/superadmin/subscriptions');
            return;
        }

        $orgId = (int)$sub['organization_id'];
        $guardLimit = max(1, (int)$this->request->input('guard_limit', $sub['guard_limit']));
        $pricePerGuard = (float)$this->request->input('price_per_guard', $sub['price_per_guard']);
        $startDate = trim((string)$this->request->input('start_date', '')) ?: date('Y-m-d');
        $endDate = trim((string)$this->request->input('end_date', '')) ?: date('Y-m-d', strtotime('+1 year', strtotime($startDate)));

        try {
            $res = $this->subscriptionService->renewSubscription($orgId, [
                'guard_limit' => $guardLimit,
                'price_per_guard' => $pricePerGuard,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => 'Subscription renewed by Superadmin',
            ], Auth::id());

            $this->setFlash('success', "Subscription renewed successfully. New invoice #{$res['invoice_number']} generated.");
            $this->redirect("/superadmin/subscriptions/{$res['subscription_id']}");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to renew subscription: ' . $e->getMessage());
            $this->redirect("/superadmin/subscriptions/{$id}");
        }
    }
}
