<?php
/**
 * Guards Roster & Management View
 * Secure360 Enterprise SaaS UI: Circular letter/photo avatars, unified toolbar, status pills, search, NO CHECKBOXES
 * 
 * STRICT INVARIANT: NO CHECKBOXES OR SELECT-ALL COLUMN ON THIS TABLE.
 */
$guards = $guards ?? [];
$statusFilter = $statusFilter ?? 'all';
$searchQuery = $searchQuery ?? '';
$totalCount = $totalCount ?? count($guards);
$activeCount = $activeCount ?? 0;
$inactiveCount = $inactiveCount ?? 0;
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title">Guards</h1>
            <p class="page-header-desc">Personnel directory authorized for mobile Flutter duty and check-ins.</p>
        </div>
        <a href="<?= url('/admin/guards/setup') ?>" class="btn btn-primary" style="height: 40px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            + Setup Guard
        </a>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Filter & Search Toolbar with Actions Distributed Across Available Width -->
    <div class="toolbar-card">
        <!-- Left: Status Filter Tabs -->
        <div class="toolbar-left">
            <div class="filter-tabs">
                <a href="<?= url('/admin/guards?status=all' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
                    All <span class="tab-count">(<?= $totalCount ?>)</span>
                </a>
                <a href="<?= url('/admin/guards?status=active' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>">
                    Active <span class="tab-count">(<?= $activeCount ?>)</span>
                </a>
                <a href="<?= url('/admin/guards?status=inactive' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'inactive' ? 'active' : '' ?>">
                    Inactive <span class="tab-count">(<?= $inactiveCount ?>)</span>
                </a>
            </div>
        </div>

        <!-- Right: Search Input -->
        <div class="toolbar-actions">
            <form method="GET" action="<?= url('/admin/guards') ?>" class="toolbar-search">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <div class="input-icon-wrapper" style="width: 320px;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search guards, badge, site..." value="<?= e($searchQuery) ?>">
                </div>
                <?php if ($searchQuery): ?>
                    <a href="<?= url('/admin/guards?status=' . $statusFilter) ?>" class="btn-search-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Guards Table (STRICTLY NO CHECKBOXES) -->
    <div class="table-card">
        <?php if (empty($guards)): ?>
            <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: #94a3b8;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <p style="font-size: 1rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No security guards found matching criteria.</p>
                <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.5rem;">Provision personnel to enable mobile app logins and duty rosters.</p>
                <a href="<?= url('/admin/guards/setup') ?>" class="btn btn-outline" style="color: #2563eb; border-color: #2563eb; text-decoration: none; font-size: 0.8125rem;">
                    + Setup First Guard
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Security Guard</th>
                            <th>Badge ID</th>
                            <th>Contact Details</th>
                            <th>Assigned Post / Shift</th>
                            <th>Mobile Status</th>
                            <th>Last Login</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guards as $g): 
                            $initials = '';
                            $words = explode(' ', trim($g['full_name'] ?? 'Guard'));
                            foreach (array_slice($words, 0, 2) as $w) {
                                $initials .= strtoupper(substr($w, 0, 1));
                            }
                            if (empty($initials)) $initials = 'GD';
                        ?>
                            <tr>
                                <!-- Guard Avatar & Full Name -->
                                <td>
                                    <div class="client-info-group">
                                        <?php if (!empty($g['photo_url'])): ?>
                                            <img src="<?= url('/' . $g['photo_url']) ?>" alt="<?= e($g['full_name']) ?>" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1; flex-shrink: 0;">
                                        <?php else: ?>
                                            <div class="client-avatar">
                                                <?= e($initials) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= url('/admin/guards/' . $g['guard_id'] . '/edit') ?>" class="client-title">
                                                <?= e($g['full_name']) ?>
                                            </a>
                                            <div class="client-code-tag">
                                                Field Operator
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Badge ID -->
                                <td>
                                    <span style="display: inline-block; background: #f1f5f9; color: #1e293b; font-family: ui-monospace, monospace; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <?= e($g['employee_code'] ?? 'GRD-' . $g['guard_id']) ?>
                                    </span>
                                </td>

                                <!-- Contact Details -->
                                <td>
                                    <div style="font-weight: 500; color: #334155;"><?= e($g['phone'] ?? '—') ?></div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($g['email']) ?></div>
                                </td>

                                <!-- Assigned Post / Shift -->
                                <td>
                                    <?php if (!empty($g['site_name'])): ?>
                                        <div>
                                            <span style="font-weight: 600; color: #1e293b; font-size: 0.8125rem;">
                                                <?= e($g['site_name']) ?>
                                            </span>
                                            <?php if (!empty($g['shift_name'])): ?>
                                                <div style="font-size: 0.75rem; color: #2563eb;">
                                                    <?= e($g['shift_name']) ?> (<?= substr($g['start_time'], 0, 5) ?> - <?= substr($g['end_time'], 0, 5) ?>)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="display: inline-block; background: #f8fafc; color: #94a3b8; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px dashed #cbd5e1;">
                                            Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Pill -->
                                <td>
                                    <?php if ((int)$g['guard_status'] === 0): ?>
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

                                <!-- Last Login -->
                                <td style="font-size: 0.8125rem; color: #64748b;">
                                    <?php if (!empty($g['last_login_at'])): ?>
                                        <?= date('M j, Y g:i A', strtotime($g['last_login_at'])) ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">Never logged in</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div class="action-controls">
                                        <a href="<?= url('/admin/guards/' . $g['guard_id'] . '/edit') ?>" class="btn-action-edit">
                                            Edit
                                        </a>
                                        <form method="POST" action="<?= url('/admin/guards/' . $g['guard_id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this guard?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn-action-delete" title="Deactivate Guard">
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

            <!-- Pagination Footer -->
            <div class="table-footer">
                <div>
                    Showing <strong><?= count($guards) ?></strong> of <strong><?= $totalCount ?></strong> guards
                </div>
                <div class="pagination-controls">
                    <button class="btn-pagination" disabled>Previous</button>
                    <button class="btn-pagination" disabled>Next</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
