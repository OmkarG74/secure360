<?php
/**
 * Guards Roster & Management View
 * Replicates Screenshot 5: Clean table, circular avatars, status pills, search, NO CHECKBOXES
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

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Guards</h1>
        <p style="font-size: 0.875rem; color: #64748b;">Personnel directory authorized for mobile Flutter duty and check-ins.</p>
    </div>
    <a href="<?= url('/admin/guards/setup') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; font-weight: 600; text-decoration: none;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
        + Setup Guard
    </a>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<!-- Filter & Search Toolbar -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        
        <!-- Status Filter Pills -->
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="<?= url('/admin/guards?status=all' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
               style="text-decoration: none; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 9999px; transition: all 0.15s ease;
                      <?= $statusFilter === 'all' ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                All (<?= $totalCount ?>)
            </a>
            <a href="<?= url('/admin/guards?status=active' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
               style="text-decoration: none; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 9999px; transition: all 0.15s ease;
                      <?= $statusFilter === 'active' ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                Active (<?= $activeCount ?>)
            </a>
            <a href="<?= url('/admin/guards?status=inactive' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
               style="text-decoration: none; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 9999px; transition: all 0.15s ease;
                      <?= $statusFilter === 'inactive' ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                Inactive (<?= $inactiveCount ?>)
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="<?= url('/admin/guards') ?>" style="display: flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <div class="input-icon-wrapper" style="width: 280px;">
                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" class="form-control" placeholder="Search guards, badge, site..." value="<?= e($searchQuery) ?>" style="padding-top: 0.45rem; padding-bottom: 0.45rem; font-size: 0.8125rem;">
            </div>
            <?php if ($searchQuery): ?>
                <a href="<?= url('/admin/guards?status=' . $statusFilter) ?>" style="font-size: 0.75rem; color: #ef4444; text-decoration: none; font-weight: 600;">Clear</a>
            <?php endif; ?>
        </form>

    </div>
</div>

<!-- Guards Table (STRICTLY NO CHECKBOXES) -->
<div class="card" style="padding: 0; overflow: hidden;">
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
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">Security Guard</th>
                        <th style="padding: 1rem 1.25rem;">Badge ID</th>
                        <th style="padding: 1rem 1.25rem;">Contact Details</th>
                        <th style="padding: 1rem 1.25rem;">Assigned Post / Shift</th>
                        <th style="padding: 1rem 1.25rem;">Mobile Status</th>
                        <th style="padding: 1rem 1.25rem;">Last Login</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">Actions</th>
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
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                            
                            <!-- Guard Avatar & Full Name -->
                            <td style="padding: 1.15rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.85rem;">
                                    <?php if (!empty($g['photo_url'])): ?>
                                        <img src="<?= url('/' . $g['photo_url']) ?>" alt="<?= e($g['full_name']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1; flex-shrink: 0;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0; border: 1px solid #dbeafe;">
                                            <?= e($initials) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="<?= url('/admin/guards/' . $g['guard_id'] . '/edit') ?>" style="font-weight: 600; color: #0f172a; text-decoration: none;">
                                            <?= e($g['full_name']) ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            Field Operator
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Badge ID -->
                            <td style="padding: 1.15rem 1.25rem;">
                                <span style="display: inline-block; background: #f1f5f9; color: #1e293b; font-family: monospace; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0;">
                                    <?= e($g['employee_code'] ?? 'GRD-' . $g['guard_id']) ?>
                                </span>
                            </td>

                            <!-- Contact Details -->
                            <td style="padding: 1.15rem 1.25rem; color: #475569;">
                                <div><?= e($g['phone'] ?? '—') ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($g['email']) ?></div>
                            </td>

                            <!-- Assigned Post / Shift -->
                            <td style="padding: 1.15rem 1.25rem;">
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
                            <td style="padding: 1.15rem 1.25rem;">
                                <?php if ((int)$g['guard_status'] === 0): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Last Login -->
                            <td style="padding: 1.15rem 1.25rem; font-size: 0.8125rem; color: #64748b;">
                                <?php if (!empty($g['last_login_at'])): ?>
                                    <?= date('M j, Y g:i A', strtotime($g['last_login_at'])) ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Never logged in</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td style="padding: 1.15rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <a href="<?= url('/admin/guards/' . $g['guard_id'] . '/edit') ?>" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; text-decoration: none; border-color: #cbd5e1; color: #334155;">
                                        Edit
                                    </a>
                                    <form method="POST" action="<?= url('/admin/guards/' . $g['guard_id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('Deactivate this guard?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" style="background: none; border: none; color: #94a3b8; font-size: 0.75rem; cursor: pointer; padding: 0.35rem 0.5rem; border-radius: 4px;" title="Deactivate Guard">
                                            ✕
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
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 0.8125rem; color: #64748b;">
            <div>
                Showing <strong><?= count($guards) ?></strong> of <strong><?= $totalCount ?></strong> guards
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; color: #94a3b8; border-color: #e2e8f0;" disabled>Previous</button>
                <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; color: #94a3b8; border-color: #e2e8f0;" disabled>Next</button>
            </div>
        </div>
    <?php endif; ?>
</div>
