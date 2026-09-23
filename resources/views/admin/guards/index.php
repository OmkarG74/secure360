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
$assignedCount = $assignedCount ?? 0;
$onDutyCount = $onDutyCount ?? 0;
?>

<?php
$sub = $subDetails ?? null;
$guardLimit = (int)($sub['guard_limit'] ?? 30);
$activeGuardsCount = (int)($sub['active_guards'] ?? $activeCount);
$isLimitReached = $sub['is_limit_reached'] ?? ($activeGuardsCount >= $guardLimit);
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <h1 class="page-header-title" style="margin: 0;">Guards</h1>
            <span class="badge-guard-usage" style="font-size: 0.8125rem; font-weight: 700; color: <?= $isLimitReached ? '#dc2626' : '#2563eb' ?>; background: <?= $isLimitReached ? '#fef2f2' : '#eff6ff' ?>; border: 1px solid <?= $isLimitReached ? '#fecaca' : '#bfdbfe' ?>; padding: 0.25rem 0.65rem; border-radius: 9999px;">
                <?= $activeGuardsCount ?> / <?= $guardLimit ?>
            </span>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <?php if ($isLimitReached): ?>
        <div style="padding: 0.875rem 1.25rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <svg width="18" height="18" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="font-size: 0.8125rem; color: #991b1b; font-weight: 600;">
                Guard limit reached. Your organisation's subscription allows up to <?= $guardLimit ?> active guards. Contact Superadmin to upgrade your capacity.
            </span>
        </div>
    <?php endif; ?>

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
                <a href="<?= url('/admin/guards?status=assigned' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'assigned' ? 'active' : '' ?>">
                    Assigned <span class="tab-count">(<?= $assignedCount ?>)</span>
                </a>
                <a href="<?= url('/admin/guards?status=on_duty' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-tab <?= $statusFilter === 'on_duty' ? 'active' : '' ?>">
                    On Duty <span class="tab-count">(<?= $onDutyCount ?>)</span>
                </a>
            </div>
        </div>

        <!-- Right: Search Input and Setup Guard Button -->
        <div class="toolbar-actions" style="display: flex; align-items: center; gap: 0.875rem; flex-wrap: wrap;">
            <form method="GET" action="<?= url('/admin/guards') ?>" class="toolbar-search" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <div class="input-icon-wrapper" style="width: 320px;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search guards, badge, site..." value="<?= e($searchQuery) ?>">
                </div>
                <?php if ($searchQuery): ?>
                    <a href="<?= url('/admin/guards?status=' . $statusFilter) ?>" class="btn-search-clear">Clear</a>
                <?php endif; ?>
            </form>

            <?php if ($isLimitReached): ?>
                <button type="button" class="btn" style="height: 44px; padding: 0 1.25rem; border-radius: 10px; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; background: #e2e8f0; color: #94a3b8; cursor: not-allowed; border: none;" title="Guard limit reached (<?= $activeGuardsCount ?> / <?= $guardLimit ?> guards). Upgrade subscription to add more." disabled>
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Setup Guard</span>
                </button>
            <?php else: ?>
                <a href="<?= url('/admin/guards/setup') ?>" class="btn btn-primary" style="height: 44px; padding: 0 1.25rem; border-radius: 10px; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Setup Guard</span>
                </a>
            <?php endif; ?>
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
                    Setup First Guard
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
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem; align-items: flex-start;">
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

                                        <?php if (!empty($g['is_on_duty'])): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.7rem; font-weight: 700; color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
                                                On Duty
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Last Login -->
                                <td style="font-size: 0.8125rem; color: #64748b;">
                                    <?php if (!empty($g['last_login_at'])): ?>
                                        <?= format_datetime($g['last_login_at']) ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">Never logged in</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div class="action-controls">
                                        <?php if ((int)$g['guard_status'] === 0): ?>
                                            <button type="button" 
                                                    class="btn-action-edit" 
                                                    style="color: #dc2626; border-color: #fca5a5; background: #fff5f5; display: inline-flex; align-items: center; gap: 0.25rem;"
                                                    title="Dispatch urgent Wake-Up Call"
                                                    onclick="triggerWakeUp(<?= (int)$g['guard_id'] ?>, '<?= e(addslashes($g['full_name'])) ?>')">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                                Wake Up
                                            </button>
                                        <?php endif; ?>
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

            <!-- Standardized Pagination Footer (Reference: Attendance Page) -->
            <?php App\Core\View::component('components/pagination', [
                'currentPage' => $currentPage ?? 1,
                'totalRecords' => $totalRecords ?? count($guards),
                'pageSize' => $pageSize ?? 10,
                'queryParams' => array_filter([
                    'status' => $statusFilter !== 'all' ? $statusFilter : null,
                    'search' => $searchQuery ?: null,
                ]),
            ]); ?>
        <?php endif; ?>
    </div>
</div>

<form id="wakeUpQuickForm" method="POST" action="<?= url('/admin/notifications/wake-up') ?>" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="guard_id" id="quickWakeUpGuardId" value="">
</form>

<script>
function triggerWakeUp(guardId, guardName) {
    if (confirm(`Send an immediate high-priority Wake-Up Call to ${guardName}?\n\nThis will sound a continuous wake-up alarm on their device until acknowledged.`)) {
        document.getElementById('quickWakeUpGuardId').value = guardId;
        document.getElementById('wakeUpQuickForm').submit();
    }
}
</script>
