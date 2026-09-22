<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\PdfInvoiceService;
use App\Services\SubscriptionService;

/**
 * Organisation Admin Billing & Invoice Portal Controller
 * Strictly scoped to tenant organisation
 */
class BillingController extends Controller
{
    /**
     * Display organisation subscription overview and invoice history
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $subService = new SubscriptionService();
        $subDetails = $subService->getSubscriptionDetails($orgId);

        $invoiceModel = new Invoice();
        $page = max(1, (int)$this->request->query('page', 1));
        $pageSize = 10;
        $totalRecords = $invoiceModel->countByTenant($orgId);
        $totalPages = max(1, (int)ceil($totalRecords / $pageSize));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $pageSize;

        $invoices = $invoiceModel->paginateByTenant($orgId, $pageSize, $offset);

        $this->render('admin/billing/index', [
            'pageTitle' => 'Subscription & Billing - Secure360',
            'sub' => $subDetails,
            'invoices' => $invoices,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'pageSize' => $pageSize,
        ], 'layouts/admin');
    }

    /**
     * Download invoice PDF for tenant organisation
     */
    public function downloadInvoice(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $invoiceId = (int)($params['id'] ?? $this->request->query('id', 0));

        if ($invoiceId <= 0) {
            $this->setFlash('error', 'Invalid invoice ID.');
            $this->redirect('/admin/billing');
            return;
        }

        $invoiceModel = new Invoice();
        // Strict tenant isolation
        $invoice = $invoiceModel->findByTenant($invoiceId, $orgId);

        if (!$invoice) {
            $this->setFlash('error', 'Invoice not found or access denied.');
            $this->redirect('/admin/billing');
            return;
        }

        $pdfService = new PdfInvoiceService();
        $pdfContent = $pdfService->generate($invoice);

        $filename = "Invoice_{$invoice['invoice_number']}.pdf";

        // Stream PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }
}
