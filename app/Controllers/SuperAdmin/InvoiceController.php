<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invoice;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\PdfInvoiceService;
use App\Services\PdfReportService;

/**
 * Superadmin Dedicated Invoices Controller
 * Read/View-oriented registry for billing statements generated from subscriptions.
 * Standalone invoice creation is prohibited by business architecture.
 */
class InvoiceController extends Controller
{
    private Invoice $invoiceModel;
    private SystemSetting $settingModel;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        parent::__construct($request, $response);
        $this->invoiceModel = new Invoice();
        $this->settingModel = new SystemSetting();
    }

    /**
     * List all platform invoices with status tabs, search, and pagination
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $statusFilter = trim((string)$this->request->query('status', 'all'));
        if (!in_array($statusFilter, ['all', 'paid', 'pending', 'cancelled'], true)) {
            $statusFilter = 'all';
        }

        $search = trim((string)$this->request->query('q', ''));
        $page = max(1, (int)$this->request->query('page', 1));
        $pageSize = 10;

        $totalRecords = $this->invoiceModel->countGlobalFiltered($statusFilter, $search);
        $totalPages = max(1, (int)ceil($totalRecords / $pageSize));
        $offset = ($page - 1) * $pageSize;

        $invoices = $this->invoiceModel->allGlobalPaginated($pageSize, $offset, $statusFilter, $search);
        $counts = $this->invoiceModel->getStatusCounts();
        $currency = $this->settingModel->getCurrency();

        $this->render('superadmin/invoices/index', [
            'pageTitle' => 'Billing Invoices - Superadmin',
            'invoices' => $invoices,
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
     * Display detailed invoice view (Section 10)
     */
    public function show(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $invoice = $this->invoiceModel->findWithDetails($id);

        if (!$invoice) {
            $this->setFlash('error', 'Invoice statement not found.');
            $this->redirect('/superadmin/invoices');
            return;
        }

        $userModel = new User();
        $admins = $userModel->findAdminsByOrganization((int)$invoice['organization_id']);
        $primaryAdmin = !empty($admins) ? $admins[0] : null;

        $currency = $this->settingModel->getCurrency();

        $this->render('superadmin/invoices/show', [
            'pageTitle' => "Invoice #{$invoice['invoice_number']} - {$invoice['organization_name']}",
            'invoice' => $invoice,
            'primaryAdmin' => $primaryAdmin,
            'currency' => $currency,
        ], 'layouts/superadmin');
    }

    /**
     * Export all matching invoices to genuine Excel (.xlsx) file (ignores pagination)
     */
    public function exportExcel(Request $request = null, Response $response = null): void
    {
        $req = $request ?? $this->request;
        $statusFilter = trim((string)$req->query('status', 'all'));
        if (!in_array($statusFilter, ['all', 'paid', 'pending', 'cancelled'], true)) {
            $statusFilter = 'all';
        }
        $search = trim((string)$req->query('q', ''));

        $invoices = $this->invoiceModel->allGlobalFiltered($statusFilter, $search);

        $statusLabels = [
            'all' => 'All',
            'paid' => 'Paid',
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
        ];

        $user = Auth::user();
        $meta = [
            'status_filter' => $statusLabels[$statusFilter] ?? 'All',
            'search' => $search,
            'generated_by' => $user ? ($user['full_name'] . ' (' . $user['email'] . ')') : 'Superadmin',
        ];

        $excelService = new ExcelExportService();
        $excelService->exportInvoices($invoices, $meta);
    }

    /**
     * Export all matching invoices to PDF report (ignores pagination)
     */
    public function exportPdf(Request $request = null, Response $response = null): void
    {
        $req = $request ?? $this->request;
        $statusFilter = trim((string)$req->query('status', 'all'));
        if (!in_array($statusFilter, ['all', 'paid', 'pending', 'cancelled'], true)) {
            $statusFilter = 'all';
        }
        $search = trim((string)$req->query('q', ''));

        $invoices = $this->invoiceModel->allGlobalFiltered($statusFilter, $search);

        $statusLabels = [
            'all' => 'All',
            'paid' => 'Paid',
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
        ];

        $user = Auth::user();
        $meta = [
            'status_filter' => $statusLabels[$statusFilter] ?? 'All',
            'search' => $search,
            'generated_by' => $user ? ($user['full_name'] . ' (' . $user['email'] . ')') : 'Superadmin',
        ];

        $pdfService = new PdfReportService();
        $pdfService->exportInvoices($invoices, $meta);
    }

    /**
     * Generate and stream single invoice PDF for download
     */
    public function download(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $invoice = $this->invoiceModel->findWithDetails($id);

        if (!$invoice) {
            $this->setFlash('error', 'Invoice not found.');
            $this->redirect('/superadmin/invoices');
            return;
        }

        $pdfService = new PdfInvoiceService();
        $pdfContent = $pdfService->generate($invoice);

        $filename = "Invoice_{$invoice['invoice_number']}.pdf";

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }
}
