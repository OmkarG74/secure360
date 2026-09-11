<?php
/**
 * Superadmin Customer Organisations Management View
 * Live data from organizations table
 */
$organizations = $organizations ?? [];
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Tenant Organisations</h1>
        <p style="font-size: 0.875rem; color: #64748b;">Multi-tenant accounts root from <code>organizations</code> table.</p>
    </div>
    <a href="<?= url('/superadmin/organisations/create') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; font-weight: 600; text-decoration: none;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
        + Onboard Organisation
    </a>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<div class="card" style="padding: 0; overflow: hidden;">
    <?php if (empty($organizations)): ?>
        <div style="border: 1px dashed #cbd5e1; border-radius: var(--radius-sm); padding: 3rem; text-align: center; color: #64748b;">
            <p style="font-size: 0.9375rem; font-weight: 500;">No customer organisations registered yet.</p>
            <p style="font-size: 0.8125rem; margin-top: 0.5rem; color: #94a3b8;">
                Click "Onboard Organisation" above to register your first tenant security agency.
            </p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">Tenant Code</th>
                        <th style="padding: 1rem 1.25rem;">Organisation Name</th>
                        <th style="padding: 1rem 1.25rem;">Contact Person</th>
                        <th style="padding: 1rem 1.25rem;">Email / Phone</th>
                        <th style="padding: 1rem 1.25rem;">Status</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($organizations as $org): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                            <td style="padding: 1.15rem 1.25rem; font-weight: 700; color: #0f172a; font-family: monospace;">
                                <?= e($org['organization_code']) ?>
                            </td>
                            <td style="padding: 1.15rem 1.25rem;">
                                <div style="font-weight: 600; color: #0f172a;"><?= e($org['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= e($org['address'] ?? '') ?></div>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; color: #334155; font-weight: 500;">
                                <?= e($org['contact_person'] ?? '—') ?>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; color: #475569;">
                                <div><?= e($org['email'] ?? '—') ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($org['phone'] ?? '') ?></div>
                            </td>
                            <td style="padding: 1.15rem 1.25rem;">
                                <?php if ((int)$org['status'] === 0): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>
                                        Suspended
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1.15rem 1.25rem; text-align: right;">
                                <form method="POST" action="<?= url('/superadmin/organisations/' . $org['id'] . '/toggle-status') ?>" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-color: #cbd5e1; color: #334155;">
                                        <?= (int)$org['status'] === 0 ? 'Suspend' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
