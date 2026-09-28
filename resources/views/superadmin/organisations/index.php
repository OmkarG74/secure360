<?php
/**
 * Superadmin Tenant Organisations Management View
 * Unified SaaS Design System:
 * - Header: Title + Subtitle on Left, "+ Onboard Organisation" on Top Right
 * - Toolbar: Single rounded white container, Left status filter tabs, Right search & export buttons
 * - Table: Clean columns (Organisation | Organisation Code | Admin / Contact | Guards | Status | Created | Action)
 * - Pagination: Standard "Showing X to Y of Z" + [ ← ] [ 1 ] [ 2 ] [ → ]
 */
$organizations = $organizations ?? [];
$totalCount = count($organizations);
$activeCount = 0;
$suspendedCount = 0;
foreach ($organizations as $org) {
    if ((int)($org['status'] ?? 0) === 0) {
        $activeCount++;
    } else {
        $suspendedCount++;
    }
}
?>

<style>
/* ==========================================================================
   Superadmin Unified Module Design System (Organisations & Subscriptions)
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

/* Code Badge */
.sa-org-code {
    font-family: monospace;
    font-size: 0.725rem;
    font-weight: 700;
    color: #0f172a;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    display: inline-block;
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
    gap: 0.25rem;
    min-width: 100px;
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

/* Confirmation Modal Backdrop */
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
</style>

<div class="page-container">
    <!-- Page Header: Title & Subtitle Left | Primary Action Top Right -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title">Tenant Organisations</h1>
        </div>
        <div>
            <a href="<?= url('/superadmin/organisations/create') ?>" class="btn btn-primary" id="btnOnboardOrganisation" style="display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 600; text-decoration: none; padding: 0 1rem; height: 38px; font-size: 0.8125rem; border-radius: 8px; white-space: nowrap; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                <span> Onboard Organisation</span>
            </a>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Unified Single Rounded White Toolbar -->
    <div class="sa-unified-toolbar">
        <!-- LEFT: Status Filter Tabs -->
        <div class="toolbar-left">
            <div class="filter-tabs" id="orgFilterTabs">
                <button type="button" class="filter-tab active" data-status="all" onclick="selectStatusFilter('all')">
                    All <span class="tab-count">(<span id="countAll"><?= $totalCount ?></span>)</span>
                </button>
                <button type="button" class="filter-tab" data-status="0" onclick="selectStatusFilter('0')">
                    Active <span class="tab-count">(<span id="countActive"><?= $activeCount ?></span>)</span>
                </button>
                <button type="button" class="filter-tab" data-status="1" onclick="selectStatusFilter('1')">
                    Suspended <span class="tab-count">(<span id="countSuspended"><?= $suspendedCount ?></span>)</span>
                </button>
            </div>
        </div>

        <!-- RIGHT: [ Search ] [ Export Excel ] [ Export PDF ] -->
        <div class="toolbar-right">
            <div class="sa-search-wrapper">
                <svg class="sa-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="orgSearchInput" class="form-control" placeholder="Search organisations, code..." value="<?= e($searchQuery ?? '') ?>" oninput="handleFilterChange()" autocomplete="off">
            </div>

            <!-- Export Excel Button -->
            <a href="<?= url('/superadmin/organisations/export-excel') ?>" id="btnExportExcel" class="sa-btn-export" title="Export filtered organisations to Excel (.xlsx)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Export Excel</span>
            </a>

            <!-- Export PDF Button -->
            <a href="<?= url('/superadmin/organisations/export-pdf') ?>" id="btnExportPdf" class="sa-btn-export" title="Export filtered organisations to PDF Report">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                <span>Export PDF</span>
            </a>
        </div>
    </div>

    <!-- Clean Organisations Data Table Card -->
    <div class="sa-table-card">
        <div class="sa-table-responsive">
            <table class="sa-data-table" id="orgsTable">
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Organisation Code</th>
                        <th>Admin / Contact</th>
                        <th>Guards</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="orgsTableBody">
                    <?php if (empty($organizations)): ?>
                        <tr>
                            <td colspan="7" style="padding: 3.5rem 1rem; text-align: center; color: #64748b;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                    <svg width="36" height="36" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
                                    <span style="font-weight: 600; font-size: 0.875rem; color: #334155;">No organisations found</span>
                                    <span style="font-size: 0.75rem; color: #94a3b8;">Try clearing your search term or select another status filter.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($organizations as $org): ?>
                            <?php 
                                $status = (int)($org['status'] ?? 0);
                                $statusText = $status === 0 ? '0' : '1';
                                $adminCount = (int)($org['admin_count'] ?? 0);
                                $adminName = !empty($org['admins'][0]['full_name']) ? $org['admins'][0]['full_name'] : (!empty($org['contact_person']) ? $org['contact_person'] : ($adminCount > 0 ? "{$adminCount} Admins" : '—'));
                                $contactEmail = $org['email'] ?: (!empty($org['admins'][0]['email']) ? $org['admins'][0]['email'] : '');
                                $contactPhone = $org['phone'] ?: (!empty($org['admins'][0]['phone']) ? $org['admins'][0]['phone'] : '');

                                $activeGuards = (int)($org['active_guards'] ?? 0);
                                $guardLimit = (int)($org['guard_limit'] ?? 0);
                                $pct = $guardLimit > 0 ? min(100, round(($activeGuards / $guardLimit) * 100)) : 0;
                            ?>
                            <tr class="org-row" 
                                data-status="<?= e($statusText) ?>" 
                                data-search="<?= e(strtolower(($org['organization_code'] ?? '') . ' ' . ($org['name'] ?? '') . ' ' . ($org['contact_person'] ?? '') . ' ' . ($org['email'] ?? '') . ' ' . ($org['phone'] ?? '') . ' ' . $adminName)) ?>">

                                <!-- 1. Organisation -->
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.875rem;">
                                        <a href="<?= url('/superadmin/organisations/' . $org['id']) ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'">
                                            <?= e($org['name'] ?? '—') ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($org['contact_person'])): ?>
                                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.15rem;">
                                            Attn: <?= e($org['contact_person']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 2. Organisation Code -->
                                <td>
                                    <span class="sa-org-code"><?= e($org['organization_code'] ?? 'ORG') ?></span>
                                </td>

                                <!-- 3. Admin / Contact -->
                                <td>
                                    <div style="font-weight: 600; color: #334155; font-size: 0.8125rem;"><?= e($adminName) ?></div>
                                    <?php if (!empty($contactEmail)): ?>
                                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.1rem;"><?= e($contactEmail) ?></div>
                                    <?php elseif (!empty($contactPhone)): ?>
                                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.1rem;"><?= e($contactPhone) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- 4. Guards -->
                                <td>
                                    <?php if ($guardLimit > 0): ?>
                                        <div class="sa-guard-usage">
                                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                                                <span style="font-weight: 700; color: <?= $activeGuards >= $guardLimit ? '#dc2626' : '#0f172a' ?>;">
                                                    <?= $activeGuards ?> / <?= $guardLimit ?>
                                                </span>
                                                <span style="font-size: 0.7rem; color: #64748b; font-weight: 500;"><?= $pct ?>%</span>
                                            </div>
                                            <div class="sa-guard-bar-bg">
                                                <div class="sa-guard-bar-fill" style="width: <?= $pct ?>%; background: <?= $activeGuards >= $guardLimit ? '#ef4444' : ($pct >= 85 ? '#f59e0b' : '#2563eb') ?>;"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.8125rem; font-weight: 600; color: #475569;">
                                            <?= $activeGuards ?> Active
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 5. Status -->
                                <td>
                                    <?php if ($status === 0): ?>
                                        <span class="sa-status-badge active">
                                            <span class="badge-dot"></span>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="sa-status-badge suspended">
                                            <span class="badge-dot"></span>
                                            Suspended
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 6. Created -->
                                <td>
                                    <span style="color: #64748b; font-size: 0.8125rem;">
                                        <?= format_date($org['created_at']) ?>
                                    </span>
                                </td>

                                <!-- 7. Action -->
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <a href="<?= url('/superadmin/organisations/' . $org['id']) ?>" class="sa-btn-action btn-view" title="View Organisation Details">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            <span>View</span>
                                        </a>

                                        <a href="<?= url('/superadmin/organisations/' . $org['id'] . '/edit') ?>" class="sa-btn-action btn-edit" title="Edit Organisation Info">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            <span>Edit</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- No Matching Filter State -->
        <div id="noMatchBox" style="display: none; padding: 3rem; text-align: center; color: #64748b;">
            <p style="font-size: 0.875rem; margin-bottom: 0.5rem; color: #0f172a; font-weight: 600;">No organisations match the selected filter or search query.</p>
            <button type="button" class="btn btn-secondary" onclick="clearAllFilters()" style="padding: 0.35rem 0.85rem; font-size: 0.75rem;">Clear Filters</button>
        </div>

        <!-- Unified Table Pagination Footer -->
        <div id="orgPaginationFooter" class="sa-pagination-footer">
            <span id="orgPaginationInfo">Showing 1 to <?= min(10, $totalCount) ?> of <?= $totalCount ?> organisations</span>
            <div id="orgPaginationControls" class="pagination-buttons"></div>
        </div>
    </div>
</div>

<!-- Confirmation Modal for Suspend / Activate Organisation -->
<div id="suspendConfirmModal" class="sa-modal-backdrop">
    <div class="sa-modal-card">
        <h3 id="modalTitle" style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
            Suspend Organisation?
        </h3>
        <p id="modalBody" style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            This will prevent organisation administrators from using the organisation until it is activated again.
        </p>
        <form method="POST" id="modalForm" action="">
            <?= csrf_field() ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeSuspendModal()" style="font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" id="modalSubmitBtn" class="btn btn-danger" style="font-weight: 600;">
                    Suspend Organisation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentStatusFilter = <?= json_encode($currentStatus ?? 'all') ?>;
let searchQuery = <?= json_encode($searchQuery ?? '') ?>;
let currentPage = 1;
const ITEMS_PER_PAGE = 10;
const allRows = Array.from(document.querySelectorAll('.org-row'));

function selectStatusFilter(status) {
    currentStatusFilter = status;
    document.querySelectorAll('#orgFilterTabs .filter-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.status === status);
    });
    currentPage = 1;
    applyFilters();
}

function handleFilterChange() {
    const input = document.getElementById('orgSearchInput');
    searchQuery = (input.value || '').trim().toLowerCase();
    currentPage = 1;
    applyFilters();
}

function clearAllFilters() {
    const input = document.getElementById('orgSearchInput');
    if (input) input.value = '';
    searchQuery = '';
    selectStatusFilter('all');
}

function updateExportLinks() {
    const excelBtn = document.getElementById('btnExportExcel');
    const pdfBtn = document.getElementById('btnExportPdf');
    const baseExcel = '<?= url('/superadmin/organisations/export-excel') ?>';
    const basePdf = '<?= url('/superadmin/organisations/export-pdf') ?>';

    const params = new URLSearchParams();
    if (currentStatusFilter !== 'all') {
        params.set('status', currentStatusFilter);
    }
    if (searchQuery !== '') {
        params.set('q', searchQuery);
    }

    const qs = params.toString() ? ('?' + params.toString()) : '';
    if (excelBtn) excelBtn.href = baseExcel + qs;
    if (pdfBtn) pdfBtn.href = basePdf + qs;
}

function updateTabCounts() {
    let countAll = 0, countActive = 0, countSuspended = 0;
    allRows.forEach(row => {
        const matchesSearch = (searchQuery === '') || (row.dataset.search && row.dataset.search.includes(searchQuery));
        if (matchesSearch) {
            countAll++;
            if (row.dataset.status === '0') countActive++;
            else if (row.dataset.status === '1') countSuspended++;
        }
    });

    const cAll = document.getElementById('countAll');
    const cActive = document.getElementById('countActive');
    const cSuspended = document.getElementById('countSuspended');

    if (cAll) cAll.innerText = countAll;
    if (cActive) cActive.innerText = countActive;
    if (cSuspended) cSuspended.innerText = countSuspended;
}

function applyFilters() {
    // 1. Update export links with active filter state
    updateExportLinks();

    // 2. Update dynamic tab counts
    updateTabCounts();

    // 3. Filter matching rows
    const matching = allRows.filter(row => {
        const matchesStatus = (currentStatusFilter === 'all') || (row.dataset.status === currentStatusFilter);
        const matchesSearch = (searchQuery === '') || (row.dataset.search && row.dataset.search.includes(searchQuery));
        return matchesStatus && matchesSearch;
    });

    const total = matching.length;
    const noMatchBox = document.getElementById('noMatchBox');
    const paginationFooter = document.getElementById('orgPaginationFooter');
    const paginationInfo = document.getElementById('orgPaginationInfo');
    const paginationControls = document.getElementById('orgPaginationControls');

    allRows.forEach(r => r.style.display = 'none');

    if (total === 0 && allRows.length > 0) {
        if (noMatchBox) noMatchBox.style.display = 'block';
        if (paginationFooter) paginationFooter.style.display = 'none';
        return;
    }

    if (noMatchBox) noMatchBox.style.display = 'none';
    if (paginationFooter) paginationFooter.style.display = 'flex';

    const totalPages = Math.ceil(total / ITEMS_PER_PAGE) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIdx = Math.min(startIdx + ITEMS_PER_PAGE, total);

    for (let i = startIdx; i < endIdx; i++) {
        if (matching[i]) matching[i].style.display = '';
    }

    if (paginationInfo) {
        paginationInfo.innerText = total > 0
            ? `Showing ${startIdx + 1} to ${endIdx} of ${total} organisations`
            : `Showing 0 to 0 of 0 organisations`;
    }

    if (paginationControls) {
        paginationControls.innerHTML = '';
        if (totalPages > 1) {
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'page-btn';
            prevBtn.innerHTML = '←';
            prevBtn.disabled = (currentPage === 1);
            prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; applyFilters(); } };
            paginationControls.appendChild(prevBtn);

            for (let p = 1; p <= totalPages; p++) {
                const pBtn = document.createElement('button');
                pBtn.type = 'button';
                pBtn.className = 'page-btn' + (p === currentPage ? ' active' : '');
                pBtn.innerText = p;
                pBtn.onclick = () => { currentPage = p; applyFilters(); };
                paginationControls.appendChild(pBtn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'page-btn';
            nextBtn.innerHTML = '→';
            nextBtn.disabled = (currentPage === totalPages);
            nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; applyFilters(); } };
            paginationControls.appendChild(nextBtn);
        }
    }
}

function openSuspendModal(orgId, orgName, currentStatus) {
    const modal = document.getElementById('suspendConfirmModal');
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    const submitBtn = document.getElementById('modalSubmitBtn');
    const form = document.getElementById('modalForm');

    form.action = '<?= url('/superadmin/organisations/') ?>' + orgId + '/toggle-status';

    if (currentStatus === 0) {
        title.innerText = 'Suspend ' + orgName + '?';
        body.innerText = 'This will prevent organisation administrators from using the organisation until it is activated again.';
        submitBtn.innerText = 'Suspend Organisation';
        submitBtn.className = 'btn btn-danger';
    } else {
        title.innerText = 'Activate ' + orgName + '?';
        body.innerText = 'This will restore operational access and normal functionality for this customer organisation.';
        submitBtn.innerText = 'Activate Organisation';
        submitBtn.className = 'btn btn-primary';
    }

    modal.style.display = 'flex';
}

function closeSuspendModal() {
    document.getElementById('suspendConfirmModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    // Initial sync of tabs based on currentStatusFilter
    if (currentStatusFilter !== 'all') {
        document.querySelectorAll('#orgFilterTabs .filter-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.status === currentStatusFilter);
        });
    }
    const searchInput = document.getElementById('orgSearchInput');
    if (searchInput && searchQuery !== '') {
        searchInput.value = searchQuery;
    }
    applyFilters();
});
</script>
