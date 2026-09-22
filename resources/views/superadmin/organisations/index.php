<?php
/**
 * Superadmin Customer Organisations Management View
 * Connected to live database with standardized search, filter tabs, and pagination.
 * Design Reference: Contracts page search + filter toolbar.
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
   Superadmin Organisations Page Table & Status Badge Styles
   ========================================================================== */
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

.sa-orgs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.sa-orgs-table th {
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

.sa-orgs-table td {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.sa-orgs-table tbody tr:hover td {
    background: #fbfcfe;
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

.sa-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.sa-status-badge.active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sa-status-badge.suspended {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.sa-btn-action {
    padding: 0.35rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.sa-btn-action:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.sa-btn-action.btn-suspend:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

.sa-btn-action.btn-activate:hover {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #059669;
}

.sa-empty-box {
    padding: 3.5rem 1.5rem;
    text-align: center;
    color: #64748b;
}

.sa-empty-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    color: #94a3b8;
}
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Tenant Organisations</h1>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Standardized Filter & Search Toolbar (Exact Contracts Reference) -->
    <div class="toolbar-card">
        <!-- Left: Status Filter Tabs -->
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

        <!-- Right: Search Input and Onboard Organisation Button -->
        <div class="toolbar-actions" style="display: flex; align-items: center; gap: 0.875rem; flex-wrap: wrap;">
            <div class="toolbar-search" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <div class="input-icon-wrapper" style="width: 320px;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="orgSearchInput" class="form-control" placeholder="Search organisations, code..." oninput="handleFilterChange()">
                </div>
                <button type="button" id="btnSearchClear" class="btn-search-clear" style="display: none;" onclick="clearSearch()">Clear</button>
            </div>

            <a href="<?= url('/superadmin/organisations/create') ?>" class="btn btn-primary" style="height: 44px; padding: 0 1.25rem; border-radius: 10px; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                <span>Onboard Organisation</span>
            </a>
        </div>
    </div>

    <!-- Organisation Table Card -->
    <div class="sa-table-card">
        <div class="sa-table-responsive">
            <table class="sa-orgs-table" id="orgsTable">
                <thead>
                    <tr>
                        <th>Tenant Code</th>
                        <th>Organisation Name</th>
                        <th>Contact Person</th>
                        <th>Email / Phone</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="orgsTableBody">
                    <?php if (empty($organizations)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="sa-empty-box">
                                    <div class="sa-empty-icon">
                                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                    </div>
                                    <div style="font-size: 0.9375rem; font-weight: 600; color: #0f172a;">No organisations registered yet</div>
                                    <div style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                                        Click "Onboard Organisation" above to provision your first customer tenant agency.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($organizations as $org): ?>
                            <?php 
                                $status = (int)($org['status'] ?? 0);
                                $statusText = $status === 0 ? '0' : '1';
                            ?>
                            <tr class="org-row" 
                                data-status="<?= e($statusText) ?>" 
                                data-search="<?= e(strtolower(($org['organization_code'] ?? '') . ' ' . ($org['name'] ?? '') . ' ' . ($org['contact_person'] ?? '') . ' ' . ($org['email'] ?? '') . ' ' . ($org['phone'] ?? '') . ' ' . ($org['address'] ?? ''))) ?>">
                                <td>
                                    <span class="sa-org-code"><?= e($org['organization_code'] ?? 'ORG') ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;"><?= e($org['name'] ?? '—') ?></div>
                                    <?php if (!empty($org['address'])): ?>
                                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.15rem; max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= e($org['address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 500; color: #334155;">
                                    <?= e($org['contact_person'] ?? '—') ?>
                                </td>
                                <td>
                                    <div style="color: #0f172a; font-weight: 500;"><?= e($org['email'] ?? '—') ?></div>
                                    <?php if (!empty($org['phone'])): ?>
                                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.15rem; font-family: monospace;"><?= e($org['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 0): ?>
                                        <span class="sa-status-badge active">
                                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="sa-status-badge suspended">
                                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;"></span>
                                            Suspended
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <a href="<?= url('/superadmin/organisations/' . $org['id'] . '/edit') ?>" class="sa-btn-action" title="Edit Organisation Details">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span>Edit</span>
                                        </a>

                                        <form method="POST" action="<?= url('/superadmin/organisations/' . $org['id'] . '/toggle-status') ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to <?= $status === 0 ? 'suspend' : 'activate' ?> this organisation?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="sa-btn-action <?= $status === 0 ? 'btn-suspend' : 'btn-activate' ?>" title="<?= $status === 0 ? 'Suspend access for this organisation' : 'Reactivate access' ?>">
                                                <span><?= $status === 0 ? 'Suspend' : 'Activate' ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- No Matching Search Results Box -->
        <div id="noMatchBox" style="display: none;" class="sa-empty-box">
            <div class="sa-empty-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div style="font-size: 0.9375rem; font-weight: 600; color: #0f172a;">No organisations match your search</div>
            <div style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Try adjusting your search terms or selecting a different status filter tab.
            </div>
        </div>

        <!-- Pagination Controls (Max 10 rows per page) -->
        <div class="table-footer-pagination" id="paginationFooter">
            <div class="pagination-info" id="paginationInfo">
                Showing 1 to <?= min(10, $totalCount) ?> of <?= $totalCount ?> records
            </div>

            <div class="pagination-controls" id="paginationControls">
                <!-- Dynamic Page Buttons -->
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const ITEMS_PER_PAGE = 10;
    let currentPage = 1;
    let currentStatus = 'all';
    let matchingRows = [];

    const allRows = Array.from(document.querySelectorAll('.org-row'));
    const searchInput = document.getElementById('orgSearchInput');
    const btnSearchClear = document.getElementById('btnSearchClear');
    const noMatchBox = document.getElementById('noMatchBox');
    const tableElement = document.getElementById('orgsTable');
    const paginationFooter = document.getElementById('paginationFooter');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationControls = document.getElementById('paginationControls');

    const countAllElem = document.getElementById('countAll');
    const countActiveElem = document.getElementById('countActive');
    const countSuspendedElem = document.getElementById('countSuspended');

    window.selectStatusFilter = function(status) {
        currentStatus = status;
        document.querySelectorAll('#orgFilterTabs .filter-tab').forEach(tab => {
            tab.classList.toggle('active', tab.getAttribute('data-status') === status);
        });
        currentPage = 1;
        applyFilters();
    };

    window.handleFilterChange = function() {
        const query = searchInput ? searchInput.value.trim() : '';
        if (btnSearchClear) {
            btnSearchClear.style.display = query !== '' ? 'inline-block' : 'none';
        }
        currentPage = 1;
        applyFilters();
    };

    window.clearSearch = function() {
        if (searchInput) {
            searchInput.value = '';
        }
        handleFilterChange();
    };

    function applyFilters() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

        // Dynamic tab counts calculation matching current search query
        let countActive = 0;
        let countSuspended = 0;

        allRows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowSearch = row.getAttribute('data-search') || '';
            const matchesQuery = (query === '' || rowSearch.includes(query));

            if (matchesQuery) {
                if (rowStatus === '0') countActive++;
                else if (rowStatus === '1') countSuspended++;
            }
        });

        if (countAllElem) countAllElem.innerText = countActive + countSuspended;
        if (countActiveElem) countActiveElem.innerText = countActive;
        if (countSuspendedElem) countSuspendedElem.innerText = countSuspended;

        matchingRows = allRows.filter(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowSearch = row.getAttribute('data-search') || '';

            const matchesStatus = (currentStatus === 'all' || rowStatus === currentStatus);
            const matchesQuery = (query === '' || rowSearch.includes(query));

            return matchesStatus && matchesQuery;
        });

        renderPage();
    }

    function renderPage() {
        const total = matchingRows.length;

        if (total === 0 && allRows.length > 0) {
            allRows.forEach(r => r.style.display = 'none');
            if (noMatchBox) noMatchBox.style.display = 'block';
            if (paginationFooter) paginationFooter.style.display = 'none';
            return;
        }

        if (noMatchBox) noMatchBox.style.display = 'none';
        if (paginationFooter) paginationFooter.style.display = 'flex';

        const totalPages = Math.ceil(total / ITEMS_PER_PAGE) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
        const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, total);

        // Hide all rows first
        allRows.forEach(r => r.style.display = 'none');

        // Show only current page matching rows
        for (let i = startIndex; i < endIndex; i++) {
            if (matchingRows[i]) {
                matchingRows[i].style.display = '';
            }
        }

        // Update info text
        if (paginationInfo) {
            paginationInfo.innerText = total > 0 
                ? `Showing ${startIndex + 1} to ${endIndex} of ${total} records`
                : `Showing 0 to 0 of 0 records`;
        }

        // Render page buttons
        renderPaginationButtons(totalPages);
    }

    function renderPaginationButtons(totalPages) {
        if (!paginationControls) return;
        paginationControls.innerHTML = '';

        if (totalPages <= 1) {
            const singlePrev = document.createElement('button');
            singlePrev.type = 'button';
            singlePrev.className = 'page-btn';
            singlePrev.innerHTML = '&lsaquo;';
            singlePrev.disabled = true;
            paginationControls.appendChild(singlePrev);

            const singlePage = document.createElement('button');
            singlePage.type = 'button';
            singlePage.className = 'page-btn active';
            singlePage.innerText = '1';
            paginationControls.appendChild(singlePage);

            const singleNext = document.createElement('button');
            singleNext.type = 'button';
            singleNext.className = 'page-btn';
            singleNext.innerHTML = '&rsaquo;';
            singleNext.disabled = true;
            paginationControls.appendChild(singleNext);
            return;
        }

        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'page-btn';
        prevBtn.innerHTML = '&lsaquo;';
        prevBtn.disabled = (currentPage === 1);
        prevBtn.title = 'Previous Page';
        prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; renderPage(); } };
        paginationControls.appendChild(prevBtn);

        // Window of max 5 pages
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let p = startPage; p <= endPage; p++) {
            const pageBtn = document.createElement('button');
            pageBtn.type = 'button';
            pageBtn.className = 'page-btn' + (p === currentPage ? ' active' : '');
            pageBtn.innerText = p;
            pageBtn.onclick = () => { currentPage = p; renderPage(); };
            paginationControls.appendChild(pageBtn);
        }

        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'page-btn';
        nextBtn.innerHTML = '&rsaquo;';
        nextBtn.disabled = (currentPage === totalPages);
        nextBtn.title = 'Next Page';
        nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; renderPage(); } };
        paginationControls.appendChild(nextBtn);
    }

    // Initialize
    matchingRows = allRows;
    applyFilters();
})();
</script>
