<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure Core PHP PDF Report Generator for Superadmin
 * Produces clean, professional PDF-1.4 tabular reports for Organisations and Subscriptions.
 * Includes header banners, filter summaries, alternating row colors, and multi-page pagination.
 * Zero external Composer dependencies required.
 */
class PdfReportService
{
    private string $buffer = '';
    private array $offsets = [];
    private int $pageWidth = 842;  // Landscape A4 width in pt
    private int $pageHeight = 595; // Landscape A4 height in pt

    /**
     * Generate and stream Organisations PDF to browser
     */
    public function exportOrganisations(array $organizations, array $meta = []): void
    {
        $filename = 'secure360-organisations-' . date('Y-m-d_His') . '.pdf';
        $pdfContent = $this->generateOrganisationsPdf($organizations, $meta);
        $this->streamPdf($filename, $pdfContent);
    }

    /**
     * Generate and stream Subscriptions PDF to browser
     */
    public function exportSubscriptions(array $subscriptions, array $meta = []): void
    {
        $filename = 'secure360-subscriptions-' . date('Y-m-d_His') . '.pdf';
        $pdfContent = $this->generateSubscriptionsPdf($subscriptions, $meta);
        $this->streamPdf($filename, $pdfContent);
    }

    /**
     * Generate and stream Invoices PDF report to browser
     */
    public function exportInvoices(array $invoices, array $meta = []): void
    {
        $filename = 'secure360-invoices-' . date('Y-m-d_His') . '.pdf';
        $pdfContent = $this->generateInvoicesPdf($invoices, $meta);
        $this->streamPdf($filename, $pdfContent);
    }

    /**
     * Generate and stream Operational Report PDF to browser
     */
    public function exportOperationalReport(string $reportType, array $records, array $meta = []): void
    {
        $reportTypeSlug = str_replace('_', '-', $reportType);
        $filename = 'secure360-' . $reportTypeSlug . '-report-' . date('Y-m-d_His') . '.pdf';
        $pdfContent = $this->generateOperationalReportPdf($reportType, $records, $meta);
        $this->streamPdf($filename, $pdfContent);
    }

    /**
     * Stream PDF bytes with clean buffer safety
     */
    private function streamPdf(string $filename, string $pdfContent): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        ini_set('display_errors', '0');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: max-age=0, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        echo $pdfContent;
        exit;
    }

    /**
     * Build raw PDF binary for Organisations
     */
    public function generateOrganisationsPdf(array $organizations, array $meta = []): string
    {
        $rowsPerPage = 12;
        $totalRecords = count($organizations);
        $pages = $totalRecords > 0 ? array_chunk($organizations, $rowsPerPage) : [[]];
        $totalPages = count($pages);

        $filterStatus = $meta['status_filter'] ?? 'All';
        $filterSearch = !empty($meta['search']) ? $meta['search'] : 'None';
        $generatedAt = date('d M Y, H:i:s');
        $generatedBy = $meta['generated_by'] ?? 'Superadmin';

        $pageStreams = [];

        foreach ($pages as $pageIndex => $pageRows) {
            $pageNum = $pageIndex + 1;
            $stream = [];

            // 1. Top Brand Banner Accent (Blue #2563EB)
            $stream[] = "0.145 0.388 0.921 rg 0 589 842 6 re f";

            // 2. Header: Logo & Title
            $stream[] = "BT /F2 18 Tf 0.059 0.090 0.165 rg 40 558 Td (SECURE360) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 546 Td (Enterprise Security Workforce & Tenant Management Platform) Tj ET";

            $stream[] = "BT /F2 16 Tf 0.145 0.388 0.921 rg 600 558 Td (ORGANISATIONS REPORT) Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 600 546 Td (Confidential Superadmin Audit Document) Tj ET";

            // Divider Rule
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 534 m 802 534 l S";

            // 3. Filter Summary Box
            $stream[] = "0.960 0.970 0.980 rg 40 488 762 38 re f";
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 488 762 38 re S";

            $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 55 512 Td (APPLIED FILTERS:) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.059 0.090 0.165 rg 145 512 Td (Status: ) Tj /F2 8.5 Tf (" . $this->escapePdf($filterStatus) . ") Tj /F1 8.5 Tf (   |   Search Query: ) Tj /F2 8.5 Tf (" . $this->escapePdf($filterSearch) . ") Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 55 496 Td (Total Filtered Records: ) Tj /F2 8 Tf (" . $totalRecords . ") Tj /F1 8 Tf (   |   Generated: " . $generatedAt . " by " . $this->escapePdf($generatedBy) . ") Tj ET";

            // 4. Table Header
            $tableY = 458;
            $stream[] = "0.145 0.388 0.921 rg 40 " . ($tableY - 22) . " 762 22 re f";

            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (ORGANISATION / CODE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 230 " . ($tableY - 15) . " Td (STATUS) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 310 " . ($tableY - 15) . " Td (CONTACT PERSON) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 430 " . ($tableY - 15) . " Td (ADMIN / PHONE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 570 " . ($tableY - 15) . " Td (OFFICIAL EMAIL) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 690 " . ($tableY - 15) . " Td (GUARDS) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 745 " . ($tableY - 15) . " Td (ONBOARDED) Tj ET";

            // 5. Data Rows
            $currY = $tableY - 22;
            $rowHeight = 28;

            foreach ($pageRows as $idx => $org) {
                $status = (int)($org['status'] ?? 0) === 0 ? 'Active' : 'Suspended';
                $admins = $org['admins'] ?? [];
                $primaryAdmin = !empty($admins) ? $admins[0] : null;

                $adminName = $primaryAdmin ? (string)($primaryAdmin['full_name'] ?? '') : 'No Admin';
                $adminPhone = $primaryAdmin ? (string)($primaryAdmin['phone'] ?? '—') : '—';
                $activeGuards = isset($org['active_guards']) ? (string)$org['active_guards'] : '0';
                $guardLimit = isset($org['guard_limit']) && $org['guard_limit'] !== null ? (string)$org['guard_limit'] : '—';
                $created = !empty($org['created_at']) ? date('d-M-y', strtotime($org['created_at'])) : '—';

                if ($idx % 2 === 1) {
                    $stream[] = "0.976 0.984 0.992 rg 40 " . ($currY - $rowHeight) . " 762 " . $rowHeight . " re f";
                }
                $stream[] = "0.945 0.961 0.976 RG 0.5 w 40 " . ($currY - $rowHeight) . " m 802 " . ($currY - $rowHeight) . " l S";

                $name = $this->truncateText((string)($org['name'] ?? ''), 28);
                $code = (string)($org['organization_code'] ?? '');
                $contact = $this->truncateText((string)($org['contact_person'] ?? '—'), 20);
                $email = $this->truncateText((string)($org['email'] ?? '—'), 24);

                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 48 " . ($currY - 12) . " Td (" . $this->escapePdf($name) . ") Tj ET";
                $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 48 " . ($currY - 22) . " Td (" . $this->escapePdf($code) . ") Tj ET";

                if ($status === 'Active') {
                    $stream[] = "0.925 0.992 0.961 rg 230 " . ($currY - 20) . " 55 14 re f";
                    $stream[] = "BT /F2 7.5 Tf 0.020 0.588 0.412 rg 238 " . ($currY - 14) . " Td (ACTIVE) Tj ET";
                } else {
                    $stream[] = "1 0.984 0.922 rg 230 " . ($currY - 20) . " 65 14 re f";
                    $stream[] = "BT /F2 7.5 Tf 0.706 0.325 0.035 rg 238 " . ($currY - 14) . " Td (SUSPENDED) Tj ET";
                }

                $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 310 " . ($currY - 17) . " Td (" . $this->escapePdf($contact) . ") Tj ET";
                $stream[] = "BT /F2 8 Tf 0.200 0.255 0.333 rg 430 " . ($currY - 12) . " Td (" . $this->escapePdf($this->truncateText($adminName, 22)) . ") Tj ET";
                $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 430 " . ($currY - 22) . " Td (" . $this->escapePdf($adminPhone) . ") Tj ET";
                $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 570 " . ($currY - 17) . " Td (" . $this->escapePdf($email) . ") Tj ET";
                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 690 " . ($currY - 17) . " Td (" . $this->escapePdf($activeGuards . " / " . $guardLimit) . ") Tj ET";
                $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 745 " . ($currY - 17) . " Td (" . $this->escapePdf($created) . ") Tj ET";

                $currY -= $rowHeight;
            }

            // Footer
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 40 m 802 40 l S";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 40 28 Td (Secure360 Enterprise Workforce Platform  |  Official Report) Tj ET";
            $stream[] = "BT /F2 8 Tf 0.145 0.388 0.921 rg 740 28 Td (Page " . $pageNum . " of " . $totalPages . ") Tj ET";

            $pageStreams[] = implode("\n", $stream);
        }

        return $this->compilePdf($pageStreams);
    }

    /**
     * Build raw PDF binary for Subscriptions
     */
    public function generateSubscriptionsPdf(array $subscriptions, array $meta = []): string
    {
        $rowsPerPage = 12;
        $totalRecords = count($subscriptions);
        $pages = $totalRecords > 0 ? array_chunk($subscriptions, $rowsPerPage) : [[]];
        $totalPages = count($pages);

        $filterStatus = $meta['status_filter'] ?? 'All';
        $filterSearch = !empty($meta['search']) ? $meta['search'] : 'None';
        $generatedAt = date('d M Y, H:i:s');
        $generatedBy = $meta['generated_by'] ?? 'Superadmin';

        $pageStreams = [];

        foreach ($pages as $pageIndex => $pageRows) {
            $pageNum = $pageIndex + 1;
            $stream = [];

            // 1. Top Brand Banner Accent (Blue #2563EB)
            $stream[] = "0.145 0.388 0.921 rg 0 589 842 6 re f";

            // 2. Header
            $stream[] = "BT /F2 18 Tf 0.059 0.090 0.165 rg 40 558 Td (SECURE360) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 546 Td (Platform Subscriptions & Guard Capacity Roster) Tj ET";

            $stream[] = "BT /F2 16 Tf 0.145 0.388 0.921 rg 600 558 Td (SUBSCRIPTIONS REPORT) Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 600 546 Td (Confidential Superadmin Audit Document) Tj ET";

            // Divider Rule
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 534 m 802 534 l S";

            // 3. Filter Summary Box
            $stream[] = "0.960 0.970 0.980 rg 40 488 762 38 re f";
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 488 762 38 re S";

            $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 55 512 Td (APPLIED FILTERS:) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.059 0.090 0.165 rg 145 512 Td (Status: ) Tj /F2 8.5 Tf (" . $this->escapePdf($filterStatus) . ") Tj /F1 8.5 Tf (   |   Search Query: ) Tj /F2 8.5 Tf (" . $this->escapePdf($filterSearch) . ") Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 55 496 Td (Total Subscriptions: ) Tj /F2 8 Tf (" . $totalRecords . ") Tj /F1 8 Tf (   |   Generated: " . $generatedAt . " by " . $this->escapePdf($generatedBy) . ") Tj ET";

            // 4. Table Header
            $tableY = 458;
            $stream[] = "0.145 0.388 0.921 rg 40 " . ($tableY - 22) . " 762 22 re f";

            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (ORGANISATION / CODE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 280 " . ($tableY - 15) . " Td (PLAN / TYPE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 440 " . ($tableY - 15) . " Td (GUARDS (ACTIVE / LIMIT)) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 580 " . ($tableY - 15) . " Td (START DATE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 670 " . ($tableY - 15) . " Td (EXPIRY DATE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 750 " . ($tableY - 15) . " Td (STATUS) Tj ET";

            // 5. Data Rows
            $currY = $tableY - 22;
            $rowHeight = 28;

            foreach ($pageRows as $idx => $s) {
                $statusKey = $s['calculated_status'] ?? 'active';
                $statusLabel = match ($statusKey) {
                    'active' => 'Active',
                    'expiring_soon' => 'Expiring',
                    'expired' => 'Expired',
                    'suspended' => 'Suspended',
                    default => ucfirst((string)$statusKey),
                };

                $planLabel = 'Standard Annual Plan';
                if (!empty($s['notes']) && strlen($s['notes']) <= 25 && !str_contains(strtolower($s['notes']), 'invoice')) {
                    $planLabel = $s['notes'];
                }

                $activeGuards = (int)($s['active_guards'] ?? 0);
                $guardLimit = (int)($s['guard_limit'] ?? 0);
                $startDate = !empty($s['start_date']) ? date('d-M-y', strtotime($s['start_date'])) : '—';
                $endDate = !empty($s['end_date']) ? date('d-M-y', strtotime($s['end_date'])) : '—';

                if ($idx % 2 === 1) {
                    $stream[] = "0.976 0.984 0.992 rg 40 " . ($currY - $rowHeight) . " 762 " . $rowHeight . " re f";
                }
                $stream[] = "0.945 0.961 0.976 RG 0.5 w 40 " . ($currY - $rowHeight) . " m 802 " . ($currY - $rowHeight) . " l S";

                $orgName = $this->truncateText((string)($s['organization_name'] ?? ''), 32);
                $orgCode = (string)($s['organization_code'] ?? '');

                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 48 " . ($currY - 12) . " Td (" . $this->escapePdf($orgName) . ") Tj ET";
                $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 48 " . ($currY - 22) . " Td (" . $this->escapePdf($orgCode) . ") Tj ET";

                $stream[] = "BT /F1 8.5 Tf 0.200 0.255 0.333 rg 280 " . ($currY - 17) . " Td (" . $this->escapePdf($planLabel) . ") Tj ET";
                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 440 " . ($currY - 17) . " Td (" . $this->escapePdf($activeGuards . " / " . $guardLimit . " Guards") . ") Tj ET";
                $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 580 " . ($currY - 17) . " Td (" . $this->escapePdf($startDate) . ") Tj ET";
                $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 670 " . ($currY - 17) . " Td (" . $this->escapePdf($endDate) . ") Tj ET";

                if ($statusLabel === 'Active') {
                    $stream[] = "0.925 0.992 0.961 rg 745 " . ($currY - 20) . " 50 14 re f";
                    $stream[] = "BT /F2 7.5 Tf 0.020 0.588 0.412 rg 752 " . ($currY - 14) . " Td (ACTIVE) Tj ET";
                } elseif ($statusLabel === 'Expiring') {
                    $stream[] = "1 0.984 0.922 rg 745 " . ($currY - 20) . " 50 14 re f";
                    $stream[] = "BT /F2 7.5 Tf 0.706 0.325 0.035 rg 749 " . ($currY - 14) . " Td (EXPIRING) Tj ET";
                } elseif ($statusLabel === 'Expired') {
                    $stream[] = "1 0.949 0.949 rg 745 " . ($currY - 20) . " 50 14 re f";
                    $stream[] = "BT /F2 7.5 Tf 0.862 0.149 0.149 rg 752 " . ($currY - 14) . " Td (EXPIRED) Tj ET";
                } else {
                    $stream[] = "0.945 0.961 0.976 rg 745 " . ($currY - 20) . " 50 14 re f";
                    $stream[] = "BT /F2 7.5 Tf 0.278 0.333 0.412 rg 748 " . ($currY - 14) . " Td (SUSPENDED) Tj ET";
                }

                $currY -= $rowHeight;
            }

            // Footer
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 40 m 802 40 l S";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 40 28 Td (Secure360 Enterprise Workforce Platform  |  Official Subscriptions Audit) Tj ET";
            $stream[] = "BT /F2 8 Tf 0.145 0.388 0.921 rg 740 28 Td (Page " . $pageNum . " of " . $totalPages . ") Tj ET";

            $pageStreams[] = implode("\n", $stream);
        }

        return $this->compilePdf($pageStreams);
    }

    /**
     * Build raw PDF binary for Invoices
     */
    public function generateInvoicesPdf(array $invoices, array $meta = []): string
    {
        $rowsPerPage = 12;
        $totalRecords = count($invoices);
        $pages = $totalRecords > 0 ? array_chunk($invoices, $rowsPerPage) : [[]];
        $totalPages = count($pages);

        $filterStatus = $meta['status_filter'] ?? 'All';
        $filterSearch = !empty($meta['search']) ? $meta['search'] : 'None';
        $generatedAt = date('d M Y, H:i:s');
        $generatedBy = $meta['generated_by'] ?? 'Superadmin';

        $pageStreams = [];

        foreach ($pages as $pageIndex => $pageRows) {
            $pageNum = $pageIndex + 1;
            $stream = [];

            // 1. Top Brand Banner Accent (Blue #2563EB)
            $stream[] = "0.145 0.388 0.921 rg 0 589 842 6 re f";

            // 2. Header: Logo & Title
            $stream[] = "BT /F2 18 Tf 0.059 0.090 0.165 rg 40 558 Td (SECURE360) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 546 Td (Platform Invoices & Billing Statements Audit) Tj ET";

            $stream[] = "BT /F2 16 Tf 0.145 0.388 0.921 rg 600 558 Td (INVOICES REPORT) Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 600 546 Td (Confidential Superadmin Audit Document) Tj ET";

            // Divider Rule
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 534 m 802 534 l S";

            // 3. Filter Summary Box
            $stream[] = "0.960 0.970 0.980 rg 40 488 762 38 re f";
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 488 762 38 re S";

            $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 55 512 Td (APPLIED FILTERS:) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.059 0.090 0.165 rg 145 512 Td (Status: ) Tj /F2 8.5 Tf (" . $this->escapePdf($filterStatus) . ") Tj /F1 8.5 Tf (   |   Search Query: ) Tj /F2 8.5 Tf (" . $this->escapePdf($filterSearch) . ") Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 55 496 Td (Total Invoices: ) Tj /F2 8 Tf (" . $totalRecords . ") Tj /F1 8 Tf (   |   Generated: " . $generatedAt . " by " . $this->escapePdf($generatedBy) . ") Tj ET";

            // 4. Table Header
            $tableY = 458;
            $stream[] = "0.145 0.388 0.921 rg 40 " . ($tableY - 22) . " 762 22 re f";

            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (INVOICE NUMBER) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 170 " . ($tableY - 15) . " Td (ORGANISATION) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 340 " . ($tableY - 15) . " Td (SUBSCRIPTION) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 440 " . ($tableY - 15) . " Td (INVOICE DATE) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 540 " . ($tableY - 15) . " Td (BILLING PERIOD) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 670 " . ($tableY - 15) . " Td (AMOUNT (INR)) Tj ET";
            $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 750 " . ($tableY - 15) . " Td (STATUS) Tj ET";

            // 5. Data Rows
            $currY = $tableY - 22;
            $rowHeight = 28;

            foreach ($pageRows as $idx => $inv) {
                $status = strtoupper((string)($inv['status'] ?? 'PAID'));
                $invDate = !empty($inv['invoice_date']) ? date('d-M-y', strtotime($inv['invoice_date'])) : '—';
                $startDate = !empty($inv['billing_start_date']) ? date('d-M-y', strtotime($inv['billing_start_date'])) : '—';
                $endDate = !empty($inv['billing_end_date']) ? date('d-M-y', strtotime($inv['billing_end_date'])) : '—';
                $period = "{$startDate} - {$endDate}";

                if ($idx % 2 === 1) {
                    $stream[] = "0.976 0.984 0.992 rg 40 " . ($currY - $rowHeight) . " 762 " . $rowHeight . " re f";
                }
                $stream[] = "0.945 0.961 0.976 RG 0.5 w 40 " . ($currY - $rowHeight) . " m 802 " . ($currY - $rowHeight) . " l S";

                $invNum = (string)($inv['invoice_number'] ?? '');
                $orgName = $this->truncateText((string)($inv['organization_name'] ?? ''), 26);
                $orgCode = (string)($inv['organization_code'] ?? '');
                $subRef = '#SUB-' . (int)($inv['subscription_id'] ?? 0);
                $amount = 'Rs. ' . number_format((float)($inv['total_amount'] ?? 0), 2);

                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 48 " . ($currY - 17) . " Td (" . $this->escapePdf($invNum) . ") Tj ET";
                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 170 " . ($currY - 12) . " Td (" . $this->escapePdf($orgName) . ") Tj ET";
                $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 170 " . ($currY - 22) . " Td (" . $this->escapePdf($orgCode) . ") Tj ET";

                $stream[] = "BT /F1 8.5 Tf 0.278 0.333 0.412 rg 340 " . ($currY - 17) . " Td (" . $this->escapePdf($subRef) . ") Tj ET";
                $stream[] = "BT /F1 8.5 Tf 0.278 0.333 0.412 rg 440 " . ($currY - 17) . " Td (" . $this->escapePdf($invDate) . ") Tj ET";
                $stream[] = "BT /F1 8 Tf 0.278 0.333 0.412 rg 540 " . ($currY - 17) . " Td (" . $this->escapePdf($period) . ") Tj ET";
                $stream[] = "BT /F2 8.5 Tf 0.059 0.090 0.165 rg 670 " . ($currY - 17) . " Td (" . $this->escapePdf($amount) . ") Tj ET";

                // Status Pill
                $pillColor = ($status === 'PAID') ? "0.063 0.725 0.506" : "0.700 0.300 0.100";
                $stream[] = "BT /F2 8 Tf {$pillColor} rg 750 " . ($currY - 17) . " Td (" . $this->escapePdf($status) . ") Tj ET";

                $currY -= $rowHeight;
            }

            // 6. Page Footer
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 40 m 802 40 l S";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 40 28 Td (Secure360 Enterprise Workforce Platform  |  Official Invoices Audit) Tj ET";
            $stream[] = "BT /F2 8 Tf 0.145 0.388 0.921 rg 740 28 Td (Page " . $pageNum . " of " . $totalPages . ") Tj ET";

            $pageStreams[] = implode("\n", $stream);
        }

        return $this->compilePdf($pageStreams);
    }

    /**
     * Build raw PDF binary for Operational Reports
     */
    public function generateOperationalReportPdf(string $reportType, array $records, array $meta = []): string
    {
        $rowsPerPage = 12;
        $totalRecords = count($records);
        $pages = $totalRecords > 0 ? array_chunk($records, $rowsPerPage) : [[]];
        $totalPages = count($pages);

        $reportTitle = strtoupper(str_replace('_', ' ', $reportType)) . ' REPORT';
        $presetLabel = $meta['preset_label'] ?? 'This Month';
        $fromDate = !empty($meta['from_date']) ? date('d-M-y', strtotime($meta['from_date'])) : '—';
        $toDate = !empty($meta['to_date']) ? date('d-M-y', strtotime($meta['to_date'])) : '—';
        $clientName = $this->truncateText($meta['client_name'] ?? 'All Clients', 20);
        $siteName = $this->truncateText($meta['site_name'] ?? 'All Sites', 20);
        $guardName = $this->truncateText($meta['guard_name'] ?? 'All Guards', 18);
        $statusLabel = $meta['status_label'] ?? 'All';
        $generatedAt = date('d M Y, H:i');
        $generatedBy = $meta['generated_by'] ?? 'Admin';

        $pageStreams = [];

        foreach ($pages as $pageIndex => $pageRows) {
            $pageNum = $pageIndex + 1;
            $stream = [];

            // 1. Top Brand Banner Accent (Blue #2563EB)
            $stream[] = "0.145 0.388 0.921 rg 0 589 842 6 re f";

            // 2. Header
            $stream[] = "BT /F2 18 Tf 0.059 0.090 0.165 rg 40 558 Td (SECURE360) Tj ET";
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 546 Td (Operational Reports & Workforce Analytics) Tj ET";

            $stream[] = "BT /F2 15 Tf 0.145 0.388 0.921 rg 580 558 Td (" . $this->escapePdf($reportTitle) . ") Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 580 546 Td (Confidential Management Document) Tj ET";

            // Divider Rule
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 534 m 802 534 l S";

            // 3. Filter Summary Box
            $stream[] = "0.960 0.970 0.980 rg 40 488 762 38 re f";
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 488 762 38 re S";

            $stream[] = "BT /F2 8 Tf 0.278 0.333 0.412 rg 52 512 Td (FILTERS:) Tj /F1 8 Tf ( Range: ) Tj /F2 8 Tf (" . $this->escapePdf($fromDate . " to " . $toDate . " (" . $presetLabel . ")") . ") Tj /F1 8 Tf (   |   Client: ) Tj /F2 8 Tf (" . $this->escapePdf($clientName) . ") Tj /F1 8 Tf (   |   Site: ) Tj /F2 8 Tf (" . $this->escapePdf($siteName) . ") Tj ET";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 52 496 Td (Guard: ) Tj /F2 8 Tf (" . $this->escapePdf($guardName) . ") Tj /F1 8 Tf (   |   Status: ) Tj /F2 8 Tf (" . $this->escapePdf($statusLabel) . ") Tj /F1 8 Tf (   |   Total: ) Tj /F2 8 Tf (" . $totalRecords . " records) Tj /F1 8 Tf (   |   Generated: " . $generatedAt . " by " . $this->escapePdf($generatedBy) . ") Tj ET";

            // 4. Table Header
            $tableY = 458;
            $stream[] = "0.145 0.388 0.921 rg 40 " . ($tableY - 22) . " 762 22 re f";

            switch ($reportType) {
                case 'contracts':
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (CONTRACT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 140 " . ($tableY - 15) . " Td (CLIENT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 295 " . ($tableY - 15) . " Td (DUTY SITE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 455 " . ($tableY - 15) . " Td (START) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 535 " . ($tableY - 15) . " Td (END) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 615 " . ($tableY - 15) . " Td (GUARDS) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 710 " . ($tableY - 15) . " Td (STATUS) Tj ET";
                    break;

                case 'shifts':
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (DATE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 120 " . ($tableY - 15) . " Td (SHIFT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 240 " . ($tableY - 15) . " Td (CLIENT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 370 " . ($tableY - 15) . " Td (DUTY SITE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 505 " . ($tableY - 15) . " Td (SCHEDULED) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 605 " . ($tableY - 15) . " Td (GUARD) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 725 " . ($tableY - 15) . " Td (STATUS) Tj ET";
                    break;

                case 'sites_clients':
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (DATE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 115 " . ($tableY - 15) . " Td (CLIENT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 240 " . ($tableY - 15) . " Td (DUTY SITE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 365 " . ($tableY - 15) . " Td (SITE STATUS) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 445 " . ($tableY - 15) . " Td (GUARD) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 570 " . ($tableY - 15) . " Td (SHIFT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 660 " . ($tableY - 15) . " Td (IN / OUT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 735 " . ($tableY - 15) . " Td (STATUS) Tj ET";
                    break;

                case 'guards':
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (DATE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 115 " . ($tableY - 15) . " Td (GUARD) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 230 " . ($tableY - 15) . " Td (BADGE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 295 " . ($tableY - 15) . " Td (DUTY SITE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 430 " . ($tableY - 15) . " Td (CLIENT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 560 " . ($tableY - 15) . " Td (SHIFT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 655 " . ($tableY - 15) . " Td (IN / OUT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 735 " . ($tableY - 15) . " Td (STATUS) Tj ET";
                    break;

                case 'attendance':
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (DATE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 115 " . ($tableY - 15) . " Td (GUARD) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 230 " . ($tableY - 15) . " Td (BADGE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 295 " . ($tableY - 15) . " Td (CLIENT / SITE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 475 " . ($tableY - 15) . " Td (SHIFT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 565 " . ($tableY - 15) . " Td (IN / OUT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 645 " . ($tableY - 15) . " Td (ATTENDANCE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 735 " . ($tableY - 15) . " Td (GPS) Tj ET";
                    break;

                case 'all_operations':
                default:
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 48 " . ($tableY - 15) . " Td (DATE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 115 " . ($tableY - 15) . " Td (GUARD) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 225 " . ($tableY - 15) . " Td (BADGE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 285 " . ($tableY - 15) . " Td (CLIENT / SITE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 450 " . ($tableY - 15) . " Td (SHIFT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 540 " . ($tableY - 15) . " Td (IN / OUT) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 625 " . ($tableY - 15) . " Td (GPS) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 690 " . ($tableY - 15) . " Td (ATTENDANCE) Tj ET";
                    $stream[] = "BT /F2 8.5 Tf 1 1 1 rg 752 " . ($tableY - 15) . " Td (SHIFT) Tj ET";
                    break;
            }

            // 5. Data Rows
            $currY = $tableY - 22;
            $rowHeight = 26;

            foreach ($pageRows as $idx => $r) {
                if ($idx % 2 === 1) {
                    $stream[] = "0.976 0.984 0.992 rg 40 " . ($currY - $rowHeight) . " 762 " . $rowHeight . " re f";
                }
                $stream[] = "0.945 0.961 0.976 RG 0.5 w 40 " . ($currY - $rowHeight) . " m 802 " . ($currY - $rowHeight) . " l S";

                $dateDisplay = !empty($r['date']) && $r['date'] !== '—' ? date('d-M-y', strtotime($r['date'])) : '—';

                switch ($reportType) {
                    case 'contracts':
                        $code = (string)($r['contract_code'] ?? '—');
                        $client = $this->truncateText((string)($r['customer_name'] ?? '—'), 24);
                        $site = $this->truncateText((string)($r['site_name'] ?? 'Multiple Sites'), 24);
                        $sDate = !empty($r['start_date']) ? date('d-M-y', strtotime($r['start_date'])) : '—';
                        $eDate = (!empty($r['end_date']) && $r['end_date'] !== 'Ongoing') ? date('d-M-y', strtotime($r['end_date'])) : 'Ongoing';
                        $guardsText = ($r['assigned_guards'] ?? 0) . ' / ' . ($r['guard_limit'] ?? 0);
                        $statusText = strtoupper((string)($r['status_label'] ?? 'ACTIVE'));

                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 48 " . ($currY - 16) . " Td (" . $this->escapePdf($code) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 140 " . ($currY - 16) . " Td (" . $this->escapePdf($client) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 295 " . ($currY - 16) . " Td (" . $this->escapePdf($site) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 455 " . ($currY - 16) . " Td (" . $this->escapePdf($sDate) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 535 " . ($currY - 16) . " Td (" . $this->escapePdf($eDate) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 615 " . ($currY - 16) . " Td (" . $this->escapePdf($guardsText) . ") Tj ET";

                        if ($statusText === 'ACTIVE') {
                            $stream[] = "0.925 0.992 0.961 rg 710 " . ($currY - 19) . " 55 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.020 0.588 0.412 rg 718 " . ($currY - 13) . " Td (ACTIVE) Tj ET";
                        } elseif (str_contains($statusText, 'EXPIRING')) {
                            $stream[] = "1 0.984 0.922 rg 710 " . ($currY - 19) . " 65 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.706 0.325 0.035 rg 715 " . ($currY - 13) . " Td (EXPIRING) Tj ET";
                        } else {
                            $stream[] = "0.996 0.929 0.929 rg 710 " . ($currY - 19) . " 55 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.886 0.176 0.176 rg 718 " . ($currY - 13) . " Td (EXPIRED) Tj ET";
                        }
                        break;

                    case 'shifts':
                        $sName = $this->truncateText((string)($r['shift_name'] ?? '—'), 18);
                        $client = $this->truncateText((string)($r['customer_name'] ?? '—'), 20);
                        $site = $this->truncateText((string)($r['site_name'] ?? '—'), 20);
                        $sched = (string)($r['scheduled_time'] ?? '—');
                        $gName = $this->truncateText((string)($r['guard_name'] ?? '—'), 18);
                        $st = strtoupper((string)($r['shift_status'] ?? 'COMPLETED'));

                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 48 " . ($currY - 16) . " Td (" . $this->escapePdf($dateDisplay) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 120 " . ($currY - 16) . " Td (" . $this->escapePdf($sName) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 240 " . ($currY - 16) . " Td (" . $this->escapePdf($client) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 370 " . ($currY - 16) . " Td (" . $this->escapePdf($site) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 505 " . ($currY - 16) . " Td (" . $this->escapePdf($sched) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 605 " . ($currY - 16) . " Td (" . $this->escapePdf($gName) . ") Tj ET";

                        if ($st === 'COMPLETED' || $st === 'ACTIVE') {
                            $stream[] = "0.925 0.992 0.961 rg 725 " . ($currY - 19) . " 58 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.020 0.588 0.412 rg 730 " . ($currY - 13) . " Td (" . $this->escapePdf($st) . ") Tj ET";
                        } else {
                            $stream[] = "0.996 0.929 0.929 rg 725 " . ($currY - 19) . " 58 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.886 0.176 0.176 rg 732 " . ($currY - 13) . " Td (" . $this->escapePdf($st) . ") Tj ET";
                        }
                        break;

                    case 'sites_clients':
                        $client = $this->truncateText((string)($r['customer_name'] ?? '—'), 20);
                        $site = $this->truncateText((string)($r['site_name'] ?? '—'), 20);
                        $siteSt = (string)($r['site_status_label'] ?? 'Active');
                        $gName = $this->truncateText((string)($r['guard_name'] ?? '—'), 18);
                        $sName = $this->truncateText((string)($r['shift_name'] ?? '—'), 14);
                        $inOut = ($r['check_in'] ?? '—') . ' / ' . ($r['check_out'] ?? '—');
                        $attSt = strtoupper((string)($r['attendance_status'] ?? 'ON DUTY'));

                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 48 " . ($currY - 16) . " Td (" . $this->escapePdf($dateDisplay) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 115 " . ($currY - 16) . " Td (" . $this->escapePdf($client) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 240 " . ($currY - 16) . " Td (" . $this->escapePdf($site) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 365 " . ($currY - 16) . " Td (" . $this->escapePdf($siteSt) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 445 " . ($currY - 16) . " Td (" . $this->escapePdf($gName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 570 " . ($currY - 16) . " Td (" . $this->escapePdf($sName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 660 " . ($currY - 16) . " Td (" . $this->escapePdf($inOut) . ") Tj ET";

                        if ($attSt === 'COMPLETED') {
                            $stream[] = "0.925 0.992 0.961 rg 735 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.020 0.588 0.412 rg 738 " . ($currY - 13) . " Td (COMPLETED) Tj ET";
                        } elseif ($attSt === 'ON DUTY') {
                            $stream[] = "0.937 0.965 1.000 rg 735 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.145 0.388 0.922 rg 741 " . ($currY - 13) . " Td (ON DUTY) Tj ET";
                        } else {
                            $stream[] = "0.996 0.949 0.949 rg 735 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.863 0.149 0.149 rg 738 " . ($currY - 13) . " Td (CANCELLED) Tj ET";
                        }
                        break;

                    case 'guards':
                        $gName = $this->truncateText((string)($r['guard_name'] ?? '—'), 18);
                        $badge = (string)($r['guard_badge'] ?? '—');
                        $site = $this->truncateText((string)($r['site_name'] ?? '—'), 20);
                        $client = $this->truncateText((string)($r['customer_name'] ?? '—'), 20);
                        $sName = $this->truncateText((string)($r['shift_name'] ?? '—'), 14);
                        $inOut = ($r['check_in'] ?? '—') . ' / ' . ($r['check_out'] ?? '—');
                        $attSt = strtoupper((string)($r['attendance_status'] ?? 'ON DUTY'));

                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 48 " . ($currY - 16) . " Td (" . $this->escapePdf($dateDisplay) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 115 " . ($currY - 16) . " Td (" . $this->escapePdf($gName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 230 " . ($currY - 16) . " Td (" . $this->escapePdf($badge) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 295 " . ($currY - 16) . " Td (" . $this->escapePdf($site) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 430 " . ($currY - 16) . " Td (" . $this->escapePdf($client) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 560 " . ($currY - 16) . " Td (" . $this->escapePdf($sName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 655 " . ($currY - 16) . " Td (" . $this->escapePdf($inOut) . ") Tj ET";

                        if ($attSt === 'COMPLETED') {
                            $stream[] = "0.925 0.992 0.961 rg 735 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.020 0.588 0.412 rg 738 " . ($currY - 13) . " Td (COMPLETED) Tj ET";
                        } elseif ($attSt === 'ON DUTY') {
                            $stream[] = "0.937 0.965 1.000 rg 735 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.145 0.388 0.922 rg 741 " . ($currY - 13) . " Td (ON DUTY) Tj ET";
                        } else {
                            $stream[] = "0.996 0.949 0.949 rg 735 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.863 0.149 0.149 rg 738 " . ($currY - 13) . " Td (CANCELLED) Tj ET";
                        }
                        break;

                    case 'attendance':
                        $gName = $this->truncateText((string)($r['guard_name'] ?? '—'), 18);
                        $badge = (string)($r['guard_badge'] ?? '—');
                        $clientSite = $this->truncateText(($r['customer_name'] ?? '—') . ' / ' . ($r['site_name'] ?? '—'), 26);
                        $sName = $this->truncateText((string)($r['shift_name'] ?? '—'), 14);
                        $inOut = ($r['check_in'] ?? '—') . ' / ' . ($r['check_out'] ?? '—');
                        $attSt = strtoupper((string)($r['attendance_status'] ?? 'ON DUTY'));
                        $gpsSt = (string)($r['gps_status'] ?? 'No GPS');

                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 48 " . ($currY - 16) . " Td (" . $this->escapePdf($dateDisplay) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 115 " . ($currY - 16) . " Td (" . $this->escapePdf($gName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 230 " . ($currY - 16) . " Td (" . $this->escapePdf($badge) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 295 " . ($currY - 16) . " Td (" . $this->escapePdf($clientSite) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 475 " . ($currY - 16) . " Td (" . $this->escapePdf($sName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 565 " . ($currY - 16) . " Td (" . $this->escapePdf($inOut) . ") Tj ET";

                        if ($attSt === 'COMPLETED') {
                            $stream[] = "0.925 0.992 0.961 rg 645 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.020 0.588 0.412 rg 648 " . ($currY - 13) . " Td (COMPLETED) Tj ET";
                        } elseif ($attSt === 'ON DUTY') {
                            $stream[] = "0.937 0.965 1.000 rg 645 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.145 0.388 0.922 rg 651 " . ($currY - 13) . " Td (ON DUTY) Tj ET";
                        } else {
                            $stream[] = "0.996 0.949 0.949 rg 645 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 7 Tf 0.863 0.149 0.149 rg 648 " . ($currY - 13) . " Td (CANCELLED) Tj ET";
                        }

                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 735 " . ($currY - 16) . " Td (" . $this->escapePdf($this->truncateText($gpsSt, 12)) . ") Tj ET";
                        break;

                    case 'all_operations':
                    default:
                        $gName = $this->truncateText((string)($r['guard_name'] ?? '—'), 16);
                        $badge = (string)($r['guard_badge'] ?? '—');
                        $clientSite = $this->truncateText(($r['customer_name'] ?? '—') . ' / ' . ($r['site_name'] ?? '—'), 24);
                        $sName = $this->truncateText((string)($r['shift_name'] ?? '—'), 12);
                        $inOut = ($r['check_in'] ?? '—') . ' / ' . ($r['check_out'] ?? '—');
                        $gpsSt = (string)($r['gps_status'] ?? 'No GPS');
                        $attSt = strtoupper((string)($r['attendance_status'] ?? 'ON DUTY'));
                        $shSt = strtoupper((string)($r['shift_status'] ?? 'COMPLETED'));

                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 48 " . ($currY - 16) . " Td (" . $this->escapePdf($dateDisplay) . ") Tj ET";
                        $stream[] = "BT /F2 8 Tf 0.059 0.090 0.165 rg 115 " . ($currY - 16) . " Td (" . $this->escapePdf($gName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 225 " . ($currY - 16) . " Td (" . $this->escapePdf($badge) . ") Tj ET";
                        $stream[] = "BT /F1 8 Tf 0.200 0.255 0.333 rg 285 " . ($currY - 16) . " Td (" . $this->escapePdf($clientSite) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 450 " . ($currY - 16) . " Td (" . $this->escapePdf($sName) . ") Tj ET";
                        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 540 " . ($currY - 16) . " Td (" . $this->escapePdf($inOut) . ") Tj ET";
                        $stream[] = "BT /F1 7 Tf 0.392 0.455 0.545 rg 625 " . ($currY - 16) . " Td (" . $this->escapePdf($this->truncateText($gpsSt, 10)) . ") Tj ET";

                        if ($attSt === 'COMPLETED') {
                            $stream[] = "0.925 0.992 0.961 rg 685 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 6.5 Tf 0.020 0.588 0.412 rg 688 " . ($currY - 13) . " Td (COMPLETED) Tj ET";
                        } elseif ($attSt === 'ON DUTY') {
                            $stream[] = "0.937 0.965 1.000 rg 685 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 6.5 Tf 0.145 0.388 0.922 rg 691 " . ($currY - 13) . " Td (ON DUTY) Tj ET";
                        } else {
                            $stream[] = "0.996 0.949 0.949 rg 685 " . ($currY - 19) . " 54 14 re f";
                            $stream[] = "BT /F2 6.5 Tf 0.863 0.149 0.149 rg 688 " . ($currY - 13) . " Td (CANCELLED) Tj ET";
                        }

                        $stream[] = "BT /F2 7 Tf 0.200 0.255 0.333 rg 752 " . ($currY - 16) . " Td (" . $this->escapePdf($this->truncateText($shSt, 8)) . ") Tj ET";
                        break;
                }

                $currY -= $rowHeight;
            }

            // 6. Page Footer
            $stream[] = "0.886 0.910 0.941 RG 1 w 40 40 m 802 40 l S";
            $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 40 28 Td (Secure360 Enterprise Workforce Platform  |  Official Operational Analytics) Tj ET";
            $stream[] = "BT /F2 8 Tf 0.145 0.388 0.921 rg 740 28 Td (Page " . $pageNum . " of " . $totalPages . ") Tj ET";

            $pageStreams[] = implode("\n", $stream);
        }

        return $this->compilePdf($pageStreams);
    }

    /**
     * Compile PDF objects and cross-reference table into valid PDF-1.4 binary
     */
    private function compilePdf(array $pageStreams): string
    {
        $this->buffer = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $this->offsets = [];

        $numPages = count($pageStreams);

        // Obj 1: Catalog
        $this->newObj(1);
        $this->buffer .= "<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Obj 2: Pages Collection
        $this->newObj(2);
        $kids = [];
        for ($i = 0; $i < $numPages; $i++) {
            $kids[] = (5 + ($i * 2)) . " 0 R";
        }
        $kidsStr = implode(' ', $kids);
        $this->buffer .= "<< /Type /Pages /Kids [ {$kidsStr} ] /Count {$numPages} >>\nendobj\n";

        // Obj 3: Font 1 (Helvetica)
        $this->newObj(3);
        $this->buffer .= "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Obj 4: Font 2 (Helvetica-Bold)
        $this->newObj(4);
        $this->buffer .= "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Page objects & Content stream objects
        for ($i = 0; $i < $numPages; $i++) {
            $pageObjId = 5 + ($i * 2);
            $contentObjId = $pageObjId + 1;
            $streamData = $pageStreams[$i];
            $streamLen = strlen($streamData);

            // Page object
            $this->newObj($pageObjId);
            $this->buffer .= "<< /Type /Page /Parent 2 0 R\n";
            $this->buffer .= "   /MediaBox [ 0 0 {$this->pageWidth} {$this->pageHeight} ]\n";
            $this->buffer .= "   /Contents {$contentObjId} 0 R\n";
            $this->buffer .= "   /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>\n";
            $this->buffer .= ">>\nendobj\n";

            // Content stream
            $this->newObj($contentObjId);
            $this->buffer .= "<< /Length {$streamLen} >>\n";
            $this->buffer .= "stream\n{$streamData}\nendstream\nendobj\n";
        }

        // Cross-Reference Table
        $xrefOffset = strlen($this->buffer);
        $totalObjs = 4 + ($numPages * 2);

        $this->buffer .= "xref\n";
        $this->buffer .= "0 " . ($totalObjs + 1) . "\n";
        $this->buffer .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $totalObjs; $i++) {
            $offset = $this->offsets[$i] ?? 0;
            $this->buffer .= sprintf("%010d 00000 n \n", $offset);
        }

        // Trailer
        $this->buffer .= "trailer\n";
        $this->buffer .= "<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\n";
        $this->buffer .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $this->buffer;
    }

    private function newObj(int $id): void
    {
        $this->offsets[$id] = strlen($this->buffer);
        $this->buffer .= "{$id} 0 obj\n";
    }

    private function escapePdf(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\(', $text);
        $text = str_replace(')', '\)', $text);
        $text = str_replace("\r", '', $text);
        $text = str_replace("\n", ' ', $text);
        return $text;
    }

    private function truncateText(string $text, int $maxLen): string
    {
        if (mb_strlen($text) > $maxLen) {
            return mb_substr($text, 0, $maxLen - 2) . '..';
        }
        return $text;
    }
}
