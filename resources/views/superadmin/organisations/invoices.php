<?php
/**
 * Superadmin Organisation Invoices History View
 * Displays all billing invoices for a specific tenant organisation with PDF download capability
 */
$org = $org ?? [];
$invoices = $invoices ?? [];
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
$totalRecords = $totalRecords ?? count($invoices);
$pageSize = $pageSize ?? 10;
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <a href="<?= url('/superadmin/organisations') ?>" style="font-size: 0.8125rem; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                    Organisations
                </a>
                <span style="color: #cbd5e1;">/</span>
                <a href="<?= url('/superadmin/organisations/' . ($org['id'] ?? 0) . '/subscription') ?>" style="font-size: 0.8125rem; color: #64748b; text-decoration: none;">
                    Subscription
                </a>
                <span style="color: #cbd5e1;">/</span>
                <span style="font-size: 0.8125rem; color: #0f172a; font-weight: 600;">Invoices</span>
            </div>
            <h1 class="page-header-title">Billing Invoices — <?= e($org['name'] ?? 'Organisation') ?></h1>
            <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Complete billing ledger and immutable PDF statements for tenant <strong><?= e($org['organization_code'] ?? '') ?></strong>.
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <a href="<?= url('/superadmin/organisations/' . ($org['id'] ?? 0) . '/subscription') ?>" class="btn btn-secondary" style="font-size: 0.8125rem; padding: 0.5rem 0.875rem;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Manage Subscription
            </a>
            <a href="<?= url('/superadmin/organisations') ?>" class="btn btn-secondary" style="font-size: 0.8125rem; padding: 0.5rem 0.875rem;">
                Back to List
            </a>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Invoices Card -->
    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid #e2e8f0; border-radius: 12px;">
        <div style="padding: 1.125rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <div>
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0;">Issued Invoices</h3>
                <span style="font-size: 0.75rem; color: #64748b;">Total of <?= $totalRecords ?> invoice(s) generated</span>
            </div>
        </div>

        <?php if (empty($invoices)): ?>
            <div style="text-align: center; padding: 3.5rem 1.5rem;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h4 style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem 0;">No Invoices Found</h4>
                <p style="font-size: 0.8125rem; color: #64748b; margin: 0;">No billing invoices have been issued for this organisation yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e2e8f0; background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                            <th style="padding: 0.875rem 1.25rem; font-weight: 700;">Invoice #</th>
                            <th style="padding: 0.875rem 1rem; font-weight: 700;">Issue Date</th>
                            <th style="padding: 0.875rem 1rem; font-weight: 700;">Billing Period</th>
                            <th style="padding: 0.875rem 1rem; font-weight: 700;">Guard Limit</th>
                            <th style="padding: 0.875rem 1rem; font-weight: 700;">Rate / Guard</th>
                            <th style="padding: 0.875rem 1rem; font-weight: 700;">Total Amount</th>
                            <th style="padding: 0.875rem 1rem; font-weight: 700;">Payment Status</th>
                            <th style="padding: 0.875rem 1.25rem; font-weight: 700; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv):
                            $status = $inv['payment_status'] ?? 'paid';
                            $statusBg = $status === 'paid' ? '#ecfdf5' : ($status === 'unpaid' ? '#fffbeb' : '#fef2f2');
                            $statusColor = $status === 'paid' ? '#059669' : ($status === 'unpaid' ? '#b45309' : '#dc2626');
                            $statusBorder = $status === 'paid' ? '#a7f3d0' : ($status === 'unpaid' ? '#fde68a' : '#fecaca');
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: #334155;">
                                <td style="padding: 1rem 1.25rem; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= e($inv['invoice_number']) ?>
                                </td>
                                <td style="padding: 1rem 1rem;">
                                    <?= !empty($inv['issue_date']) ? date('d M Y', strtotime($inv['issue_date'])) : '—' ?>
                                </td>
                                <td style="padding: 1rem 1rem; font-size: 0.8125rem;">
                                    <?php if (!empty($inv['billing_period_start']) && !empty($inv['billing_period_end'])): ?>
                                        <?= date('d M Y', strtotime($inv['billing_period_start'])) ?> – <?= date('d M Y', strtotime($inv['billing_period_end'])) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem 1rem; font-weight: 600;">
                                    <?= (int)($inv['guard_limit'] ?? 0) ?> Guards
                                </td>
                                <td style="padding: 1rem 1rem; color: #64748b;">
                                    ₹<?= number_format((float)($inv['price_per_guard'] ?? 0), 2) ?>
                                </td>
                                <td style="padding: 1rem 1rem; font-weight: 800; color: #0f172a;">
                                    ₹<?= number_format((float)($inv['total_amount'] ?? 0), 2) ?>
                                </td>
                                <td style="padding: 1rem 1rem;">
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.725rem; font-weight: 700; background: <?= $statusBg ?>; color: <?= $statusColor ?>; border: 1px solid <?= $statusBorder ?>;">
                                        <span style="width: 5px; height: 5px; border-radius: 50%; background: <?= $statusColor ?>;"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem 1.25rem; text-align: right;">
                                    <a href="<?= url('/superadmin/invoices/' . $inv['id'] . '/download') ?>" class="btn btn-secondary" style="font-size: 0.775rem; padding: 0.375rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;" title="Download Verified PDF">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div style="padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.8125rem; color: #64748b;">
                        Showing Page <?= $currentPage ?> of <?= $totalPages ?>
                    </span>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($currentPage > 1): ?>
                            <a href="<?= url('/superadmin/organisations/' . ($org['id'] ?? 0) . '/invoices?page=' . ($currentPage - 1)) ?>" class="btn btn-secondary" style="font-size: 0.775rem; padding: 0.375rem 0.65rem;">
                                &larr; Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= url('/superadmin/organisations/' . ($org['id'] ?? 0) . '/invoices?page=' . ($currentPage + 1)) ?>" class="btn btn-secondary" style="font-size: 0.775rem; padding: 0.375rem 0.65rem;">
                                Next &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
