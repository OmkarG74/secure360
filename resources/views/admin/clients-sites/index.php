<?php
/**
 * Clients & Accounts View
 * Secure360 Enterprise SaaS UI: Circular letter avatars, unified toolbar, standardized badges, and responsive table
 */
$customers = $customers ?? [];
$sites = $sites ?? [];
$statusFilter = $statusFilter ?? 'all';
$searchQuery = $searchQuery ?? '';
$totalCount = $totalCount ?? count($customers);
$activeCount = $activeCount ?? 0;
$inactiveCount = $inactiveCount ?? 0;
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title" style="margin-bottom: 1rem;">Clients &amp; Accounts</h1>
        </div>
    </div>

    <!-- Filter & Search Toolbar with Actions Distributed Across Available Width -->
    <div class="toolbar-card">
        <!-- Left: Status Filter Tabs -->
        <div class="toolbar-left">
            <div class="filter-tabs">
                <a href="<?= url('/admin/clients-sites?status=all' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
                    All <span class="tab-count">(<?= $totalCount ?>)</span>
                </a>
                <a href="<?= url('/admin/clients-sites?status=active' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>">
                    Active <span class="tab-count">(<?= $activeCount ?>)</span>
                </a>
                <a href="<?= url('/admin/clients-sites?status=inactive' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'inactive' ? 'active' : '' ?>">
                    Inactive <span class="tab-count">(<?= $inactiveCount ?>)</span>
                </a>
            </div>
        </div>

        <!-- Right: Search Field and Register Client Button -->
        <div class="toolbar-actions" style="display: flex; align-items: center; gap: 0.875rem; flex-wrap: wrap;">
            <form method="GET" action="<?= url('/admin/clients-sites') ?>" class="toolbar-search" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <div class="input-icon-wrapper" style="width: 320px;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search clients or sites..." value="<?= e($searchQuery) ?>">
                </div>
                <?php if ($searchQuery): ?>
                    <a href="<?= url('/admin/clients-sites?status=' . $statusFilter) ?>" class="btn-search-clear">Clear</a>
                <?php endif; ?>
            </form>

            <a href="<?= url('/admin/clients/register') ?>" class="btn btn-primary" style="height: 44px; padding: 0 1.25rem; border-radius: 10px; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                Register Client
            </a>
        </div>
    </div>

    <!-- Clients Table Card -->
    <div class="table-card">
        <?php if (empty($customers)): ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; text-align: center; color: #64748b;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: #94a3b8;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <p style="font-size: 1rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No clients match your filter criteria.</p>
                <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.5rem;">Try adjusting your search query or status filter.</p>
                <a href="<?= url('/admin/clients/register') ?>" class="btn btn-outline" style="font-size: 0.8125rem;">
                    Register First Client
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="clients-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">Client / Company</th>
                            <th style="width: 18%;">Contact Person</th>
                            <th style="width: 20%;">Contact Details</th>
                            <th style="width: 20%;">Assigned Sites</th>
                            <th style="width: 8%;">Status</th>
                            <th style="width: 6%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): 
                            $initials = '';
                            $words = explode(' ', trim($c['name'] ?? 'Client'));
                            foreach (array_slice($words, 0, 2) as $w) {
                                $initials .= strtoupper(substr($w, 0, 1));
                            }
                            if (empty($initials)) $initials = 'CL';
                        ?>
                            <tr>
                                <!-- Client Avatar & Name -->
                                <td>
                                    <div class="client-info-group">
                                        <div class="client-avatar">
                                            <?= e($initials) ?>
                                        </div>
                                        <div>
                                            <a href="<?= url('/admin/clients/' . $c['id'] . '/edit') ?>" class="client-title">
                                                <?= e($c['name']) ?>
                                            </a>
                                            <div class="client-code-tag">
                                                <?= e($c['client_code']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact Person -->
                                <td style="color: #334155; font-weight: 500;">
                                    <?= e($c['contact_person'] ?? '—') ?>
                                </td>

                                <!-- Phone / Email -->
                                <td>
                                    <div style="font-weight: 500; color: #334155;"><?= e($c['phone'] ?? '—') ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;"><?= e($c['email'] ?? '') ?></div>
                                </td>

                                <!-- Sites Count & List -->
                                <td>
                                    <?php $siteCount = count($c['sites'] ?? []); ?>
                                    <?php if ($siteCount > 0): ?>
                                        <div class="sites-badge-container">
                                            <span class="badge-site-count">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                                <?= $siteCount ?> <?= $siteCount === 1 ? 'Site' : 'Sites' ?>
                                            </span>
                                            <?php foreach (array_slice($c['sites'], 0, 2) as $s): ?>
                                                <span class="badge-site-name" title="<?= e($s['site_name']) ?>">
                                                    <?= e($s['site_name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if ($siteCount > 2): ?>
                                                <span class="badge-site-more">+<?= $siteCount - 2 ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem; font-style: italic;">No active sites</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Pill -->
                                <td>
                                    <?php if ((int)$c['status'] === 0): ?>
                                        <span class="badge-status active">
                                            <span class="badge-status-dot"></span>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status inactive">
                                            <span class="badge-status-dot"></span>
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div class="action-controls">
                                        <a href="<?= url('/admin/clients/' . $c['id'] . '/edit') ?>" class="btn-action-edit">
                                            Edit
                                        </a>
                                        <form method="POST" action="<?= url('/admin/clients/' . $c['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this client?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn-action-delete" title="Deactivate Client">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Standardized Pagination Footer (Reference: Attendance Page) -->
            <?php App\Core\View::component('components/pagination', [
                'currentPage' => $currentPage ?? 1,
                'totalRecords' => $totalRecords ?? count($customers),
                'pageSize' => $pageSize ?? 10,
                'queryParams' => array_filter([
                    'status' => $statusFilter !== 'all' ? $statusFilter : null,
                    'search' => $searchQuery ?: null,
                ]),
            ]); ?>
        <?php endif; ?>
    </div>
</div>
