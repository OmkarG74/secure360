<?php
/**
 * Superadmin Dedicated Invoices Registry View
 * Unified SaaS Admin Portal Layout:
 * - Header: "Invoices" with subtitle "Manage subscription invoices and billing records."
 * - Single Rounded White Toolbar:
 *   Left: Filter pills [ All (X) ] [ Paid (X) ] [ Pending (X) ] [ Cancelled (X) ]
 *   Right: Search input (no clear button), [ Export Excel ], [ Export PDF ]
 * - Clean Data Table:
 *   Columns: Invoice Number, Organisation, Subscription, Invoice Date, Billing Period, Amount, Status, Action
 *   Actions: [ View ], [ PDF ]
 * - Standard Pagination Footer
 */
$invoices = $invoices ?? [];
$statusFilter = $statusFilter ?? 'all';
$search = $search ?? '';
$counts = $counts ?? ['all' => 0, 'paid' => 0, 'pending' => 0, 'cancelled' => 0];
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
$totalRecords = $totalRecords ?? 0;
$pageSize = $pageSize ?? 10;
$currency = $currency ?? 'INR';
?>

<style>
/* ==========================================================================
   Superadmin Invoices Styling (Harmonized with Organisations & Subscriptions)
   ========================================================================== */
.sa-toolbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.65rem 0.85rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.sa-filter-tabs {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    flex-shrink: 0;
}

.filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 6px;
    font-size: 0.775rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    border: none;
    background: transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    font-family: inherit;
    line-height: 1.2;
}

.filter-tab:hover {
    color: #0f172a;
}

.filter-tab.active {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
}

.filter-tab.active .tab-count {
    color: #dbeafe;
}

.filter-tab .tab-count {
    font-weight: 500;
    color: #94a3b8;
}

/* Flexible Search Wrapper (No Clear button beside input) */
.sa-search-wrapper {
    position: relative;
    width: 100%;
    max-width: 320px;
    min-width: 180px;
    flex-shrink: 1;
}

.sa-search-wrapper .form-control {
    width: 100%;
    padding-left: 2.25rem;
    padding-right: 0.75rem;
    height: 38px;
    font-size: 0.8125rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.sa-search-wrapper .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.sa-search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
    display: flex;
    align-items: center;
}

/* Standard Export Buttons */
.sa-btn-export {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.45rem 0.75rem;
    height: 38px;
    text-decoration: none;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    border-radius: 8px;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all 0.15s ease;
    box-sizing: border-box;
}

.sa-btn-export:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

/* Table Card & Data Table */
.sa-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.sa-table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.sa-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.sa-data-table th {
    background: #f8fafc;
    padding: 0.875rem 1.15rem;
    font-weight: 600;
    color: #475569;
    font-size: 0.725rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.sa-data-table td {
    padding: 0.95rem 1.15rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.sa-data-table tbody tr:hover td {
    background: #fbfcfe;
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

/* Action Buttons */
.sa-actions-cell {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
    white-space: nowrap;
}

.sa-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.2;
}

.sa-action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

.sa-action-btn.btn-view:hover {
    color: #2563eb;
    border-color: #bfdbfe;
    background: #eff6ff;
}

.sa-action-btn.btn-pdf:hover {
    color: #b45309;
    border-color: #fde68a;
    background: #fffbeb;
}
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <h1 class="page-header-title" style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; letter-spacing: -0.02em;">
            Invoices
        </h1>
        <p class="page-header-desc" style="font-size: 0.845rem; color: #64748b; margin: 0;">
            Manage subscription invoices and billing records.
        </p>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Standard Single Rounded White Toolbar -->
    <div class="sa-toolbar">
        <!-- Left: Status Filter Tabs -->
        <div class="sa-filter-tabs">
            <a href="<?= url('/superadmin/invoices?status=all' . ($search ? '&q=' . urlencode($search) : '')) ?>" class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
                All <span class="tab-count">(<?= $counts['all'] ?? 0 ?>)</span>
            </a>
            <a href="<?= url('/superadmin/invoices?status=paid' . ($search ? '&q=' . urlencode($search) : '')) ?>" class="filter-tab <?= $statusFilter === 'paid' ? 'active' : '' ?>">
                Paid <span class="tab-count">(<?= $counts['paid'] ?? 0 ?>)</span>
            </a>
            <a href="<?= url('/superadmin/invoices?status=pending' . ($search ? '&q=' . urlencode($search) : '')) ?>" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                Pending <span class="tab-count">(<?= $counts['pending'] ?? 0 ?>)</span>
            </a>
            <?php if (!empty($counts['cancelled'])): ?>
                <a href="<?= url('/superadmin/invoices?status=cancelled' . ($search ? '&q=' . urlencode($search) : '')) ?>" class="filter-tab <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">
                    Cancelled <span class="tab-count">(<?= $counts['cancelled'] ?? 0 ?>)</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Right: Search Input + Export Actions -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <form method="GET" action="<?= url('/superadmin/invoices') ?>" style="display: flex; align-items: center; margin: 0;">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <div class="sa-search-wrapper">
                    <span class="sa-search-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="q" class="form-control" placeholder="Search invoices, organisation, invoice number..." value="<?= e($search) ?>" autocomplete="off">
                </div>
            </form>

            <a href="<?= url('/superadmin/invoices/export-excel?status=' . urlencode($statusFilter) . ($search ? '&q=' . urlencode($search) : '')) ?>" class="sa-btn-export" title="Export matching records to Excel (.xlsx)">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export Excel</span>
            </a>

            <a href="<?= url('/superadmin/invoices/export-pdf?status=' . urlencode($statusFilter) . ($search ? '&q=' . urlencode($search) : '')) ?>" class="sa-btn-export" title="Export matching records to PDF report">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export PDF</span>
            </a>
        </div>
    </div>

    <!-- Invoices Clean Data Table -->
    <div class="sa-table-card">
        <div class="sa-table-responsive">
            <table class="sa-data-table">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Organisation</th>
                        <th>Subscription</th>
                        <th>Invoice Date</th>
                        <th>Billing Period</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="8" style="padding: 3.5rem 1rem; text-align: center; color: #94a3b8;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                    <svg width="32" height="32" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span style="font-size: 0.875rem; font-weight: 600; color: #64748b;">No invoices found matching criteria.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <?php
                                $statusKey = strtolower((string)($inv['status'] ?? 'paid'));
                                $invDate = !empty($inv['invoice_date']) ? format_date($inv['invoice_date']) : '—';
                                $startDate = !empty($inv['billing_start_date']) ? format_date($inv['billing_start_date']) : '—';
                                $endDate = !empty($inv['billing_end_date']) ? format_date($inv['billing_end_date']) : '—';
                                $billingPeriod = "{$startDate} &rarr; {$endDate}";
                            ?>
                            <tr>
                                <!-- Invoice Number -->
                                <td style="font-family: monospace; font-weight: 700;">
                                    <a href="<?= url('/superadmin/invoices/' . $inv['id']) ?>" style="color: #2563eb; text-decoration: none;">
                                        <?= e($inv['invoice_number']) ?>
                                    </a>
                                </td>

                                <!-- Organisation -->
                                <td>
                                    <span style="font-weight: 600; color: #0f172a;">
                                        <?= e($inv['organization_name']) ?>
                                    </span>
                                </td>

                                <!-- Subscription -->
                                <td>
                                    <a href="<?= url('/superadmin/subscriptions/' . $inv['subscription_id']) ?>" style="font-family: monospace; font-weight: 600; color: #475569; text-decoration: none;">
                                        #SUB-<?= (int)$inv['subscription_id'] ?>
                                    </a>
                                </td>

                                <!-- Invoice Date -->
                                <td style="color: #475569; font-size: 0.8125rem;">
                                    <?= $invDate ?>
                                </td>

                                <!-- Billing Period -->
                                <td style="color: #64748b; font-size: 0.775rem;">
                                    <?= $billingPeriod ?>
                                </td>

                                <!-- Amount -->
                                <td>
                                    <span style="font-weight: 700; color: #0f172a; font-size: 0.875rem;">
                                        ₹<?= number_format((float)$inv['total_amount'], 2) ?>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td>
                                    <?php if ($statusKey === 'paid'): ?>
                                        <span class="sa-status-badge paid"><span class="badge-dot"></span> PAID</span>
                                    <?php elseif ($statusKey === 'pending'): ?>
                                        <span class="sa-status-badge pending"><span class="badge-dot"></span> PENDING</span>
                                    <?php else: ?>
                                        <span class="sa-status-badge cancelled"><span class="badge-dot"></span> <?= strtoupper($statusKey) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td style="text-align: right;">
                                    <div class="sa-actions-cell">
                                        <a href="<?= url('/superadmin/invoices/' . $inv['id']) ?>" class="sa-action-btn btn-view" title="View Details">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span>View</span>
                                        </a>
                                        <a href="<?= url('/superadmin/invoices/' . $inv['id'] . '/download') ?>" class="sa-action-btn btn-pdf" title="View/Download PDF Statement" target="_blank">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>PDF</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.875rem 1.25rem; border-top: 1px solid #f1f5f9; font-size: 0.775rem; color: #64748b;">
            <span>Showing <?= $totalRecords > 0 ? (($currentPage - 1) * $pageSize + 1) : 0 ?> to <?= min($currentPage * $pageSize, $totalRecords) ?> of <?= $totalRecords ?> invoices</span>
            <div style="display: flex; gap: 0.25rem;">
                <?php if ($totalPages > 1): ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?= url('/superadmin/invoices?status=' . $statusFilter . ($search ? '&q=' . urlencode($search) : '') . '&page=' . $p) ?>" class="filter-tab <?= $p === $currentPage ? 'active' : '' ?>" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; text-decoration: none;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
