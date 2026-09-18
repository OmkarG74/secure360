<?php
/**
 * Contracts & Agreements View
 * Live data from contracts table joined with customers and sites
 */
$contracts = $contracts ?? [];
$statusFilter = $statusFilter ?? 'all';
$searchQuery = $searchQuery ?? '';
$totalCount = $totalCount ?? count($contracts);
$activeCount = $activeCount ?? 0;
$inactiveCount = $inactiveCount ?? 0;
?>

<div class="page-container">
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title">Client Contracts &amp; Coverage</h1>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Filter & Search Toolbar with Actions Distributed Across Available Width -->
    <div class="toolbar-card">
        <!-- Left: Status Filter Tabs -->
        <div class="toolbar-left">
            <div class="filter-tabs">
                <a href="<?= url('/admin/contracts?status=all' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
                    All <span class="tab-count">(<?= $totalCount ?>)</span>
                </a>
                <a href="<?= url('/admin/contracts?status=active' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>">
                    Active <span class="tab-count">(<?= $activeCount ?>)</span>
                </a>
                <a href="<?= url('/admin/contracts?status=inactive' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'inactive' ? 'active' : '' ?>">
                    Inactive <span class="tab-count">(<?= $inactiveCount ?>)</span>
                </a>
            </div>
        </div>

        <!-- Right: Search Input and New Contract Button -->
        <div class="toolbar-actions" style="display: flex; align-items: center; gap: 0.875rem; flex-wrap: wrap;">
            <form method="GET" action="<?= url('/admin/contracts') ?>" class="toolbar-search" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <div class="input-icon-wrapper" style="width: 320px;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search contracts, client, site..." value="<?= e($searchQuery) ?>">
                </div>
                <?php if ($searchQuery): ?>
                    <a href="<?= url('/admin/contracts?status=' . $statusFilter) ?>" class="btn-search-clear">Clear</a>
                <?php endif; ?>
            </form>

            <a href="<?= url('/admin/contracts/create') ?>" class="btn btn-primary" style="height: 44px; padding: 0 1.25rem; border-radius: 10px; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                New Contract
            </a>
        </div>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
    <?php if (empty($contracts)): ?>
        <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: #94a3b8;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p style="font-size: 1rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No security contracts registered yet.</p>
            <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.5rem;">Create a contract to bind clients with sites and guard shifts.</p>
            <a href="<?= url('/admin/contracts/create') ?>" class="btn btn-outline" style="color: #2563eb; border-color: #2563eb; text-decoration: none; font-size: 0.8125rem;">
                Create First Contract
            </a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">Contract Code</th>
                        <th style="padding: 1rem 1.25rem;">Client / Customer</th>
                        <th style="padding: 1rem 1.25rem;">Assigned Site</th>
                        <th style="padding: 1rem 1.25rem;">Guards Required</th>
                        <th style="padding: 1rem 1.25rem;">Duration</th>
                        <th style="padding: 1rem 1.25rem;">Status</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $c): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                            <td style="padding: 1.15rem 1.25rem; font-weight: 700; color: #0f172a; font-family: monospace;">
                                <?= e($c['contract_code']) ?>
                            </td>
                            <td style="padding: 1.15rem 1.25rem;">
                                <div style="font-weight: 600; color: #0f172a;"><?= e($c['customer_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; font-family: monospace;"><?= e($c['client_code']) ?></div>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; color: #334155;">
                                <div style="font-weight: 500;"><?= e($c['site_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= e($c['site_address'] ?? '—') ?></div>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; color: #475569;">
                                <span style="display: inline-block; background: #eff6ff; color: #1e40af; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem;">
                                    <?= (int)$c['required_guard_count'] ?> Guard<?= (int)$c['required_guard_count'] > 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; color: #64748b; font-size: 0.8125rem;">
                                <?= format_date_range($c['start_date'], $c['end_date']) ?>
                            </td>
                            <td style="padding: 1.15rem 1.25rem;">
                                <?php if ((int)$c['status'] === 0): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>
                                        Expired / Terminated
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; text-align: right;">
                                <a href="<?= url('/admin/contracts/' . $c['id'] . '/edit') ?>" class="btn-action-edit" style="display: inline-flex; align-items: center; padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #2563eb; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 6px; text-decoration: none;">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Standardized Pagination Footer (Reference: Attendance Page) -->
        <?php App\Core\View::component('components/pagination', [
            'currentPage' => $currentPage ?? 1,
            'totalRecords' => $totalRecords ?? count($contracts),
            'pageSize' => $pageSize ?? 10,
            'queryParams' => array_filter([
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
                'search' => $searchQuery ?: null,
            ]),
        ]); ?>
    <?php endif; ?>
</div>
