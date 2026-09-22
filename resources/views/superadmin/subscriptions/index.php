<?php
/**
 * Superadmin Dedicated Subscriptions Management View
 * Unified SaaS Design System:
 * - Header: Title + Subtitle on Left, "+ Add Subscription" on Top Right
 * - Toolbar: Single rounded white container, Left status filter tabs, Right search & export buttons
 * - Table: 6 clean columns (Organisation | Guards | Start Date | Expiry Date | Status | Action)
 * - Actions: View, Edit, Suspend (with confirmation modal)
 * - Pagination: Standard "Showing X to Y of Z" + [ ← ] [ 1 ] [ 2 ] [ → ]
 */
$subscriptions = $subscriptions ?? [];
$statusFilter = $statusFilter ?? 'all';
$search = $search ?? '';
$counts = $counts ?? ['all' => 0, 'active' => 0, 'expiring' => 0, 'expired' => 0, 'suspended' => 0];
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
$totalRecords = $totalRecords ?? 0;
$pageSize = $pageSize ?? 10;
$currency = $currency ?? 'INR';
?>

<style>
/* ==========================================================================
   Superadmin Unified Module Design System (Subscriptions)
   ========================================================================== */

/* Single Rounded White Toolbar */
.sa-unified-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 1.25rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}

.sa-unified-toolbar .toolbar-left {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.sa-unified-toolbar .toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex: 1;
    justify-content: flex-end;
    min-width: 0;
}

/* Filter Tabs & Blue Rounded Pills */
.filter-tabs {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    border-radius: 8px;
    padding: 0.25rem;
    gap: 0.25rem;
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

/* Unified Status Badges */
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

.sa-status-badge.active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sa-status-badge.active .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}

.sa-status-badge.expiring {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.sa-status-badge.expiring .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #f59e0b;
}

.sa-status-badge.expired {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.sa-status-badge.expired .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
}

.sa-status-badge.suspended {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.sa-status-badge.suspended .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
}

/* Guard Capacity Indicator */
.sa-guard-usage {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 120px;
    max-width: 170px;
}

.sa-guard-bar-bg {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 9999px;
    overflow: hidden;
}

.sa-guard-bar-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.3s ease;
}

/* Action Links */
.sa-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
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
    white-space: nowrap;
}

.sa-btn-action:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

.sa-btn-action.btn-view:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #2563eb;
}

.sa-btn-action.btn-edit:hover {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #16a34a;
}

.sa-btn-action.btn-suspend {
    color: #b45309;
    border-color: #fde68a;
}

.sa-btn-action.btn-suspend:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

.sa-btn-action.btn-activate {
    color: #059669;
    border-color: #a7f3d0;
}

.sa-btn-action.btn-activate:hover {
    background: #ecfdf5;
    border-color: #6ee7b7;
    color: #047857;
}

/* Pagination Buttons */
.sa-pagination-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    border-top: 1px solid #f1f5f9;
    font-size: 0.75rem;
    color: #64748b;
}

.pagination-buttons {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.page-btn {
    min-width: 32px;
    height: 32px;
    padding: 0 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.page-btn:hover:not(:disabled):not(.disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}

.page-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}

.page-btn:disabled,
.page-btn.disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
}

/* Modal styling */
.sa-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(2px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.sa-modal-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 480px;
    width: 90%;
    padding: 1.75rem;
}

/* Responsive Behaviour: keep single row on desktop, wrap cleanly on mobile */
@media (max-width: 991px) {
    .sa-unified-toolbar {
        flex-wrap: wrap;
    }
    .sa-unified-toolbar .toolbar-right {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    .sa-search-wrapper {
        max-width: 100%;
        flex: 1 1 200px;
    }
}
</style>

<div class="page-container">
    <!-- Page Header: Title & Subtitle Left | Primary Action Top Right -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title">Subscriptions</h1>
            <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Manage organisation subscriptions and guard capacity.
            </p>
        </div>
        <div>
            <a href="<?= url('/superadmin/subscriptions/create') ?>" class="btn btn-primary" id="btnAddSubscription" style="display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 600; text-decoration: none; padding: 0 1rem; height: 38px; font-size: 0.8125rem; border-radius: 8px; white-space: nowrap; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                <span>+ Add Subscription</span>
            </a>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Unified Single Rounded White Toolbar -->
    <div class="sa-unified-toolbar">
        <!-- LEFT: Status Filter Tabs -->
        <div class="toolbar-left">
            <div class="filter-tabs">
                <a href="<?= url('/superadmin/subscriptions?status=all' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                   class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
                    All <span class="tab-count">(<?= $counts['all'] ?? 0 ?>)</span>
                </a>
                <a href="<?= url('/superadmin/subscriptions?status=active' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                   class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>">
                    Active <span class="tab-count">(<?= $counts['active'] ?? 0 ?>)</span>
                </a>
                <a href="<?= url('/superadmin/subscriptions?status=expiring' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                   class="filter-tab <?= $statusFilter === 'expiring' ? 'active' : '' ?>">
                    Expiring <span class="tab-count">(<?= $counts['expiring'] ?? 0 ?>)</span>
                </a>
                <a href="<?= url('/superadmin/subscriptions?status=expired' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                   class="filter-tab <?= $statusFilter === 'expired' ? 'active' : '' ?>">
                    Expired <span class="tab-count">(<?= $counts['expired'] ?? 0 ?>)</span>
                </a>
                <a href="<?= url('/superadmin/subscriptions?status=suspended' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                   class="filter-tab <?= $statusFilter === 'suspended' ? 'active' : '' ?>">
                    Suspended <span class="tab-count">(<?= $counts['suspended'] ?? 0 ?>)</span>
                </a>
            </div>
        </div>

        <!-- RIGHT: [ Search ] [ Export Excel ] [ Export PDF ] -->
        <div class="toolbar-right">
            <form method="GET" action="<?= url('/superadmin/subscriptions') ?>" class="sa-search-wrapper" style="margin: 0;">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <svg class="sa-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" class="form-control" placeholder="Search subscriptions, organisation, code..." value="<?= e($search) ?>" autocomplete="off">
            </form>

            <!-- Export Excel Button -->
            <a href="<?= url('/superadmin/subscriptions/export-excel?status=' . urlencode($statusFilter) . ($search ? '&q=' . urlencode($search) : '')) ?>" id="btnExportExcel" class="sa-btn-export" title="Export filtered subscriptions to Excel (.xlsx)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Export Excel</span>
            </a>

            <!-- Export PDF Button -->
            <a href="<?= url('/superadmin/subscriptions/export-pdf?status=' . urlencode($statusFilter) . ($search ? '&q=' . urlencode($search) : '')) ?>" id="btnExportPdf" class="sa-btn-export" title="Export filtered subscriptions to PDF Report">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                <span>Export PDF</span>
            </a>
        </div>
    </div>

    <!-- Clean Subscriptions Data Table Card (Plan / Type removed; Only Org Name in Org column) -->
    <div class="sa-table-card">
        <div class="sa-table-responsive">
            <table class="sa-data-table">
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Guards</th>
                        <th>Start Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)): ?>
                        <tr>
                            <td colspan="6" style="padding: 3.5rem 1rem; text-align: center; color: #64748b;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                    <svg width="36" height="36" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span style="font-weight: 600; font-size: 0.875rem; color: #334155;">No subscriptions found</span>
                                    <span style="font-size: 0.75rem; color: #94a3b8;">Try selecting a different status filter or clearing your search term.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $s): ?>
                            <?php
                                $subId = (int)$s['id'];
                                $limit = (int)$s['guard_limit'];
                                $active = (int)($s['active_guards'] ?? 0);
                                $pct = $limit > 0 ? min(100, round(($active / $limit) * 100)) : 0;
                                $statusKey = $s['calculated_status'] ?? 'active';

                                // Guard bar color: neutral at 0%, blue at low, amber at high (>=80%), red at 100%
                                $barColor = '#cbd5e1';
                                if ($pct >= 100 || $active >= $limit) {
                                    $barColor = '#ef4444';
                                } elseif ($pct >= 80) {
                                    $barColor = '#f59e0b';
                                } elseif ($pct > 0) {
                                    $barColor = '#2563eb';
                                }
                            ?>
                            <tr>
                                <!-- 1. Organisation (ONLY Name displayed; Code available in View) -->
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.875rem;">
                                        <a href="<?= url('/superadmin/subscriptions/' . $subId) ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'">
                                            <?= e($s['organization_name']) ?>
                                        </a>
                                    </div>
                                </td>

                                <!-- 2. Guards (Active / Limit + Progress bar & %) -->
                                <td>
                                    <div class="sa-guard-usage">
                                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                                            <span style="font-weight: 700; color: <?= $active >= $limit ? '#dc2626' : '#0f172a' ?>;">
                                                <?= $active ?> / <?= $limit ?>
                                            </span>
                                            <span style="font-size: 0.7rem; color: #64748b; font-weight: 600;"><?= $pct ?>%</span>
                                        </div>
                                        <div class="sa-guard-bar-bg">
                                            <div class="sa-guard-bar-fill" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- 3. Start Date -->
                                <td>
                                    <span style="font-size: 0.8125rem; color: #475569;">
                                        <?= format_date($s['start_date']) ?>
                                    </span>
                                </td>

                                <!-- 4. Expiry Date -->
                                <td>
                                    <span style="font-size: 0.8125rem; font-weight: 600; color: <?= $statusKey === 'expired' ? '#dc2626' : ($statusKey === 'expiring_soon' ? '#b45309' : '#0f172a') ?>;">
                                        <?= format_date($s['end_date']) ?>
                                    </span>
                                </td>

                                <!-- 5. Status -->
                                <td>
                                    <?php if ($statusKey === 'active'): ?>
                                        <span class="sa-status-badge active">
                                            <span class="badge-dot"></span>
                                            Active
                                        </span>
                                    <?php elseif ($statusKey === 'expiring_soon'): ?>
                                        <span class="sa-status-badge expiring">
                                            <span class="badge-dot"></span>
                                            Expiring
                                        </span>
                                    <?php elseif ($statusKey === 'expired'): ?>
                                        <span class="sa-status-badge expired">
                                            <span class="badge-dot"></span>
                                            Expired
                                        </span>
                                    <?php else: ?>
                                        <span class="sa-status-badge suspended">
                                            <span class="badge-dot"></span>
                                            Suspended
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 6. Action (View, Edit, Suspend/Activate) -->
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <a href="<?= url('/superadmin/subscriptions/' . $subId) ?>" class="sa-btn-action btn-view" title="View Subscription Details">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            <span>View</span>
                                        </a>

                                        <a href="<?= url('/superadmin/subscriptions/' . $subId . '/edit') ?>" class="sa-btn-action btn-edit" title="Edit Subscription">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            <span>Edit</span>
                                        </a>

                                        <?php if ((int)$s['status'] !== 3): ?>
                                            <button type="button" class="sa-btn-action btn-suspend" onclick="openSuspendSubModal(<?= $subId ?>, '<?= e(addslashes($s['organization_name'])) ?>')" title="Suspend Subscription">
                                                <span>Suspend</span>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="sa-btn-action btn-activate" onclick="openActivateSubModal(<?= $subId ?>, '<?= e(addslashes($s['organization_name'])) ?>')" title="Activate Subscription">
                                                <span>Activate</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Unified Table Pagination Footer -->
        <div class="sa-pagination-footer">
            <span>Showing <?= $totalRecords > 0 ? (($currentPage - 1) * $pageSize + 1) : 0 ?> to <?= min($currentPage * $pageSize, $totalRecords) ?> of <?= $totalRecords ?> subscriptions</span>
            <div class="pagination-buttons">
                <?php if ($totalPages > 1): ?>
                    <a href="<?= url('/superadmin/subscriptions?status=' . $statusFilter . ($search ? '&q=' . urlencode($search) : '') . '&page=' . max(1, $currentPage - 1)) ?>" class="page-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>" style="text-decoration: none;">←</a>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?= url('/superadmin/subscriptions?status=' . $statusFilter . ($search ? '&q=' . urlencode($search) : '') . '&page=' . $p) ?>" class="page-btn <?= $p === $currentPage ? 'active' : '' ?>" style="text-decoration: none;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <a href="<?= url('/superadmin/subscriptions?status=' . $statusFilter . ($search ? '&q=' . urlencode($search) : '') . '&page=' . min($totalPages, $currentPage + 1)) ?>" class="page-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" style="text-decoration: none;">→</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal for Suspend / Activate Subscription -->
<div id="subStatusModal" class="sa-modal-backdrop">
    <div class="sa-modal-card">
        <h3 id="subModalTitle" style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
            Suspend Subscription?
        </h3>
        <p id="subModalBody" style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            Are you sure you want to suspend the subscription for this organisation?
        </p>
        <form method="POST" id="subModalForm" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= url('/superadmin/subscriptions') ?>">
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeSubModal()" style="font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" id="subModalSubmitBtn" class="btn btn-danger" style="font-weight: 600;">
                    Suspend Subscription
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSuspendSubModal(subId, orgName) {
    const modal = document.getElementById('subStatusModal');
    const title = document.getElementById('subModalTitle');
    const body = document.getElementById('subModalBody');
    const submitBtn = document.getElementById('subModalSubmitBtn');
    const form = document.getElementById('subModalForm');

    form.action = '<?= url('/superadmin/subscriptions/') ?>' + subId + '/toggle-status';
    title.innerText = 'Suspend Subscription?';
    body.innerHTML = 'Are you sure you want to suspend the subscription for <strong>"' + orgName + '"</strong>?<br><br>Suspending this subscription may restrict the organisation\'s access according to the existing subscription rules.';
    submitBtn.innerText = 'Suspend Subscription';
    submitBtn.className = 'btn btn-danger';

    modal.style.display = 'flex';
}

function openActivateSubModal(subId, orgName) {
    const modal = document.getElementById('subStatusModal');
    const title = document.getElementById('subModalTitle');
    const body = document.getElementById('subModalBody');
    const submitBtn = document.getElementById('subModalSubmitBtn');
    const form = document.getElementById('subModalForm');

    form.action = '<?= url('/superadmin/subscriptions/') ?>' + subId + '/toggle-status';
    title.innerText = 'Activate Subscription?';
    body.innerHTML = 'Are you sure you want to activate the subscription for <strong>"' + orgName + '"</strong>?<br><br>This will restore active status and normal organisation operations.';
    submitBtn.innerText = 'Activate Subscription';
    submitBtn.className = 'btn btn-primary';

    modal.style.display = 'flex';
}

function closeSubModal() {
    document.getElementById('subStatusModal').style.display = 'none';
}
</script>
