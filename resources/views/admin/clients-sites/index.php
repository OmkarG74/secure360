<?php
/**
 * Clients & Accounts View
 * Replicates Screenshot 3: Circular letter avatars, status filter pills, search, and pagination
 */
$customers = $customers ?? [];
$sites = $sites ?? [];
$statusFilter = $statusFilter ?? 'all';
$searchQuery = $searchQuery ?? '';
$totalCount = $totalCount ?? count($customers);
$activeCount = $activeCount ?? 0;
$inactiveCount = $inactiveCount ?? 0;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Clients &amp; Accounts</h1>
        <p style="font-size: 0.875rem; color: #64748b;">Manage client organizations and their assigned security sites.</p>
    </div>
    <a href="<?= url('/admin/clients/register') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; font-weight: 600; text-decoration: none;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
        Register Client
    </a>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<!-- Filter & Search Toolbar -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        
        <!-- Status Filter Pills -->
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="<?= url('/admin/clients-sites?status=all' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
               style="text-decoration: none; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 9999px; transition: all 0.15s ease;
                      <?= $statusFilter === 'all' ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                All (<?= $totalCount ?>)
            </a>
            <a href="<?= url('/admin/clients-sites?status=active' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
               style="text-decoration: none; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 9999px; transition: all 0.15s ease;
                      <?= $statusFilter === 'active' ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                Active (<?= $activeCount ?>)
            </a>
            <a href="<?= url('/admin/clients-sites?status=inactive' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '')) ?>" 
               style="text-decoration: none; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 9999px; transition: all 0.15s ease;
                      <?= $statusFilter === 'inactive' ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #64748b;' ?>">
                Inactive (<?= $inactiveCount ?>)
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="<?= url('/admin/clients-sites') ?>" style="display: flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <div class="input-icon-wrapper" style="width: 280px;">
                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" class="form-control" placeholder="Search clients or sites..." value="<?= e($searchQuery) ?>" style="padding-top: 0.45rem; padding-bottom: 0.45rem; font-size: 0.8125rem;">
            </div>
            <?php if ($searchQuery): ?>
                <a href="<?= url('/admin/clients-sites?status=' . $statusFilter) ?>" style="font-size: 0.75rem; color: #ef4444; text-decoration: none; font-weight: 600;">Clear</a>
            <?php endif; ?>
        </form>

    </div>
</div>

<!-- Clients Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <?php if (empty($customers)): ?>
        <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: #94a3b8;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <p style="font-size: 1rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No clients match your filter criteria.</p>
            <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.5rem;">Try adjusting your search query or status filter.</p>
            <a href="<?= url('/admin/clients/register') ?>" class="btn btn-outline" style="color: #2563eb; border-color: #2563eb; text-decoration: none; font-size: 0.8125rem;">
                + Register First Client
            </a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">Client / Company</th>
                        <th style="padding: 1rem 1.25rem;">Contact Person</th>
                        <th style="padding: 1rem 1.25rem;">Contact Details</th>
                        <th style="padding: 1rem 1.25rem;">Assigned Sites</th>
                        <th style="padding: 1rem 1.25rem;">Status</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">Actions</th>
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
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                            
                            <!-- Client Avatar & Name -->
                            <td style="padding: 1.15rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.85rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0; border: 1px solid #dbeafe;">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <a href="<?= url('/admin/clients/' . $c['id'] . '/edit') ?>" style="font-weight: 600; color: #0f172a; text-decoration: none;">
                                            <?= e($c['name']) ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: #64748b; font-family: monospace; margin-top: 0.15rem;">
                                            <?= e($c['client_code']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact Person -->
                            <td style="padding: 1.15rem 1.25rem; color: #334155; font-weight: 500;">
                                <?= e($c['contact_person'] ?? '—') ?>
                            </td>

                            <!-- Phone / Email -->
                            <td style="padding: 1.15rem 1.25rem; color: #475569;">
                                <div><?= e($c['phone'] ?? '—') ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($c['email'] ?? '') ?></div>
                            </td>

                            <!-- Sites Count & List -->
                            <td style="padding: 1.15rem 1.25rem;">
                                <?php $siteCount = count($c['sites'] ?? []); ?>
                                <?php if ($siteCount > 0): ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.3rem; background: #eff6ff; color: #1d4ed8; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.55rem; border-radius: 4px; border: 1px solid #dbeafe;">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                            <?= $siteCount ?> <?= $siteCount === 1 ? 'Site' : 'Sites' ?>
                                        </span>
                                        <?php foreach (array_slice($c['sites'], 0, 2) as $s): ?>
                                            <span style="font-size: 0.75rem; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.2rem 0.45rem; border-radius: 4px;">
                                                <?= e($s['site_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if ($siteCount > 2): ?>
                                            <span style="font-size: 0.75rem; color: #94a3b8;">+<?= $siteCount - 2 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem; font-style: italic;">No active sites</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status Pill -->
                            <td style="padding: 1.15rem 1.25rem;">
                                <?php if ((int)$c['status'] === 0): ?>
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

                            <!-- Actions -->
                            <td style="padding: 1.15rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <a href="<?= url('/admin/clients/' . $c['id'] . '/edit') ?>" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; text-decoration: none; border-color: #cbd5e1; color: #334155;">
                                        Edit
                                    </a>
                                    <form method="POST" action="<?= url('/admin/clients/' . $c['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this client?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" style="background: none; border: none; color: #94a3b8; font-size: 0.75rem; cursor: pointer; padding: 0.35rem 0.5rem; border-radius: 4px;" title="Deactivate Client">
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
                Showing <strong><?= count($customers) ?></strong> of <strong><?= $totalCount ?></strong> clients
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; color: #94a3b8; border-color: #e2e8f0;" disabled>Previous</button>
                <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; color: #94a3b8; border-color: #e2e8f0;" disabled>Next</button>
            </div>
        </div>
    <?php endif; ?>
</div>
