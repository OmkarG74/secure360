<?php
/**
 * Superadmin Detailed Invoice View
 * Aligned with Section 10 requirements:
 * - Header: Back to Invoices, "Invoice Details", Status badge, "Download/View PDF"
 * - 4 Clear Logical Sections using stored billing snapshot (never recalculated):
 *   1. Invoice Information (Invoice Number, Invoice Date, Status, Billing Type)
 *   2. Organisation (Organisation Name, Organisation Code, Admin Name, Contact Information)
 *   3. Subscription (Subscription Reference, Start Date, End Date, Guard Quota, Price Per Guard)
 *   4. Billing (Billed Guards, Price Per Guard, Billing Period, Subtotal, Total Amount)
 */
$invoice = $invoice ?? [];
$invoiceId = (int)($invoice['id'] ?? 0);
$primaryAdmin = $primaryAdmin ?? null;
$currency = $currency ?? 'INR';

$statusKey = strtolower((string)($invoice['status'] ?? 'paid'));
$invoiceType = $invoice['invoice_type'] ?? 'Initial Subscription';

$adminName = !empty($primaryAdmin['full_name']) ? $primaryAdmin['full_name'] : (!empty($invoice['contact_person']) ? $invoice['contact_person'] : '—');
$adminEmail = !empty($invoice['organization_email']) ? $invoice['organization_email'] : (!empty($primaryAdmin['email']) ? $primaryAdmin['email'] : '—');
$adminPhone = !empty($invoice['organization_phone']) ? $invoice['organization_phone'] : (!empty($primaryAdmin['phone']) ? $primaryAdmin['phone'] : '—');
$orgAddress = !empty($invoice['organization_address']) ? $invoice['organization_address'] : '—';

$billingStart = !empty($invoice['billing_start_date']) ? format_date($invoice['billing_start_date']) : '—';
$billingEnd = !empty($invoice['billing_end_date']) ? format_date($invoice['billing_end_date']) : '—';
$subStart = !empty($invoice['sub_start_date']) ? format_date($invoice['sub_start_date']) : '—';
$subEnd = !empty($invoice['sub_end_date']) ? format_date($invoice['sub_end_date']) : '—';
?>

<style>
/* ==========================================================================
   Superadmin Invoice Details Styling (Full-Width Dashboard Grid)
   ========================================================================== */
.inv-view-container {
    width: 100%;
    margin: 0;
}

/* 2x2 Responsive Grid */
.inv-grid-2x2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

@media (max-width: 900px) {
    .inv-grid-2x2 {
        grid-template-columns: 1fr;
    }
}

.inv-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.4rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
}

.inv-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.inv-card-title {
    font-size: 0.9125rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.inv-card-title svg {
    color: #2563eb;
    flex-shrink: 0;
}

.inv-info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.inv-info-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.8125rem;
    gap: 1rem;
}

.inv-info-label {
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}

.inv-info-val {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
    word-break: break-word;
}

.sa-org-code {
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: #0f172a;
    background: #f1f5f9;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    display: inline-block;
}

/* Status Badges */
.sa-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.sa-status-badge.paid {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sa-status-badge.paid .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}

.sa-status-badge.pending {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.sa-status-badge.pending .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #f59e0b;
}

.sa-status-badge.cancelled {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.sa-status-badge.cancelled .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
}

/* Itemized Statement Table */
.inv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    margin-top: 1rem;
}

.inv-table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-size: 0.725rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}

.inv-table td {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
</style>

<div class="page-container">
    <div class="inv-view-container">
        <!-- Back Navigation & Action Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <a href="<?= url('/superadmin/invoices') ?>" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.85rem; border-radius: 6px;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                <span>Back to Invoices</span>
            </a>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <?php if ($statusKey === 'paid'): ?>
                    <span class="sa-status-badge paid"><span class="badge-dot"></span> PAID</span>
                <?php elseif ($statusKey === 'pending'): ?>
                    <span class="sa-status-badge pending"><span class="badge-dot"></span> PENDING</span>
                <?php else: ?>
                    <span class="sa-status-badge cancelled"><span class="badge-dot"></span> <?= strtoupper($statusKey) ?></span>
                <?php endif; ?>

                <a href="<?= url('/superadmin/invoices/' . $invoiceId . '/download') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 1rem; border-radius: 6px;" target="_blank">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>View / Download PDF</span>
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div style="margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; letter-spacing: -0.02em;">
                Invoice Details
            </h1>
            <p style="font-size: 0.845rem; color: #64748b; margin: 0;">
                Official billing statement and immutable commercial snapshot for <strong style="color: #0f172a;"><?= e($invoice['invoice_number']) ?></strong>.
            </p>
        </div>

        <?php App\Core\View::component('components/alerts'); ?>

        <!-- SECTION 1 & 2: TOP ROW -->
        <div class="inv-grid-2x2">
            <!-- 1. INVOICE INFORMATION -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <h2 class="inv-card-title">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Invoice Information</span>
                    </h2>
                    <span style="font-family: monospace; font-size: 0.775rem; font-weight: 700; color: #2563eb;">
                        <?= e($invoice['invoice_number']) ?>
                    </span>
                </div>

                <div class="inv-info-list">
                    <div class="inv-info-row">
                        <span class="inv-info-label">Invoice Number</span>
                        <span class="inv-info-val" style="font-family: monospace; color: #2563eb; font-weight: 700;">
                            <?= e($invoice['invoice_number']) ?>
                        </span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Invoice Date</span>
                        <span class="inv-info-val"><?= format_date($invoice['invoice_date']) ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Status</span>
                        <span class="inv-info-val">
                            <?php if ($statusKey === 'paid'): ?>
                                <span class="sa-status-badge paid"><span class="badge-dot"></span> PAID</span>
                            <?php elseif ($statusKey === 'pending'): ?>
                                <span class="sa-status-badge pending"><span class="badge-dot"></span> PENDING</span>
                            <?php else: ?>
                                <span class="sa-status-badge cancelled"><span class="badge-dot"></span> <?= strtoupper($statusKey) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Billing Type</span>
                        <span class="inv-info-val" style="color: #0f172a; font-weight: 600;">
                            <?= e($invoiceType) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 2. ORGANISATION -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <h2 class="inv-card-title">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
                        <span>Organisation</span>
                    </h2>
                    <span class="sa-org-code"><?= e($invoice['organization_code']) ?></span>
                </div>

                <div class="inv-info-list">
                    <div class="inv-info-row">
                        <span class="inv-info-label">Organisation Name</span>
                        <span class="inv-info-val" style="font-weight: 700;">
                            <a href="<?= url('/superadmin/organisations/' . $invoice['organization_id']) ?>" style="color: #0f172a; text-decoration: none;">
                                <?= e($invoice['organization_name']) ?>
                            </a>
                        </span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Organisation Code</span>
                        <span class="inv-info-val"><span class="sa-org-code"><?= e($invoice['organization_code']) ?></span></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Admin Name</span>
                        <span class="inv-info-val"><?= e($adminName) ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Email</span>
                        <span class="inv-info-val"><?= e($adminEmail) ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Phone</span>
                        <span class="inv-info-val"><?= e($adminPhone) ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Address</span>
                        <span class="inv-info-val" style="font-size: 0.775rem; color: #64748b;"><?= e($orgAddress) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3 & 4: BOTTOM ROW -->
        <div class="inv-grid-2x2">
            <!-- 3. SUBSCRIPTION -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <h2 class="inv-card-title">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>Subscription</span>
                    </h2>
                    <a href="<?= url('/superadmin/subscriptions/' . $invoice['subscription_id']) ?>" style="font-size: 0.775rem; font-weight: 700; color: #2563eb; text-decoration: none;">
                        View Subscription &rarr;
                    </a>
                </div>

                <div class="inv-info-list">
                    <div class="inv-info-row">
                        <span class="inv-info-label">Subscription Reference</span>
                        <span class="inv-info-val">
                            <a href="<?= url('/superadmin/subscriptions/' . $invoice['subscription_id']) ?>" style="color: #2563eb; font-family: monospace; font-weight: 700; text-decoration: none;">
                                #SUB-<?= (int)$invoice['subscription_id'] ?>
                            </a>
                        </span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Subscription Start Date</span>
                        <span class="inv-info-val"><?= $subStart ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Subscription End Date</span>
                        <span class="inv-info-val"><?= $subEnd ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Guard Quota</span>
                        <span class="inv-info-val"><?= (int)($invoice['sub_guard_limit'] ?? $invoice['guard_quantity']) ?> Guards</span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Price Per Guard</span>
                        <span class="inv-info-val">₹<?= number_format((float)($invoice['sub_price_per_guard'] ?? $invoice['price_per_guard']), 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- 4. BILLING SNAPSHOT -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <h2 class="inv-card-title">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>Billing (Snapshot)</span>
                    </h2>
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                        Currency: <strong>INR (₹)</strong>
                    </span>
                </div>

                <div class="inv-info-list">
                    <div class="inv-info-row">
                        <span class="inv-info-label">Billed Guards</span>
                        <span class="inv-info-val" style="font-weight: 700;"><?= (int)$invoice['guard_quantity'] ?> Guards</span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Price Per Guard</span>
                        <span class="inv-info-val">₹<?= number_format((float)$invoice['price_per_guard'], 2) ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-label">Billing Period</span>
                        <span class="inv-info-val" style="font-size: 0.775rem;"><?= $billingStart ?> &rarr; <?= $billingEnd ?></span>
                    </div>
                    <div class="inv-info-row" style="padding-top: 0.4rem; border-top: 1px solid #f1f5f9;">
                        <span class="inv-info-label">Subtotal</span>
                        <span class="inv-info-val">₹<?= number_format((float)$invoice['subtotal'], 2) ?></span>
                    </div>
                    <div class="inv-info-row" style="padding-top: 0.4rem; border-top: 1.5px solid #0f172a; margin-top: 0.25rem;">
                        <span class="inv-info-label" style="font-weight: 700; color: #0f172a;">Total Amount</span>
                        <span class="inv-info-val" style="font-size: 1.25rem; font-weight: 800; color: #2563eb;">
                            ₹<?= number_format((float)$invoice['total_amount'], 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEMIZED INVOICE TABLE -->
        <div class="inv-card" style="margin-bottom: 2rem;">
            <div class="inv-card-header">
                <h2 class="inv-card-title">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Itemized Statement Breakdown</span>
                </h2>
                <span style="font-size: 0.75rem; color: #94a3b8;">Authoritative snapshot as billed</span>
            </div>

            <div class="sa-table-responsive">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th style="text-align: center;">Guard Quantity</th>
                            <th style="text-align: right;">Unit Rate</th>
                            <th style="text-align: right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;">
                                    <?= e($invoiceType) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                                    Billing Coverage Period: <?= $billingStart ?> &rarr; <?= $billingEnd ?>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 700; color: #0f172a;">
                                <?= (int)$invoice['guard_quantity'] ?>
                            </td>
                            <td style="text-align: right; color: #475569;">
                                ₹<?= number_format((float)$invoice['price_per_guard'], 2) ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                                ₹<?= number_format((float)$invoice['total_amount'], 2) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="padding-top: 1.25rem; margin-top: 1rem; border-top: 1px solid #f1f5f9; font-size: 0.75rem; color: #94a3b8; display: flex; justify-content: space-between; align-items: center;">
                <span>1. This invoice represents an authoritative commercial record generated by Secure360.</span>
                <span>Created on: <?= date('d M Y, H:i', strtotime($invoice['created_at'] ?? 'now')) ?></span>
            </div>
        </div>
    </div>
</div>
