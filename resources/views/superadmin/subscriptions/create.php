<?php
/**
 * Superadmin Create Subscription View
 * Redesigned Layout:
 * - Full available content width with modern two-column responsive CSS Grid (Left: 60-65%, Right: 35-40%)
 * - Page Header: Back button, Title & Subtitle
 * - Left Column:
 *   [1] Organisation (Select dropdown, informative banner)
 *   [2] Subscription Period (Start & End dates, dynamic Duration & Remaining Days tiles)
 *   [3] Guard Quota & Pricing (Licensed Guard Quota & Price Per Guard inputs)
 * - Right Column:
 *   - Subscription Summary (Live dynamic table of selections & highlighted Total Subscription Amount)
 *   - Administrative Notes (Textarea for internal notes)
 *   - Action Buttons ([ Cancel ] and [ + Create Subscription ])
 */
$organizations = $organizations ?? [];
$defaultPrice = (float)($defaultPrice ?? 500.00);
$currency = $currency ?? 'INR';
$preselectedOrgId = (int)($preselectedOrgId ?? 0);
?>

<style>
/* ==========================================================================
   Superadmin Create Subscription Styling (Two-Column SaaS Form Grid)
   ========================================================================== */
.sub-create-container {
    width: 100%;
    margin: 0;
}

/* Two-column layout on Desktop (62% Form / 38% Summary & Notes) */
.sub-create-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.62fr) minmax(0, 1fr);
    gap: 1.5rem;
    align-items: start;
}

@media (max-width: 992px) {
    .sub-create-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
}

/* White Modular Cards */
.sub-create-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.4rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
    margin-bottom: 1.25rem;
}

.sub-create-card:last-child {
    margin-bottom: 0;
}

/* Section Header with Blue Numbered Circle */
.sub-card-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1.15rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.sub-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    border: 1.5px solid #bfdbfe;
    font-size: 0.785rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 1px;
}

.sub-card-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 0.2rem 0;
    letter-spacing: -0.01em;
}

.sub-card-desc {
    font-size: 0.785rem;
    color: #64748b;
    margin: 0;
}

/* Form Controls */
.sub-form-group {
    margin-bottom: 1rem;
}

.sub-form-group:last-child {
    margin-bottom: 0;
}

.sub-field-label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.4rem;
}

.sub-field-input {
    width: 100%;
    height: 38px;
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

.sub-field-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.sub-field-hint {
    font-size: 0.725rem;
    color: #64748b;
    margin-top: 0.35rem;
    display: block;
    line-height: 1.35;
}

/* Info Banner in Card 1 */
.sub-info-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.75rem 0.95rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    font-size: 0.775rem;
    color: #1e40af;
    margin-top: 0.85rem;
    line-height: 1.4;
}

/* Calculated Period Area in Card 2 */
.sub-calc-period-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 1rem;
}

.sub-calc-period-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem 0.95rem;
}

.sub-calc-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    display: block;
}

.sub-calc-val {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    display: block;
    margin: 0.2rem 0;
}

.sub-calc-sub {
    font-size: 0.725rem;
    color: #64748b;
    display: block;
}

/* Right Column Summary Card */
.sub-summary-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.sub-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.8125rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid #f8fafc;
    gap: 0.5rem;
}

.sub-summary-label {
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}

.sub-summary-val {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
    word-break: break-word;
}

.sa-org-code {
    font-family: monospace;
    font-size: 0.725rem;
    font-weight: 700;
    color: #0f172a;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    display: inline-block;
}

/* Highlighted Total Box in Summary */
.sub-summary-total-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 0.95rem 1rem;
    margin-top: 1rem;
    text-align: center;
}

.sub-summary-total-label {
    font-size: 0.725rem;
    font-weight: 600;
    color: #1e40af;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    display: block;
}

.sub-summary-total-formula {
    font-size: 0.775rem;
    color: #3b82f6;
    margin: 0.25rem 0;
    font-weight: 500;
}

.sub-summary-total-val {
    font-size: 1.45rem;
    font-weight: 800;
    color: #1d4ed8;
    letter-spacing: -0.02em;
}

/* Administrative Notes Textarea */
.sub-textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 400;
    color: #0f172a;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    resize: vertical;
    box-sizing: border-box;
    font-family: inherit;
    line-height: 1.4;
}

.sub-textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Action Buttons */
.sub-actions-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.25rem;
}
</style>

<div class="page-container">
    <div class="sub-create-container">
        <!-- Back Navigation -->
        <a href="<?= url('/superadmin/subscriptions') ?>" class="form-back-nav" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; font-size: 0.8125rem; font-weight: 500; text-decoration: none; margin-bottom: 1.25rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            <span>Back to Subscriptions</span>
        </a>

        <!-- Page Header -->
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-header-title" style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; letter-spacing: -0.02em;">
                Create Organisation Subscription
            </h1>
            <p class="page-header-desc" style="font-size: 0.845rem; color: #64748b; margin: 0;">
                Provision an authoritative guard licensing subscription for an existing customer organisation.
            </p>
        </div>

        <?php App\Core\View::component('components/alerts'); ?>

        <form method="POST" action="<?= url('/superadmin/subscriptions/create') ?>" id="createSubForm">
            <?= csrf_field() ?>

            <div class="sub-create-grid">
                <!-- ========================================================
                     LEFT COLUMN: MAIN SUBSCRIPTION FORM (62%)
                     ======================================================== -->
                <div class="sub-create-left">
                    <!-- SECTION 1 — ORGANISATION -->
                    <div class="sub-create-card">
                        <div class="sub-card-header">
                            <span class="sub-step-num">1</span>
                            <div>
                                <h2 class="sub-card-title">Organisation</h2>
                                <p class="sub-card-desc">Select the existing organisation for this subscription.</p>
                            </div>
                        </div>

                        <div class="sub-form-group">
                            <label class="sub-field-label" for="subOrgId">
                                Target Organisation <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="organization_id" id="subOrgId" class="sub-field-input" required onchange="updateLiveSummary()" style="font-weight: 600;">
                                <option value="">-- Select Existing Organisation --</option>
                                <?php foreach ($organizations as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>"
                                            data-name="<?= e($o['name']) ?>"
                                            data-code="<?= e($o['organization_code']) ?>"
                                            <?= $preselectedOrgId === (int)$o['id'] ? 'selected' : '' ?>>
                                        <?= e($o['name']) ?> (<?= e($o['organization_code']) ?>) <?= !empty($o['has_active_sub']) ? '— [Active Sub #SUB-' . $o['current_sub_id'] . ']' : '— [No Active Subscription]' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="sub-info-banner">
                                <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                                <span>Subscriptions belong to an existing organisation. To add a new tenant organisation first, use the Organisations module.</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 — SUBSCRIPTION PERIOD -->
                    <div class="sub-create-card">
                        <div class="sub-card-header">
                            <span class="sub-step-num">2</span>
                            <div>
                                <h2 class="sub-card-title">Subscription Period</h2>
                                <p class="sub-card-desc">Set the subscription start and end dates.</p>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="sub-form-group">
                                <label class="sub-field-label" for="subStartDate">
                                    Start Date <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="start_date" id="subStartDate" class="sub-field-input" value="<?= date('Y-m-d') ?>" required onchange="updateLiveSummary()">
                            </div>

                            <div class="sub-form-group">
                                <label class="sub-field-label" for="subEndDate">
                                    End Date (Expiry) <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="end_date" id="subEndDate" class="sub-field-input" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required onchange="updateLiveSummary()">
                            </div>
                        </div>

                        <!-- Compact Calculated Period Area -->
                        <div class="sub-calc-period-grid">
                            <div class="sub-calc-period-box">
                                <span class="sub-calc-label">Duration</span>
                                <span class="sub-calc-val" id="displayDuration">1 Year</span>
                                <span class="sub-calc-sub" id="displayDurationDays">365 days</span>
                            </div>
                            <div class="sub-calc-period-box">
                                <span class="sub-calc-label">Remaining Days</span>
                                <span class="sub-calc-val" id="displayRemainingDays">365 days</span>
                                <span class="sub-calc-sub">from start date</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3 — GUARD QUOTA & PRICING -->
                    <div class="sub-create-card">
                        <div class="sub-card-header">
                            <span class="sub-step-num">3</span>
                            <div>
                                <h2 class="sub-card-title">Guard Quota & Pricing</h2>
                                <p class="sub-card-desc">Define the licensed guard capacity and pricing.</p>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="sub-form-group">
                                <label class="sub-field-label" for="subGuardLimit">
                                    Licensed Guard Quota <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="number" name="guard_limit" id="subGuardLimit" class="sub-field-input" value="30" min="1" required oninput="updateLiveSummary()">
                                <span class="sub-field-hint">Maximum number of guards allowed under this subscription.</span>
                            </div>

                            <div class="sub-form-group">
                                <label class="sub-field-label" for="subPricePerGuard">
                                    Price Per Guard (₹) <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="number" name="price_per_guard" id="subPricePerGuard" class="sub-field-input" value="<?= number_format($defaultPrice, 2, '.', '') ?>" min="0" step="0.01" required oninput="updateLiveSummary()">
                                <span class="sub-field-hint">Defaulted from system settings. Saved immutably with this subscription.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================
                     RIGHT COLUMN: LIVE SUMMARY + NOTES + ACTIONS (38%)
                     ======================================================== -->
                <div class="sub-create-right">
                    <!-- SUBSCRIPTION SUMMARY CARD -->
                    <div class="sub-create-card">
                        <div style="margin-bottom: 1rem; padding-bottom: 0.65rem; border-bottom: 1px solid #f1f5f9;">
                            <h2 style="font-size: 0.9375rem; font-weight: 700; color: #0f172a; margin: 0 0 0.2rem 0;">
                                Subscription Summary
                            </h2>
                            <p style="font-size: 0.785rem; color: #64748b; margin: 0;">
                                Review the details before creating the subscription.
                            </p>
                        </div>

                        <div class="sub-summary-list">
                            <div class="sub-summary-row">
                                <span class="sub-summary-label">Organisation</span>
                                <span class="sub-summary-val" id="summaryOrgName">Not selected</span>
                            </div>
                            <div class="sub-summary-row">
                                <span class="sub-summary-label">Start Date</span>
                                <span class="sub-summary-val" id="summaryStartDate">—</span>
                            </div>
                            <div class="sub-summary-row">
                                <span class="sub-summary-label">End Date</span>
                                <span class="sub-summary-val" id="summaryEndDate">—</span>
                            </div>
                            <div class="sub-summary-row">
                                <span class="sub-summary-label">Duration</span>
                                <span class="sub-summary-val" id="summaryDuration">—</span>
                            </div>
                            <div class="sub-summary-row">
                                <span class="sub-summary-label">Guard Quota</span>
                                <span class="sub-summary-val" id="summaryGuardQuota">30</span>
                            </div>
                            <div class="sub-summary-row">
                                <span class="sub-summary-label">Price Per Guard</span>
                                <span class="sub-summary-val" id="summaryPricePerGuard">₹<?= number_format($defaultPrice, 2) ?></span>
                            </div>
                        </div>

                        <!-- Highlighted Total Amount -->
                        <div class="sub-summary-total-box">
                            <span class="sub-summary-total-label">Total Subscription Amount</span>
                            <div class="sub-summary-total-formula" id="summaryFormula">
                                30 Guards × ₹<?= number_format($defaultPrice, 2) ?>
                            </div>
                            <div class="sub-summary-total-val" id="summaryTotalAmount">
                                ₹<?= number_format(30 * $defaultPrice, 2) ?>
                            </div>
                        </div>
                    </div>

                    <!-- ADMINISTRATIVE NOTES CARD -->
                    <div class="sub-create-card">
                        <div style="margin-bottom: 0.85rem; padding-bottom: 0.65rem; border-bottom: 1px solid #f1f5f9;">
                            <h2 style="font-size: 0.9125rem; font-weight: 700; color: #0f172a; margin: 0 0 0.15rem 0;">
                                Administrative Notes
                            </h2>
                            <p style="font-size: 0.775rem; color: #64748b; margin: 0;">
                                Optional notes regarding this subscription agreement.
                            </p>
                        </div>

                        <div class="sub-form-group">
                            <textarea name="notes" id="subNotes" class="sub-textarea" rows="3" placeholder="Add any internal notes, reference or special terms..."></textarea>
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="sub-actions-bar">
                        <a href="<?= url('/superadmin/subscriptions') ?>" class="btn btn-secondary" style="font-weight: 600; text-decoration: none; padding: 0.55rem 1.25rem; font-size: 0.8125rem; border-radius: 8px;">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnCreateSub" style="display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 600; padding: 0.55rem 1.5rem; font-size: 0.8125rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(37,99,235,0.25);">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                            <span> Create Subscription</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Live Subscription Summary & Period Calculations
 */
function formatDisplayDate(dateStr) {
    if (!dateStr) return '—';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    if (isNaN(d.getTime())) return dateStr;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const day = String(d.getDate()).padStart(2, '0');
    const mon = months[d.getMonth()];
    const year = d.getFullYear();
    return `${day} ${mon} ${year}`;
}

function updateLiveSummary() {
    // 1. Target Organisation
    const orgSelect = document.getElementById('subOrgId');
    const selectedOption = orgSelect.options[orgSelect.selectedIndex];
    const orgNameElem = document.getElementById('summaryOrgName');

    if (selectedOption && selectedOption.value) {
        const orgName = selectedOption.getAttribute('data-name') || selectedOption.text.split('(')[0].trim();
        const orgCode = selectedOption.getAttribute('data-code');
        orgNameElem.innerHTML = `<strong>${orgName}</strong>` + (orgCode ? ` <span class="sa-org-code">${orgCode}</span>` : '');
    } else {
        orgNameElem.innerText = 'Not selected';
    }

    // 2. Start Date & End Date
    const startInput = document.getElementById('subStartDate');
    const endInput = document.getElementById('subEndDate');
    const startDateVal = startInput.value;
    const endDateVal = endInput.value;

    document.getElementById('summaryStartDate').innerText = formatDisplayDate(startDateVal);
    document.getElementById('summaryEndDate').innerText = formatDisplayDate(endDateVal);

    let durationStr = '—';
    let durationDaysStr = '—';
    let remainingDaysStr = '—';

    if (startDateVal && endDateVal) {
        const startParts = startDateVal.split('-').map(Number);
        const endParts = endDateVal.split('-').map(Number);
        const dStart = new Date(startParts[0], startParts[1] - 1, startParts[2]);
        const dEnd = new Date(endParts[0], endParts[1] - 1, endParts[2]);

        const diffTime = dEnd.getTime() - dStart.getTime();
        const diffDays = Math.max(0, Math.round(diffTime / (1000 * 60 * 60 * 24)));

        let periodLabel = '';
        if (diffDays >= 360) {
            const years = Math.round(diffDays / 365);
            periodLabel = years + ' Year' + (years > 1 ? 's' : '');
        } else if (diffDays >= 28) {
            const months = Math.round(diffDays / 30);
            periodLabel = months + ' Month' + (months > 1 ? 's' : '');
        } else {
            periodLabel = diffDays + ' Day' + (diffDays !== 1 ? 's' : '');
        }

        durationStr = periodLabel;
        durationDaysStr = diffDays + ' days';
        remainingDaysStr = diffDays + ' days';

        document.getElementById('summaryDuration').innerText = `${periodLabel} (${diffDays} days)`;
    } else {
        document.getElementById('summaryDuration').innerText = '—';
    }

    // Update calculated period boxes in Card [2]
    const dispDuration = document.getElementById('displayDuration');
    const dispDurationDays = document.getElementById('displayDurationDays');
    const dispRemaining = document.getElementById('displayRemainingDays');
    if (dispDuration) dispDuration.innerText = durationStr;
    if (dispDurationDays) dispDurationDays.innerText = durationDaysStr;
    if (dispRemaining) dispRemaining.innerText = remainingDaysStr;

    // 3. Guard Quota & Pricing
    const guards = Math.max(1, parseInt(document.getElementById('subGuardLimit').value, 10) || 0);
    const price = Math.max(0, parseFloat(document.getElementById('subPricePerGuard').value) || 0);
    const total = guards * price;

    document.getElementById('summaryGuardQuota').innerText = guards;
    document.getElementById('summaryPricePerGuard').innerText = '₹' + price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // 4. Highlighted Total Box
    document.getElementById('summaryFormula').innerText = `${guards} Guards × ₹${price.toFixed(2)}`;
    document.getElementById('summaryTotalAmount').innerText = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Client-side validation
document.getElementById('createSubForm').addEventListener('submit', function(e) {
    const orgSelect = document.getElementById('subOrgId');
    if (!orgSelect.value) {
        alert('Please select a target organisation.');
        orgSelect.focus();
        e.preventDefault();
        return false;
    }

    const startVal = document.getElementById('subStartDate').value;
    const endVal = document.getElementById('subEndDate').value;
    if (startVal && endVal && new Date(endVal) <= new Date(startVal)) {
        alert('End date (expiry) must be after the start date.');
        document.getElementById('subEndDate').focus();
        e.preventDefault();
        return false;
    }

    return true;
});

document.addEventListener('DOMContentLoaded', () => {
    updateLiveSummary();
});
</script>
