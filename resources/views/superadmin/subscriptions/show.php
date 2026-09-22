<?php
/**
 * Superadmin Dedicated Subscription Details View
 * Professional Dashboard-Style Layout:
 * - Header: Back button, Title & Subtitle, Right-side Status Badge + "Expires in X days", Edit & Suspend actions
 * - Top Row (3-Column Desktop Grid):
 *   1. Organisation Details (Name, Code, Admin Name, Email, Phone, Address)
 *   2. Subscription Details (Status, Start Date, Expiry Date, Remaining Days, Duration)
 *   3. Guard Capacity (Guard Limit, Active Guards, Available Guards, Usage %, Progress Bar)
 * - Bottom Row (2-Column Desktop Grid):
 *   4. Billing Information (Price Per Guard, Current Guard Count, Current Subscription Amount, Billing Cycle)
 *   5. Subscription History (Date, Type, Guards, Remarks)
 * - NO INVOICE TABLE (Invoices managed exclusively under Superadmin -> Invoices)
 */
$sub = $sub ?? [];
$subId = (int)($sub['id'] ?? 0);
$primaryAdmin = $primaryAdmin ?? null;
$history = $history ?? [];
$subInvoice = $subInvoice ?? null;
$activeGuards = (int)($activeGuards ?? 0);
$availableSlots = (int)($availableSlots ?? 0);
$usagePercent = (int)($usagePercent ?? 0);
$guardLimit = (int)($sub['guard_limit'] ?? 0);
$currency = $currency ?? 'INR';
$statusKey = $sub['calculated_status'] ?? 'active';

// Duration calculation
$startTs = strtotime($sub['start_date'] ?? 'now');
$endTs = strtotime($sub['end_date'] ?? 'now');
$diffDays = max(0, (int)round(($endTs - $startTs) / 86400));
if ($diffDays >= 360) {
    $years = round($diffDays / 365);
    $durationLabel = $years . ' Year' . ($years > 1 ? 's' : '');
} elseif ($diffDays >= 28) {
    $months = round($diffDays / 30);
    $durationLabel = $months . ' Month' . ($months > 1 ? 's' : '');
} else {
    $durationLabel = $diffDays . ' Day' . ($diffDays !== 1 ? 's' : '');
}

// Expiry description label
if ($statusKey === 'suspended') {
    $remainingLabel = 'Suspended';
} elseif ($sub['days_remaining'] > 0) {
    $remainingLabel = "Expires in {$sub['days_remaining']} days";
} elseif ($sub['days_remaining'] === 0) {
    $remainingLabel = "Expires today";
} else {
    $remainingLabel = "Expired " . abs((int)$sub['days_remaining']) . " days ago";
}

// Guard bar color
$barColor = '#cbd5e1';
if ($usagePercent >= 100 || $activeGuards >= $guardLimit) {
    $barColor = '#ef4444';
} elseif ($usagePercent >= 80) {
    $barColor = '#f59e0b';
} elseif ($usagePercent > 0) {
    $barColor = '#2563eb';
}

$adminName = !empty($primaryAdmin['full_name']) ? $primaryAdmin['full_name'] : (!empty($sub['contact_person']) ? $sub['contact_person'] : '—');
$adminEmail = !empty($sub['organization_email']) ? $sub['organization_email'] : (!empty($primaryAdmin['email']) ? $primaryAdmin['email'] : '—');
$adminPhone = !empty($sub['organization_phone']) ? $sub['organization_phone'] : (!empty($primaryAdmin['phone']) ? $primaryAdmin['phone'] : '—');
$orgAddress = !empty($sub['organization_address']) ? $sub['organization_address'] : '—';
?>

<style>
/* ==========================================================================
   Superadmin Subscription Details Dashboard Grid
   ========================================================================== */
.sub-view-container {
    width: 100%;
    margin: 0;
}

/* 3-Column Top Grid on Desktop */
.sub-grid-top {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

/* 2-Column Bottom Grid on Desktop */
.sub-grid-bottom {
    display: grid;
    grid-template-columns: 1fr 1.35fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

@media (max-width: 1100px) {
    .sub-grid-top {
        grid-template-columns: 1fr 1fr;
    }
    .sub-grid-bottom {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sub-grid-top {
        grid-template-columns: 1fr;
    }
}

.sub-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.35rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
}

.sub-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.sub-card-title {
    font-size: 0.9125rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.sub-info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sub-info-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.8125rem;
    gap: 1rem;
}

.sub-info-label {
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}

.sub-info-value {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
    word-break: break-word;
}

.sa-org-code {
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: #0f172a;
    background: #f1f5f9;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    display: inline-block;
}

/* Unified Status Badges */
.sa-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.sa-status-badge.active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sa-status-badge.active .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}

.sa-status-badge.expiring {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.sa-status-badge.expiring .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #f59e0b;
}

.sa-status-badge.expired {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.sa-status-badge.expired .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
}

.sa-status-badge.suspended {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.sa-status-badge.suspended .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
}

/* History Table */
.sa-history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.sa-history-table th {
    background: #f8fafc;
    padding: 0.65rem 0.85rem;
    font-size: 0.725rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #475569;
    letter-spacing: 0.04em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.sa-history-table td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.sa-history-table tbody tr:last-child td {
    border-bottom: none;
}
</style>

<div class="page-container sub-view-container">
    <!-- Back Navigation -->
    <a href="<?= url('/superadmin/subscriptions') ?>" class="form-back-nav" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; font-size: 0.8125rem; font-weight: 500; text-decoration: none; margin-bottom: 1.25rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Subscriptions</span>
    </a>

    <!-- Page Header with Right-Side Status + Expiry -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title" style="margin: 0;">Subscription Details</h1>
            <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Complete information about this subscription
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <!-- Status Badge + Remaining Days -->
            <div style="display: flex; align-items: center; gap: 0.65rem; background: #ffffff; border: 1px solid #e2e8f0; padding: 0.35rem 0.85rem; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <?php if ($statusKey === 'active'): ?>
                    <span class="sa-status-badge active"><span class="badge-dot"></span>Active</span>
                <?php elseif ($statusKey === 'expiring_soon'): ?>
                    <span class="sa-status-badge expiring"><span class="badge-dot"></span>Expiring</span>
                <?php elseif ($statusKey === 'expired'): ?>
                    <span class="sa-status-badge expired"><span class="badge-dot"></span>Expired</span>
                <?php else: ?>
                    <span class="sa-status-badge suspended"><span class="badge-dot"></span>Suspended</span>
                <?php endif; ?>
                <span style="font-size: 0.775rem; font-weight: 600; color: #475569;"><?= e($remainingLabel) ?></span>
            </div>

            <!-- Action Controls -->
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <a href="<?= url('/superadmin/subscriptions/' . $subId . '/edit') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; text-decoration: none; height: 38px; padding: 0 1rem; font-size: 0.8125rem; border-radius: 8px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <span>Edit</span>
                </a>

                <form method="POST" action="<?= url('/superadmin/subscriptions/' . $subId . '/toggle-status') ?>" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= url('/superadmin/subscriptions/' . $subId) ?>">
                    <button type="submit" class="btn <?= (int)$sub['status'] === 3 ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 600; height: 38px; padding: 0 0.875rem; font-size: 0.8125rem; border-radius: 8px; color: <?= (int)$sub['status'] === 3 ? '#ffffff' : '#b45309' ?>; border-color: <?= (int)$sub['status'] === 3 ? '' : '#fde68a' ?>; background: <?= (int)$sub['status'] === 3 ? '' : '#fffbeb' ?>;">
                        <?= (int)$sub['status'] === 3 ? 'Activate' : 'Suspend' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- TOP ROW: 3-Column Desktop Grid (Organisation, Subscription Details, Guard Capacity) -->
    <div class="sub-grid-top">
        <!-- 1. Organisation Details Card -->
        <div class="sub-card">
            <div class="sub-card-header">
                <h2 class="sub-card-title">
                    <svg width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
                    <span>Organisation Details</span>
                </h2>
                <a href="<?= url('/superadmin/organisations/' . $sub['organization_id']) ?>" style="font-size: 0.75rem; color: #2563eb; font-weight: 600; text-decoration: none;">
                    View Profile &rarr;
                </a>
            </div>

            <div class="sub-info-list">
                <div class="sub-info-row">
                    <span class="sub-info-label">Organisation Name</span>
                    <span class="sub-info-value" style="font-weight: 700; color: #0f172a;">
                        <?= e($sub['organization_name']) ?>
                    </span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Organisation Code</span>
                    <span class="sub-info-value">
                        <span class="sa-org-code"><?= e($sub['organization_code']) ?></span>
                    </span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Admin Name</span>
                    <span class="sub-info-value"><?= e($adminName) ?></span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Email</span>
                    <span class="sub-info-value"><?= e($adminEmail) ?></span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Phone</span>
                    <span class="sub-info-value"><?= e($adminPhone) ?></span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Address</span>
                    <span class="sub-info-value" style="font-weight: 500; font-size: 0.775rem; color: #475569; max-width: 220px;">
                        <?= e($orgAddress) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Subscription Details Card -->
        <div class="sub-card">
            <div class="sub-card-header">
                <h2 class="sub-card-title">
                    <svg width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Subscription Details</span>
                </h2>
                <span class="sa-org-code">#SUB-<?= $subId ?></span>
            </div>

            <div class="sub-info-list">
                <div class="sub-info-row">
                    <span class="sub-info-label">Status</span>
                    <span class="sub-info-value">
                        <?php if ($statusKey === 'active'): ?>
                            <span class="sa-status-badge active"><span class="badge-dot"></span>Active</span>
                        <?php elseif ($statusKey === 'expiring_soon'): ?>
                            <span class="sa-status-badge expiring"><span class="badge-dot"></span>Expiring</span>
                        <?php elseif ($statusKey === 'expired'): ?>
                            <span class="sa-status-badge expired"><span class="badge-dot"></span>Expired</span>
                        <?php else: ?>
                            <span class="sa-status-badge suspended"><span class="badge-dot"></span>Suspended</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Start Date</span>
                    <span class="sub-info-value"><?= format_date($sub['start_date']) ?></span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Expiry Date</span>
                    <span class="sub-info-value" style="color: <?= $statusKey === 'expired' ? '#dc2626' : ($statusKey === 'expiring_soon' ? '#b45309' : '#0f172a') ?>;">
                        <?= format_date($sub['end_date']) ?>
                    </span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Remaining Days</span>
                    <span class="sub-info-value">
                        <?= $sub['days_remaining'] > 0 ? $sub['days_remaining'] . ' days' : 'Expired (' . abs($sub['days_remaining']) . ' days ago)' ?>
                    </span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Duration</span>
                    <span class="sub-info-value"><?= e($durationLabel) ?></span>
                </div>
            </div>
        </div>

        <!-- 3. Guard Capacity Card -->
        <div class="sub-card">
            <div class="sub-card-header">
                <h2 class="sub-card-title">
                    <svg width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Guard Capacity</span>
                </h2>
                <span style="font-size: 0.75rem; font-weight: 700; color: <?= $activeGuards >= $guardLimit ? '#dc2626' : '#2563eb' ?>;">
                    <?= $availableSlots ?> Available
                </span>
            </div>

            <!-- Capacity Usage Meter -->
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.4rem;">
                    <div>
                        <span style="font-size: 1.5rem; font-weight: 800; color: <?= $activeGuards >= $guardLimit ? '#dc2626' : '#0f172a' ?>;">
                            <?= $activeGuards ?>
                        </span>
                        <span style="font-size: 0.8125rem; font-weight: 600; color: #64748b;"> / <?= $guardLimit ?> Guards</span>
                    </div>
                    <span style="font-size: 0.8125rem; font-weight: 700; color: #334155;"><?= $usagePercent ?>%</span>
                </div>

                <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                    <div style="width: <?= $usagePercent ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 9999px;"></div>
                </div>
            </div>

            <div class="sub-info-list" style="padding-top: 0.5rem; border-top: 1px solid #f1f5f9;">
                <div class="sub-info-row">
                    <span class="sub-info-label">Guard Limit</span>
                    <span class="sub-info-value"><?= $guardLimit ?> Guards</span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Active Guards</span>
                    <span class="sub-info-value"><?= $activeGuards ?> Guards</span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Available Guards</span>
                    <span class="sub-info-value" style="color: #059669; font-weight: 700;">
                        <?= $availableSlots ?> Guards
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- BOTTOM ROW: 2-Column Desktop Grid (Billing Information & Subscription History) -->
    <div class="sub-grid-bottom">
        <!-- 4. Billing Information Card (Strictly NO Invoices) -->
        <div class="sub-card">
            <div class="sub-card-header">
                <h2 class="sub-card-title">
                    <svg width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Billing Information</span>
                </h2>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                    Currency: <strong><?= e($currency) ?></strong>
                </span>
            </div>

            <div class="sub-info-list">
                <div class="sub-info-row">
                    <span class="sub-info-label">Price Per Guard</span>
                    <span class="sub-info-value">₹<?= number_format((float)$sub['price_per_guard'], 2) ?></span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Current Guard Count</span>
                    <span class="sub-info-value"><?= $guardLimit ?></span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Current Subscription Amount</span>
                    <span class="sub-info-value" style="color: #2563eb; font-size: 1.125rem; font-weight: 800;">
                        ₹<?= number_format((float)$sub['total_amount'], 2) ?>
                    </span>
                </div>
                <div class="sub-info-row">
                    <span class="sub-info-label">Billing Cycle</span>
                    <span class="sub-info-value">Annual</span>
                </div>
                <div class="sub-info-row" style="padding-top: 0.5rem; border-top: 1px dashed #e2e8f0; margin-top: 0.25rem;">
                    <span class="sub-info-label">Associated Invoice</span>
                    <span class="sub-info-value">
                        <?php if (!empty($subInvoice)): ?>
                            <a href="<?= url('/superadmin/invoices/' . $subInvoice['id']) ?>" style="color: #2563eb; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                <span><?= e($subInvoice['invoice_number']) ?></span>
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m-7-7l7 7-7 7"/></svg>
                            </a>
                        <?php else: ?>
                            <a href="<?= url('/superadmin/invoices?q=' . urlencode($sub['organization_name'])) ?>" style="color: #2563eb; font-weight: 600; text-decoration: none;">
                                View in Invoices &rarr;
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 5. Subscription History Card -->
        <div class="sub-card">
            <div class="sub-card-header">
                <h2 class="sub-card-title">
                    <svg width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Subscription History</span>
                </h2>
                <span style="font-size: 0.75rem; color: #64748b;">Event Timeline</span>
            </div>

            <?php if (empty($history)): ?>
                <div style="padding: 2rem 1rem; text-align: center; color: #94a3b8; font-size: 0.8125rem;">
                    No subscription history available.
                </div>
            <?php else: ?>
                <div class="sa-table-responsive" style="max-height: 240px; overflow-y: auto;">
                    <table class="sa-history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Guards</th>
                                <th>Subscription</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <?php
                                    $typeLabel = ucfirst(str_replace('_', ' ', $h['change_type']));
                                    $guardsLabel = (int)$h['new_guard_limit'];
                                    if ($h['previous_guard_limit'] !== null && $h['previous_guard_limit'] != $h['new_guard_limit']) {
                                        $guardsLabel = (int)$h['previous_guard_limit'] . ' &rarr; ' . (int)$h['new_guard_limit'];
                                    }
                                    $remarks = !empty($h['notes']) ? $h['notes'] : $typeLabel;
                                    $isCur = (int)$h['subscription_id'] === $subId;
                                ?>
                                <tr>
                                    <td style="color: #475569; font-size: 0.775rem; white-space: nowrap;">
                                        <?= format_date($h['created_at']) ?>
                                    </td>
                                    <td style="font-weight: 600; color: #0f172a; white-space: nowrap;">
                                        <?= e($typeLabel) ?>
                                    </td>
                                    <td style="font-weight: 700; color: #334155; white-space: nowrap;">
                                        <?= $guardsLabel ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 0.775rem; white-space: nowrap;">
                                        <a href="<?= url('/superadmin/subscriptions/' . $h['subscription_id']) ?>" style="color: <?= $isCur ? '#059669; font-weight: 800;' : '#2563eb;' ?> text-decoration: none;">
                                            #SUB-<?= (int)$h['subscription_id'] ?> <?= $isCur ? '★' : '' ?>
                                        </a>
                                    </td>
                                    <td style="color: #64748b; font-size: 0.75rem;">
                                        <?= e($remarks) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
