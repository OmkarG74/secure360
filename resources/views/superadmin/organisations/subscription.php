<?php
/**
 * Superadmin Organisation Subscription & Guard Licensing Console
 * Live capacity meters, mid-term quota adjustments, renewal workflows, and audit history
 */
$org = $org ?? [];
$sub = $sub ?? [];
$history = $history ?? [];
$allSubscriptions = $allSubscriptions ?? [];
$invoices = $invoices ?? [];
$defaultPrice = $defaultPrice ?? 500.00;

$orgId = (int)($org['id'] ?? 0);
$orgName = $org['name'] ?? 'Organisation';
$orgCode = $org['organization_code'] ?? 'ORG';

$guardLimit = (int)($sub['guard_limit'] ?? 30);
$activeGuards = (int)($sub['active_guards'] ?? 0);
$availableSlots = (int)($sub['available_slots'] ?? 0);
$pricePerGuard = (float)($sub['price_per_guard'] ?? 500);
$totalAmount = (float)($sub['total_amount'] ?? 15000);
$startDate = !empty($sub['start_date']) ? date('d M Y', strtotime($sub['start_date'])) : '—';
$endDate = !empty($sub['end_date']) ? date('d M Y', strtotime($sub['end_date'])) : '—';
$rawEndDate = $sub['end_date'] ?? date('Y-m-d', strtotime('+1 year'));
$calculatedStatus = $sub['calculated_status'] ?? 'active';
$usagePct = $sub['usage_percentage'] ?? 0;
$lastInvoice = $sub['last_invoice'] ?? null;
?>

<div class="page-container">
    <!-- Back Navigation -->
    <a href="<?= url('/superadmin/organisations') ?>" class="form-back-nav" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; color: #64748b; font-size: 0.8125rem; font-weight: 500; margin-bottom: 1rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Organisations</span>
    </a>

    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 class="page-header-title" style="margin: 0;"><?= e($orgName) ?></h1>
                <span class="sa-org-code"><?= e($orgCode) ?></span>
            </div>
            <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Guard licensing capacity, subscription pricing, renewal management &amp; billing statements.
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="<?= url('/superadmin/organisations/' . $orgId . '/invoices') ?>" class="btn btn-outline" style="font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>View Invoices (<?= count($invoices) ?>)</span>
            </a>
            <button type="button" class="btn btn-primary" onclick="toggleSection('renewModal')" style="font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Renew Subscription</span>
            </button>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Capacity & Subscription Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">

        <!-- Guard Capacity Card -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">
                    Guard Capacity
                </span>
                <span style="font-size: 0.7rem; color: #2563eb; background: #eff6ff; border: 1px solid #dbeafe; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 600;">
                    <?= $availableSlots ?> Slots Available
                </span>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                <?= $activeGuards ?> <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">/ <?= $guardLimit ?> Guards</span>
            </div>
            <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                <div style="width: <?= $usagePct ?>%; height: 100%; background: <?= $usagePct >= 100 ? '#ef4444' : ($usagePct >= 85 ? '#f59e0b' : '#2563eb') ?>; border-radius: 9999px;"></div>
            </div>
        </div>

        <!-- Plan Status & Expiry -->
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
                Valid Until: <strong><?= e($endDate) ?></strong>
            </div>
        </div>

        <!-- Rate Per Guard -->
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Rate Per Guard
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">
                ₹<?= number_format($pricePerGuard, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                Applied on current license
            </div>
        </div>

        <!-- Total Subscription Value -->
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Total Amount
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">
                ₹<?= number_format($totalAmount, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                <?= $guardLimit ?> guards × ₹<?= number_format($pricePerGuard, 0) ?>
            </div>
        </div>

    </div>

    <!-- Renewal Form Card (Collapsible) -->
    <div id="renewModal" style="display: none; margin-bottom: 1.5rem;" class="card">
        <div style="padding: 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0;">Renew Organisation Subscription</h3>
                <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.2rem;">
                    Provisions a new billing period, updates guard license quotas, and issues a fresh renewal tax invoice.
                </p>
            </div>
            <button type="button" class="btn btn-outline" onclick="toggleSection('renewModal')" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">Close</button>
        </div>

        <form method="POST" action="<?= url('/superadmin/organisations/' . $orgId . '/renew') ?>" style="padding: 1.75rem;">
            <?= csrf_field() ?>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">Start Date <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="start_date" id="renewStartDate" class="form-control" value="<?= date('Y-m-d') ?>" required onchange="handleRenewDateChange()">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">End Date <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="end_date" id="renewEndDate" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">Guard Limit <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="guard_limit" id="renewGuardLimit" class="form-control" value="<?= $guardLimit ?>" min="<?= max(1, $activeGuards) ?>" required oninput="calculateRenewPreview()">
                    <span style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem; display: block;">Min <?= $activeGuards ?> (active guards floor)</span>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">Price / Guard (₹) <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="price_per_guard" id="renewPrice" class="form-control" value="<?= number_format($pricePerGuard, 2, '.', '') ?>" min="0" step="0.01" required oninput="calculateRenewPreview()">
                </div>
            </div>

            <!-- Renewal Preview Box -->
            <div style="padding: 1rem 1.25rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Renewal Calculation Preview</span>
                    <div style="font-size: 0.8125rem; color: #334155; margin-top: 0.2rem;" id="renewFormulaPreview">
                        <?= $guardLimit ?> guards × ₹<?= number_format($pricePerGuard, 2) ?> = ₹<?= number_format($totalAmount, 2) ?>
                    </div>
                </div>
                <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a;" id="renewTotalDisplay">
                    ₹<?= number_format($totalAmount, 2) ?>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="toggleSection('renewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">Process Renewal &amp; Generate Invoice</button>
            </div>
        </form>
    </div>

    <!-- Two-Column Control Panel: Guard Limit Adjustment & Recent Invoices -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; align-items: start;">

        <!-- Manage Guard Limit & Price Card -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">
                Adjust Guard Capacity &amp; Pricing
            </h3>
            <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1.25rem;">
                Modify licensed guard quota or subscription rate. Decreasing below active guards count (<strong><?= $activeGuards ?></strong>) is strictly rejected.
            </p>

            <form method="POST" action="<?= url('/superadmin/organisations/' . $orgId . '/subscription') ?>" id="adjustLimitForm" onsubmit="return validateLimitFloor(event)">
                <?= csrf_field() ?>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">
                            Licensed Guard Limit <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="number" name="guard_limit" id="adjGuardLimit" class="form-control" value="<?= $guardLimit ?>" min="<?= max(1, $activeGuards) ?>" required oninput="calculateAdjustPreview()">
                        <div style="display: flex; justify-content: space-between; font-size: 0.725rem; margin-top: 0.25rem;">
                            <span style="color: #64748b;">Current Active Guards: <strong><?= $activeGuards ?></strong></span>
                            <span style="color: #b45309; font-weight: 600;">Minimum Allowed: <?= $activeGuards ?></span>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">
                            Subscription Price Per Guard (₹) <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="number" name="price_per_guard" id="adjPrice" class="form-control" value="<?= number_format($pricePerGuard, 2, '.', '') ?>" min="0" step="0.01" required oninput="calculateAdjustPreview()">
                        <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">
                            Updating this updates this organisation's rate. Historical statements remain unchanged.
                        </span>
                    </div>

                    <!-- Live Calculation Preview -->
                    <div style="padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">New Subscription Total:</span>
                            <span style="font-size: 1.1rem; font-weight: 800; color: #0f172a;" id="adjTotalDisplay">₹<?= number_format($totalAmount, 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem;">
                            <span style="color: #64748b;">Billing Difference:</span>
                            <span style="font-weight: 700;" id="adjDiffDisplay">₹0.00</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; padding-top: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                            Save Capacity Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Latest Billing Invoices Card -->
        <div class="card" style="padding: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.0625rem; font-weight: 700; color: #0f172a; margin: 0;">
                    Latest Statements
                </h3>
                <a href="<?= url('/superadmin/organisations/' . $orgId . '/invoices') ?>" style="font-size: 0.75rem; color: #2563eb; font-weight: 600; text-decoration: none;">
                    View All &rarr;
                </a>
            </div>

            <?php if (empty($invoices)): ?>
                <div style="padding: 2.5rem 1rem; text-align: center; color: #64748b; font-size: 0.8125rem;">
                    No invoice statements generated yet.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach (array_slice($invoices, 0, 4) as $inv): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <div>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= e($inv['invoice_number']) ?>
                                </div>
                                <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.15rem;">
                                    <?= date('d M Y', strtotime($inv['invoice_date'])) ?> &bull; <?= (int)$inv['guard_quantity'] ?> Guards
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 0.9rem; font-weight: 700; color: #0f172a;">
                                    ₹<?= number_format((float)$inv['total_amount'], 0) ?>
                                </span>
                                <a href="<?= url('/superadmin/invoices/' . $inv['id'] . '/download') ?>" class="sa-btn-action" style="padding: 0.25rem 0.5rem;" title="Download PDF">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Subscription History Table -->
    <div class="table-card">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;">
            <h2 style="font-size: 0.9375rem; font-weight: 700; color: #0f172a; margin: 0;">
                Subscription Audit &amp; Adjustment History
            </h2>
        </div>

        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Event Date</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Action</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Capacity Transition</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">New Rate</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Amount Diff</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Performed By</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="7" style="padding: 2.5rem; text-align: center; color: #64748b;">
                                No historical subscription adjustments recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem 1rem; color: #334155;">
                                    <?= date('d M Y, H:i', strtotime($h['created_at'])) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: #0f172a;">
                                    <?= ucwords(str_replace('_', ' ', $h['change_type'])) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php if ($h['previous_guard_limit'] !== null): ?>
                                        <span style="color: #64748b; text-decoration: line-through;"><?= $h['previous_guard_limit'] ?></span> &rarr;
                                    <?php endif; ?>
                                    <strong><?= $h['new_guard_limit'] ?> Guards</strong>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #475569;">
                                    ₹<?= number_format((float)$h['new_price_per_guard'], 2) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-weight: 700; color: <?= (float)$h['amount_difference'] >= 0 ? '#059669' : '#dc2626' ?>;">
                                    <?= (float)$h['amount_difference'] >= 0 ? '+' : '' ?>₹<?= number_format((float)$h['amount_difference'], 2) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #475569;">
                                    <?= e($h['performed_by_name'] ?? 'Superadmin') ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: #64748b; font-size: 0.75rem;">
                                    <?= e($h['notes'] ?? '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const activeGuardsFloor = <?= $activeGuards ?>;
const prevTotal = <?= $totalAmount ?>;

function toggleSection(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}

function calculateAdjustPreview() {
    const guardsInput = document.getElementById('adjGuardLimit');
    const priceInput = document.getElementById('adjPrice');
    const totalDisplay = document.getElementById('adjTotalDisplay');
    const diffDisplay = document.getElementById('adjDiffDisplay');

    if (!guardsInput || !priceInput || !totalDisplay || !diffDisplay) return;

    const guards = Math.max(1, parseInt(guardsInput.value) || 0);
    const price = Math.max(0, parseFloat(priceInput.value) || 0);
    const newTotal = guards * price;
    const diff = newTotal - prevTotal;

    totalDisplay.innerText = '₹' + newTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    diffDisplay.innerText = (diff >= 0 ? '+₹' : '-₹') + Math.abs(diff).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    diffDisplay.style.color = diff >= 0 ? '#059669' : '#dc2626';
}

function validateLimitFloor(e) {
    const guardsInput = document.getElementById('adjGuardLimit');
    if (!guardsInput) return true;

    const newLimit = parseInt(guardsInput.value) || 0;
    if (newLimit < activeGuardsFloor) {
        alert(`Cannot reduce the guard limit below the current active guard count (${activeGuardsFloor}). Please deactivate guards first.`);
        guardsInput.focus();
        e.preventDefault();
        return false;
    }
    return true;
}

function calculateRenewPreview() {
    const guardsInput = document.getElementById('renewGuardLimit');
    const priceInput = document.getElementById('renewPrice');
    const totalDisplay = document.getElementById('renewTotalDisplay');
    const formulaPreview = document.getElementById('renewFormulaPreview');

    if (!guardsInput || !priceInput || !totalDisplay) return;

    const guards = Math.max(1, parseInt(guardsInput.value) || 0);
    const price = Math.max(0, parseFloat(priceInput.value) || 0);
    const total = guards * price;

    totalDisplay.innerText = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (formulaPreview) {
        formulaPreview.innerText = `${guards} guards × ₹${price.toLocaleString('en-IN')} = ₹${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
}

function handleRenewDateChange() {
    const startInput = document.getElementById('renewStartDate');
    const endInput = document.getElementById('renewEndDate');
    if (!startInput || !endInput || !startInput.value) return;

    const startDate = new Date(startInput.value);
    if (!isNaN(startDate.getTime())) {
        const nextYear = new Date(startDate);
        nextYear.setFullYear(nextYear.getFullYear() + 1);
        endInput.value = nextYear.toISOString().split('T')[0];
    }
}

document.addEventListener('DOMContentLoaded', () => {
    calculateAdjustPreview();
    calculateRenewPreview();
});
</script>
