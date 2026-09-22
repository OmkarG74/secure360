<?php
/**
 * Superadmin Edit Subscription View
 * Redesigned Layout:
 * - Header: Back button, Title & Subtitle, Status Badge
 * - Top Row (3-Column Desktop Grid):
 *   1. Organisation (Read-only: Name, Code, Admin Name, Email, Phone)
 *   2. Subscription (Start Date [read-only], Expiry Date [editable], Status [badge], Duration info)
 *   3. Guard Capacity (Guard Limit [editable], Active Guards [read-only], Available Guards [live], Progress bar)
 * - Bottom Row (Full Width Card):
 *   4. Billing Information (Price Per Guard [input], Current Guard Count [calculated], Subscription Value [calculated], Delta Notice)
 * - Bottom Right: [ Cancel ] [ Save Changes ]
 */
$sub = $sub ?? [];
$subId = (int)($sub['id'] ?? 0);
$primaryAdmin = $primaryAdmin ?? null;
$activeGuards = (int)($activeGuards ?? 0);
$currency = $currency ?? 'INR';

$guardLimit = (int)($sub['guard_limit'] ?? 0);
$pricePerGuard = (float)($sub['price_per_guard'] ?? 500);
$totalAmount = (float)($sub['total_amount'] ?? 0);
$startDate = $sub['start_date'] ?? date('Y-m-d');
$endDate = $sub['end_date'] ?? date('Y-m-d');
$statusKey = $sub['calculated_status'] ?? 'active';

$availableSlots = max(0, $guardLimit - $activeGuards);
$usagePercent = $guardLimit > 0 ? min(100, round(($activeGuards / $guardLimit) * 100)) : 100;

// Duration calculation
$startTs = strtotime($startDate);
$endTs = strtotime($endDate);
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

// Progress bar color
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
?>

<style>
/* ==========================================================================
   Superadmin Edit Subscription Styling (Full-Width Responsive Dashboard)
   ========================================================================== */
.sub-edit-container {
    width: 100%;
    margin: 0;
}

/* 3-Column Top Grid on Desktop */
.sub-edit-grid-top {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

@media (max-width: 1100px) {
    .sub-edit-grid-top {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .sub-edit-grid-top {
        grid-template-columns: 1fr;
    }
}

.sub-edit-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.35rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
}

.sub-edit-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.sub-edit-card-title {
    font-size: 0.9125rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.sub-edit-card-title svg {
    color: #2563eb;
    flex-shrink: 0;
}

/* Organisation Read-Only Rows */
.sub-readonly-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sub-readonly-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.8125rem;
    gap: 0.75rem;
}

.sub-readonly-label {
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}

.sub-readonly-val {
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

/* Status Badges */
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

/* Form Controls */
.sub-edit-field {
    margin-bottom: 0.85rem;
}

.sub-edit-field:last-child {
    margin-bottom: 0;
}

.sub-edit-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.sub-edit-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.845rem;
    font-weight: 500;
    color: #0f172a;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    box-sizing: border-box;
}

.sub-edit-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.sub-edit-input[readonly] {
    background-color: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
    cursor: not-allowed;
}

/* Guard Progress Bar */
.sub-progress-bar-wrap {
    width: 100%;
    height: 8px;
    background: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
    margin: 0.6rem 0;
}

.sub-progress-bar-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.3s ease, background-color 0.3s ease;
}

/* Billing Grid */
.sub-billing-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    gap: 1.25rem;
    align-items: center;
}

@media (max-width: 768px) {
    .sub-billing-grid {
        grid-template-columns: 1fr;
    }
}

.sub-metric-tile {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.85rem 1rem;
    text-align: center;
}

.sub-metric-tile .tile-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.25rem;
    display: block;
}

.sub-metric-tile .tile-val {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
}
</style>

<div class="page-container">
    <div class="sub-edit-container">
        <!-- Back Navigation & Action Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <a href="<?= url('/superadmin/subscriptions/' . $subId) ?>" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.85rem; border-radius: 6px;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                <span>Back to Subscriptions</span>
            </a>

            <div>
                <?php if ($statusKey === 'active'): ?>
                    <span class="sa-status-badge active"><span class="badge-dot"></span> Active</span>
                <?php elseif ($statusKey === 'expiring_soon'): ?>
                    <span class="sa-status-badge expiring"><span class="badge-dot"></span> Expiring Soon</span>
                <?php elseif ($statusKey === 'expired'): ?>
                    <span class="sa-status-badge expired"><span class="badge-dot"></span> Expired</span>
                <?php else: ?>
                    <span class="sa-status-badge suspended"><span class="badge-dot"></span> Suspended</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page Header -->
        <div style="margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; letter-spacing: -0.02em;">
                Edit Subscription
            </h1>
            <p style="font-size: 0.845rem; color: #64748b; margin: 0;">
                Update subscription details and guard capacity.
            </p>
        </div>

        <?php App\Core\View::component('components/alerts'); ?>

        <form method="POST" action="<?= url('/superadmin/subscriptions/' . $subId . '/edit') ?>" id="editSubForm" onsubmit="return validateForm(event)">
            <?= csrf_field() ?>

            <!-- TOP ROW: 3-COLUMN DESKTOP GRID -->
            <div class="sub-edit-grid-top">
                <!-- CARD 1: ORGANISATION (READ-ONLY) -->
                <div class="sub-edit-card">
                    <div class="sub-edit-card-header">
                        <h2 class="sub-edit-card-title">
                            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
                            <span>Organisation</span>
                        </h2>
                        <span style="font-size: 0.725rem; font-weight: 600; color: #94a3b8; background: #f8fafc; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0;">
                            Read-only
                        </span>
                    </div>

                    <div class="sub-readonly-list">
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Organisation Name</span>
                            <span class="sub-readonly-val" style="font-size: 0.875rem; color: #0f172a;"><?= e($sub['organization_name']) ?></span>
                        </div>
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Organisation Code</span>
                            <span class="sub-readonly-val"><span class="sa-org-code"><?= e($sub['organization_code']) ?></span></span>
                        </div>
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Admin Name</span>
                            <span class="sub-readonly-val"><?= e($adminName) ?></span>
                        </div>
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Email</span>
                            <span class="sub-readonly-val"><?= e($adminEmail) ?></span>
                        </div>
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Phone</span>
                            <span class="sub-readonly-val"><?= e($adminPhone) ?></span>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: SUBSCRIPTION DETAILS -->
                <div class="sub-edit-card">
                    <div class="sub-edit-card-header">
                        <h2 class="sub-edit-card-title">
                            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Subscription</span>
                        </h2>
                        <span style="font-size: 0.725rem; font-weight: 600; color: #64748b;">
                            <?= e($durationLabel) ?>
                        </span>
                    </div>

                    <div class="sub-readonly-list" style="margin-bottom: 0.85rem;">
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Status</span>
                            <span class="sub-readonly-val">
                                <?php if ($statusKey === 'active'): ?>
                                    <span class="sa-status-badge active"><span class="badge-dot"></span> Active</span>
                                <?php elseif ($statusKey === 'expiring_soon'): ?>
                                    <span class="sa-status-badge expiring"><span class="badge-dot"></span> Expiring Soon</span>
                                <?php elseif ($statusKey === 'expired'): ?>
                                    <span class="sa-status-badge expired"><span class="badge-dot"></span> Expired</span>
                                <?php else: ?>
                                    <span class="sa-status-badge suspended"><span class="badge-dot"></span> Suspended</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Start Date</span>
                            <span class="sub-readonly-val"><?= format_date($startDate) ?></span>
                        </div>
                    </div>

                    <div class="sub-edit-field">
                        <label class="sub-edit-label" for="newEndDate">
                            Expiry Date <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="date" name="new_end_date" id="newEndDate" class="sub-edit-input" value="<?= $endDate ?>" min="<?= $endDate ?>" required onchange="calculateSummary()">
                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.35rem;">
                            Current Expiry: <strong><?= format_date($endDate) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- CARD 3: GUARD CAPACITY -->
                <div class="sub-edit-card">
                    <div class="sub-edit-card-header">
                        <h2 class="sub-edit-card-title">
                            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>Guard Capacity</span>
                        </h2>
                        <span id="capacityPercentBadge" style="font-size: 0.75rem; font-weight: 700; color: #2563eb;">
                            <?= $usagePercent ?>% used
                        </span>
                    </div>

                    <div class="sub-edit-field">
                        <label class="sub-edit-label" for="guardLimit">
                            Guard Limit <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="number" name="guard_limit" id="guardLimit" class="sub-edit-input" value="<?= $guardLimit ?>" min="<?= max(1, $activeGuards) ?>" required oninput="calculateSummary()">
                    </div>

                    <div class="sub-progress-bar-wrap">
                        <div id="capacityProgressBar" class="sub-progress-bar-fill" style="width: <?= $usagePercent ?>%; background-color: <?= $barColor ?>;"></div>
                    </div>

                    <div class="sub-readonly-list" style="margin-top: 0.25rem;">
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Active Guards (Roster)</span>
                            <span class="sub-readonly-val" id="activeGuardsDisplay"><?= $activeGuards ?></span>
                        </div>
                        <div class="sub-readonly-row">
                            <span class="sub-readonly-label">Available Guard Slots</span>
                            <span class="sub-readonly-val" id="availableGuardsVal" style="color: #059669;"><?= $availableSlots ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM SECTION: BILLING INFORMATION (FULL WIDTH CARD) -->
            <div class="sub-edit-card" style="margin-bottom: 1.5rem;">
                <div class="sub-edit-card-header">
                    <h2 class="sub-edit-card-title">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>Billing Information</span>
                    </h2>
                    <span style="font-size: 0.75rem; color: #64748b;">
                        Billing Cycle: <strong><?= e(ucfirst($sub['billing_cycle'] ?? 'Annual')) ?></strong>
                    </span>
                </div>

                <div class="sub-billing-grid">
                    <!-- Price Per Guard Input -->
                    <div>
                        <label class="sub-edit-label" for="pricePerGuard">
                            Price Per Guard (₹) <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="number" name="price_per_guard" id="pricePerGuard" class="sub-edit-input" value="<?= number_format($pricePerGuard, 2, '.', '') ?>" min="0" step="0.01" required oninput="calculateSummary()">
                        <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.35rem; display: block;">
                            Contracted rate per guard applied to this subscription.
                        </span>
                    </div>

                    <!-- Current Guard Count Metric Tile -->
                    <div class="sub-metric-tile">
                        <span class="tile-label">Current Guard Count</span>
                        <div class="tile-val" id="summaryGuardLimit"><?= $guardLimit ?></div>
                        <span style="font-size: 0.7rem; color: #64748b;">licensed capacity limit</span>
                    </div>

                    <!-- Calculated Subscription Value Metric Tile -->
                    <div class="sub-metric-tile" style="border-color: #bfdbfe; background: #eff6ff;">
                        <span class="tile-label" style="color: #1d4ed8;">Subscription Value</span>
                        <div class="tile-val" id="summaryTotal" style="color: #1e40af;">₹<?= number_format($totalAmount, 2) ?></div>
                        <span style="font-size: 0.7rem; color: #3b82f6;">calculated rate × guards</span>
                    </div>
                </div>

                <!-- Dynamic Delta Notification -->
                <div id="deltaNotice" style="margin-top: 1.25rem; padding: 0.85rem 1.15rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8125rem; color: #475569;">
                    No pending capacity or duration changes detected.
                </div>
            </div>

            <!-- FORM ACTIONS (BOTTOM RIGHT) -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
                <a href="<?= url('/superadmin/subscriptions/' . $subId) ?>" class="btn btn-secondary" style="font-weight: 600; text-decoration: none; padding: 0.55rem 1.35rem; font-size: 0.8125rem; border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="btnSubmitSub" style="display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 600; padding: 0.55rem 1.5rem; font-size: 0.8125rem; border-radius: 8px; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const baseLimit = <?= $guardLimit ?>;
const basePrice = <?= $pricePerGuard ?>;
const activeGuards = <?= $activeGuards ?>;
const currentEndDate = new Date('<?= $endDate ?>');

function calculateSummary() {
    const limitInput = document.getElementById('guardLimit');
    const priceInput = document.getElementById('pricePerGuard');
    const endDateInput = document.getElementById('newEndDate');

    const limit = parseInt(limitInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const totalVal = limit * price;

    // Update Guard Metrics
    const available = Math.max(0, limit - activeGuards);
    const availableElem = document.getElementById('availableGuardsVal');
    if (availableElem) {
        availableElem.innerText = available;
    }

    const usagePercent = limit > 0 ? Math.min(100, Math.round((activeGuards / limit) * 100)) : 100;
    const bar = document.getElementById('capacityProgressBar');
    const badge = document.getElementById('capacityPercentBadge');
    if (bar) {
        bar.style.width = usagePercent + '%';
        if (usagePercent >= 100 || activeGuards >= limit) {
            bar.style.backgroundColor = '#ef4444';
        } else if (usagePercent >= 80) {
            bar.style.backgroundColor = '#f59e0b';
        } else {
            bar.style.backgroundColor = '#2563eb';
        }
    }
    if (badge) {
        badge.innerText = usagePercent + '% used';
    }

    // Update Billing Tiles
    document.getElementById('summaryGuardLimit').innerText = limit;
    document.getElementById('summaryTotal').innerText = '₹' + totalVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const diffGuards = limit - baseLimit;
    const newEnd = endDateInput.value ? new Date(endDateInput.value) : currentEndDate;
    const isDurationExtended = newEnd > currentEndDate;

    const notice = document.getElementById('deltaNotice');
    let messages = [];

    if (diffGuards > 0) {
        const extraCharge = diffGuards * price;
        messages.push('<strong>Capacity Upgrade:</strong> +' + diffGuards + ' additional guards (Incremental invoice: ₹' + extraCharge.toFixed(2) + ').');
    } else if (diffGuards < 0) {
        messages.push('<strong>Capacity Reduction:</strong> -' + Math.abs(diffGuards) + ' guards. No incremental charge.');
    }

    if (isDurationExtended) {
        const diffTime = newEnd - currentEndDate;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const months = Math.max(1, Math.ceil(diffDays / 30));
        const durCharge = months * limit * price;
        messages.push('<strong>Duration Extension:</strong> +' + months + ' month(s) extended to ' + endDateInput.value + ' (Incremental extension charge: ₹' + durCharge.toFixed(2) + ').');
    }

    if (messages.length > 0) {
        notice.innerHTML = messages.join('<br>');
        notice.style.background = '#eff6ff';
        notice.style.borderColor = '#bfdbfe';
        notice.style.color = '#1e40af';
    } else {
        notice.innerHTML = 'No pending capacity or duration changes detected.';
        notice.style.background = '#f8fafc';
        notice.style.borderColor = '#e2e8f0';
        notice.style.color = '#475569';
    }
}

function validateForm(e) {
    const limit = parseInt(document.getElementById('guardLimit').value) || 0;
    if (limit < activeGuards) {
        alert('Guard limit cannot be lower than the current active guard count (' + activeGuards + ').');
        e.preventDefault();
        return false;
    }

    const endDateInput = document.getElementById('newEndDate');
    if (endDateInput.value) {
        const newEnd = new Date(endDateInput.value);
        if (newEnd < currentEndDate) {
            alert('Expiry date cannot be set earlier than the current expiration date (' + currentEndDate.toISOString().split('T')[0] + ').');
            e.preventDefault();
            return false;
        }
    }

    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    calculateSummary();
});
</script>
