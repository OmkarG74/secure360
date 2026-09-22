<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure Core PHP PDF Invoice Generator
 * Generates valid PDF-1.4 standard invoices with clean typography, tables, and borders
 * Zero external Composer dependencies required.
 */
class PdfInvoiceService
{
    private string $buffer = '';
    private array $offsets = [];
    private int $pageWidth = 595; // A4 width in pt (approx 210mm)
    private int $pageHeight = 842; // A4 height in pt (approx 297mm)

    /**
     * Generate raw PDF binary for an invoice record
     */
    public function generate(array $invoice): string
    {
        $invoiceNumber = $invoice['invoice_number'] ?? 'INV-000000';
        $invoiceDate = date('d M Y', strtotime($invoice['invoice_date'] ?? 'now'));
        $startDate = date('d M Y', strtotime($invoice['billing_start_date'] ?? 'now'));
        $endDate = date('d M Y', strtotime($invoice['billing_end_date'] ?? 'now'));
        $orgName = $invoice['organization_name'] ?? 'Customer Organisation';
        $orgCode = $invoice['organization_code'] ?? 'ORG-000';
        $contactPerson = $invoice['contact_person'] ?? '';
        $email = $invoice['organization_email'] ?? '';
        $phone = $invoice['organization_phone'] ?? '';
        $address = $invoice['organization_address'] ?? '';
        $guards = (int)($invoice['guard_quantity'] ?? 1);
        $price = number_format((float)($invoice['price_per_guard'] ?? 0), 2);
        $total = number_format((float)($invoice['total_amount'] ?? 0), 2);
        $currency = 'INR';
        $status = strtoupper($invoice['status'] ?? 'PAID');
        $invoiceType = $invoice['invoice_type'] ?? 'Initial Subscription';
        $prevLimit = isset($invoice['previous_guard_limit']) && $invoice['previous_guard_limit'] !== null ? (int)$invoice['previous_guard_limit'] : null;
        $addGuards = isset($invoice['additional_guards']) && $invoice['additional_guards'] !== null ? (int)$invoice['additional_guards'] : null;

        // Build PDF graphics/text stream commands
        $stream = [];

        // 1. Header Banner & Branding
        // Top accent line (primary blue #2563EB: 0.145 0.388 0.921)
        $stream[] = "0.145 0.388 0.921 rg 0 836 595 6 re f";

        // Company Logo / Brand Name
        $stream[] = "BT /F2 20 Tf 0.059 0.090 0.165 rg 40 780 Td (SECURE360) Tj ET";
        $stream[] = "BT /F1 9 Tf 0.392 0.455 0.545 rg 40 766 Td (Enterprise Security Workforce & Guard Management) Tj ET";

        // "INVOICE" Title right aligned
        $stream[] = "BT /F2 22 Tf 0.145 0.388 0.921 rg 420 780 Td (INVOICE) Tj ET";

        // Status Badge Pill (Green #10B981 for PAID)
        $stream[] = "0.925 0.992 0.961 rg 420 758 75 16 re f";
        $stream[] = "0.063 0.725 0.506 RG 1 w 420 758 75 16 re S";
        $stream[] = "BT /F2 8.5 Tf 0.024 0.455 0.314 rg 438 763 Td ({$status}) Tj ET";

        // Divider Rule
        $stream[] = "0.886 0.910 0.941 RG 1 w 40 740 m 555 740 l S";

        // 2. Invoice Details Grid (Left: Bill To, Right: Invoice Meta)
        // Left: Bill To
        $stream[] = "BT /F2 9.5 Tf 0.278 0.333 0.412 rg 40 715 Td (BILLED TO:) Tj ET";
        $stream[] = "BT /F2 13 Tf 0.059 0.090 0.165 rg 40 698 Td (" . $this->escapePdf($orgName) . ") Tj ET";
        $stream[] = "BT /F1 9 Tf 0.392 0.455 0.545 rg 40 684 Td (Tenant Code: " . $this->escapePdf($orgCode) . ") Tj ET";

        $currentY = 670;
        if (!empty($contactPerson)) {
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 {$currentY} Td (Attn: " . $this->escapePdf($contactPerson) . ") Tj ET";
            $currentY -= 13;
        }
        if (!empty($email) || !empty($phone)) {
            $contactLine = trim($email . ($email && $phone ? ' | ' : '') . $phone);
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 {$currentY} Td (" . $this->escapePdf($contactLine) . ") Tj ET";
            $currentY -= 13;
        }
        if (!empty($address)) {
            $shortAddr = substr($address, 0, 60);
            $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 40 {$currentY} Td (" . $this->escapePdf($shortAddr) . ") Tj ET";
        }

        // Right: Invoice Meta Box
        $stream[] = "0.973 0.980 0.988 rg 360 645 195 90 re f";
        $stream[] = "0.886 0.910 0.941 RG 1 w 360 645 195 90 re S";

        $stream[] = "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 370 720 Td (Invoice Number:) Tj ET";
        $stream[] = "BT /F2 9.5 Tf 0.059 0.090 0.165 rg 450 720 Td (" . $this->escapePdf($invoiceNumber) . ") Tj ET";

        $stream[] = "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 370 702 Td (Invoice Type:) Tj ET";
        $stream[] = "BT /F2 8.5 Tf 0.145 0.388 0.921 rg 450 702 Td (" . $this->escapePdf($invoiceType) . ") Tj ET";

        $stream[] = "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 370 684 Td (Invoice Date:) Tj ET";
        $stream[] = "BT /F1 8.5 Tf 0.118 0.161 0.235 rg 450 684 Td (" . $this->escapePdf($invoiceDate) . ") Tj ET";

        $stream[] = "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 370 666 Td (Billing Period:) Tj ET";
        $stream[] = "BT /F1 8 Tf 0.118 0.161 0.235 rg 450 666 Td (" . $this->escapePdf($startDate . " - " . $endDate) . ") Tj ET";

        $stream[] = "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 370 650 Td (Payment Terms:) Tj ET";
        $stream[] = "BT /F1 8.5 Tf 0.063 0.725 0.506 rg 450 650 Td (Prepaid / Full) Tj ET";

        // 3. Itemized Table
        $tableY = 600;

        // Table Header row
        $stream[] = "0.945 0.961 0.976 rg 40 {$tableY} 515 24 re f";
        $stream[] = "0.886 0.910 0.941 RG 1 w 40 {$tableY} 515 24 re S";

        $headerTextY = $tableY + 8;
        $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 50 {$headerTextY} Td (ITEM / DESCRIPTION) Tj ET";
        $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 310 {$headerTextY} Td (GUARDS) Tj ET";
        $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 380 {$headerTextY} Td (PRICE / GUARD) Tj ET";
        $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 480 {$headerTextY} Td (TOTAL (INR)) Tj ET";

        // Table Item row
        $rowY = $tableY - 45;
        $stream[] = "1.0 1.0 1.0 rg 40 {$rowY} 515 45 re f";
        $stream[] = "0.886 0.910 0.941 RG 1 w 40 {$rowY} 515 45 re S";

        // Description text based on invoice type
        if ($invoiceType === 'Guard Capacity Increase') {
            $itemDesc = "Additional Guard Capacity Upgrade (+{$guards} Guards)";
            $subDesc = "Capacity Transition: " . ($prevLimit ?? ($guards > 0 ? $guards : 0)) . " -> " . (($prevLimit ?? 0) + $guards) . " Guards";
        } elseif ($invoiceType === 'Subscription Extension') {
            $itemDesc = "Subscription Duration Extension";
            $subDesc = "Extension Period: {$startDate} to {$endDate} ({$guards} licensed guards)";
        } elseif ($invoiceType === 'Subscription Renewal') {
            $itemDesc = "Subscription License Renewal";
            $subDesc = "Coverage Renewal: {$startDate} to {$endDate} ({$guards} licensed guards)";
        } else {
            $itemDesc = "Organisation Guard Licensing Subscription";
            $subDesc = "Coverage Period: {$startDate} to {$endDate}";
        }

        $stream[] = "BT /F2 10 Tf 0.059 0.090 0.165 rg 50 " . ($rowY + 26) . " Td (" . $this->escapePdf($itemDesc) . ") Tj ET";
        $stream[] = "BT /F1 8 Tf 0.392 0.455 0.545 rg 50 " . ($rowY + 12) . " Td (" . $this->escapePdf($subDesc) . ") Tj ET";

        // Quantity (Guards)
        $stream[] = "BT /F2 9.5 Tf 0.118 0.161 0.235 rg 325 " . ($rowY + 20) . " Td ({$guards}) Tj ET";

        // Unit Price
        $stream[] = "BT /F1 9.5 Tf 0.118 0.161 0.235 rg 385 " . ($rowY + 20) . " Td (Rs. {$price}) Tj ET";

        // Line Total
        $stream[] = "BT /F2 10.5 Tf 0.059 0.090 0.165 rg 480 " . ($rowY + 20) . " Td (Rs. {$total}) Tj ET";

        // 4. Totals Calculation Summary
        $summaryY = $rowY - 85;

        // Total Box container
        $stream[] = "0.973 0.980 0.988 rg 340 {$summaryY} 215 75 re f";
        $stream[] = "0.886 0.910 0.941 RG 1 w 340 {$summaryY} 215 75 re S";

        $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 355 " . ($summaryY + 54) . " Td (Subtotal:) Tj ET";
        $stream[] = "BT /F1 8.5 Tf 0.118 0.161 0.235 rg 465 " . ($summaryY + 54) . " Td (Rs. {$total}) Tj ET";

        $stream[] = "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 355 " . ($summaryY + 36) . " Td (Applicable Taxes (0%):) Tj ET";
        $stream[] = "BT /F1 8.5 Tf 0.118 0.161 0.235 rg 465 " . ($summaryY + 36) . " Td (Rs. 0.00) Tj ET";

        // Highlighted Total Row
        $stream[] = "0.145 0.388 0.921 rg 340 {$summaryY} 215 24 re f";
        $stream[] = "BT /F2 10.5 Tf 1.0 1.0 1.0 rg 355 " . ($summaryY + 8) . " Td (TOTAL AMOUNT PAID:) Tj ET";
        $stream[] = "BT /F2 11 Tf 1.0 1.0 1.0 rg 460 " . ($summaryY + 8) . " Td (Rs. {$total}) Tj ET";

        // 5. Notes & Legal Footer
        $stream[] = "0.886 0.910 0.941 RG 1 w 40 130 m 555 130 l S";

        $stream[] = "BT /F2 8.5 Tf 0.278 0.333 0.412 rg 40 112 Td (TERMS & CONDITIONS) Tj ET";
        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 40 98 Td (1. This is a computer-generated tax invoice and license confirmation issued by Secure360 Platform.) Tj ET";
        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 40 86 Td (2. The licensed active guard limit is strictly authoritative and enforced on mobile app telemetry & rosters.) Tj ET";
        $stream[] = "BT /F1 7.5 Tf 0.392 0.455 0.545 rg 40 74 Td (3. For enterprise renewals or capacity upgrades, please contact your Secure360 platform administrator.) Tj ET";

        $stream[] = "BT /F2 8 Tf 0.145 0.388 0.921 rg 210 40 Td (Secure360 Workforce OS - www.secure360.internal) Tj ET";

        $content = implode("\n", $stream);
        return $this->compilePdf($content);
    }

    /**
     * Escape characters for PDF string literal
     */
    private function escapePdf(string $text): string
    {
        // Replace non-ascii with closest ascii
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /**
     * Compile standard PDF-1.4 binary structure
     */
    private function compilePdf(string $contentStream): string
    {
        $this->buffer = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $this->offsets = [];

        // Object 1: Catalog
        $this->newObject(1);
        $this->write("<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");

        // Object 2: Pages
        $this->newObject(2);
        $this->write("<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n");

        // Object 3: Page
        $this->newObject(3);
        $this->write(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] " .
            "/Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n"
        );

        // Object 4: Stream Content
        $streamLen = strlen($contentStream);
        $this->newObject(4);
        $this->write("<< /Length {$streamLen} >>\nstream\n{$contentStream}\nendstream\nendobj\n");

        // Object 5: Font F1 (Helvetica Normal)
        $this->newObject(5);
        $this->write("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /StandardEncoding >>\nendobj\n");

        // Object 6: Font F2 (Helvetica-Bold)
        $this->newObject(6);
        $this->write("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /StandardEncoding >>\nendobj\n");

        // Cross-reference table
        $startXref = strlen($this->buffer);
        $this->write("xref\n0 7\n0000000000 65535 f \n");

        for ($i = 1; $i <= 6; $i++) {
            $offset = $this->offsets[$i] ?? 0;
            $this->write(sprintf("%010d 00000 n \n", $offset));
        }

        // Trailer
        $this->write(
            "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$startXref}\n%%EOF"
        );

        return $this->buffer;
    }

    private function newObject(int $objId): void
    {
        $this->offsets[$objId] = strlen($this->buffer);
        $this->write("{$objId} 0 obj\n");
    }

    private function write(string $data): void
    {
        $this->buffer .= $data;
    }
}
