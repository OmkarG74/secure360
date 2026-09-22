<?php
/**
 * Organisation Admin Subscription & Billing Portal
 * Displays licensed guard capacity, current subscription status, and downloadable invoices
 */
$sub = $sub ?? null;
$invoices = $invoices ?? [];
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
$totalRecords = $totalRecords ?? count($invoices);

$guardLimit = (int)($sub['guard_limit'] ?? 30);
$activeGuards = (int)($sub['active_guards'] ?? 0);
$availableSlots = (int)($sub['available_slots'] ?? 0);
$pricePerGuard = (float)($sub['price_per_guard'] ?? 500);
$totalAmount = (float)($sub['total_amount'] ?? 15000);
$endDate = !empty($sub['end_date']) ? date('d M Y', strtotime($sub['end_date'])) : '—';
$calculatedStatus = $sub['calculated_status'] ?? 'active';
$usagePct = $sub['usage_percentage'] ?? 0;
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title">Subscription &amp; Invoices</h1>
            <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Manage your organisation's active guard license quota and view verified billing statements.
            </p>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Subscription KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">

        <!-- Guard Capacity -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">
                    Guard Quota
                </span>
                <span class="badge-site-count" style="font-size: 0.7rem; height: 20px; padding: 0 0.5rem; background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;">
                    <?= $availableSlots ?> Available
                </span>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                <?= $activeGuards ?> <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">/ <?= $guardLimit ?></span>
            </div>
            <!-- Progress Bar -->
            <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                <div style="width: <?= $usagePct ?>%; height: 100%; background: <?= $usagePct >= 100 ? '#ef4444' : ($usagePct >= 85 ? '#f59e0b' : '#2563eb') ?>; border-radius: 9999px;"></div>
            </div>
        </div>

        <!-- Subscription Status -->
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Plan Status
            </div>
            <div style="margin-bottom: 0.35rem;">
                <?php if ($calculatedStatus === 'active'): ?>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                        Active
                    </span>
                <?php elseif ($calculatedStatus === 'expiring_soon'): ?>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700; background: #fffbeb; color: #b45309; border: 1px solid #fde68a;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;"></span>
                        Expiring Soon
                    </span>
                <?php else: ?>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>
                        <?= ucfirst($calculatedStatus) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.35rem;">
                Valid until <strong><?= e($endDate) ?></strong>
            </div>
        </div>

        <!-- Price Per Guard -->
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Price / Guard
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">
                ₹<?= number_format($pricePerGuard, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                Per guard per cycle
            </div>
        </div>

        <!-- Total Subscription Amount -->
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Current Plan Total
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">
                ₹<?= number_format($totalAmount, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                <?= $guardLimit ?> guards × ₹<?= number_format($pricePerGuard, 0) ?>
            </div>
        </div>

    </div>

    <!-- Capacity Notice Banner -->
    <?php if ($calculatedStatus === 'expiring_soon'): ?>
        <div style="padding: 1rem 1.25rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span style="font-size: 0.875rem; color: #92400e; font-weight: 600;">
                    Your subscription is expiring soon (<?= e($endDate) ?>). Please contact your platform administrator to renew.
                </span>
            </div>
        </div>
    <?php elseif ($activeGuards >= $guardLimit): ?>
        <div style="padding: 1rem 1.25rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span style="font-size: 0.875rem; color: #1e40af; font-weight: 600;">
                    Guard limit reached (<?= $activeGuards ?> / <?= $guardLimit ?> Guards Used). To onboard more personnel, request a capacity upgrade.
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Invoices Table Card -->
    <div class="table-card">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 0.9375rem; font-weight: 700; color: #0f172a; margin: 0;">
                Billing History &amp; Invoices
            </h2>
            <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                <?= $totalRecords ?> Total Statements
            </span>
        </div>

        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Invoice #</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Date</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Billing Period</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Guards</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Rate</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Amount</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase;">Status</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; font-size: 0.725rem; text-transform: uppercase; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="8" style="padding: 3rem; text-align: center; color: #64748b;">
                                No invoices found for your organisation.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.875rem 1rem; font-family: monospace; font-weight: 700; color: #0f172a;">
                                    <?= e($inv['invoice_number']) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: #334155;">
                                    <?= date('d M Y', strtotime($inv['invoice_date'])) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: #64748b; font-size: 0.75rem;">
                                    <?= date('d M Y', strtotime($inv['billing_start_date'])) ?> &rarr; <?= date('d M Y', strtotime($inv['billing_end_date'])) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; font-weight: 600; color: #0f172a;">
                                    <?= (int)$inv['guard_quantity'] ?> Guards
                                </td>
                                <td style="padding: 0.875rem 1rem; color: #475569;">
                                    ₹<?= number_format((float)$inv['price_per_guard'], 2) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; font-weight: 700; color: #0f172a;">
                                    ₹<?= number_format((float)$inv['total_amount'], 2) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem;">
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                                        <?= strtoupper(e($inv['status'])) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.875rem 1rem; text-align: right;">
                                    <a href="<?= url('/admin/invoices/' . $inv['id'] . '/download') ?>"
                                       class="btn btn-outline"
                                       style="padding: 0.35rem 0.75rem; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none; border-radius: 6px; font-weight: 600;">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        <span>Download PDF</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="padding: 0.875rem 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; color: #64748b;">
                    Page <?= $currentPage ?> of <?= $totalPages ?>
                </span>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= url('/admin/billing?page=' . ($currentPage - 1)) ?>" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; text-decoration: none;">Previous</a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= url('/admin/billing?page=' . ($currentPage + 1)) ?>" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; text-decoration: none;">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
