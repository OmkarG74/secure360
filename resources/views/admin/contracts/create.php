<?php
/**
 * Create Service Contract View
 * Secure360 Enterprise SaaS UI: Two balanced vertical cards (Contract Details + Guard Assignments)
 * Strictly matches Register Client design language.
 */
$customers = $customers ?? [];
$sites = $sites ?? [];
$guards = $guards ?? [];
$contractCode = $contractCode ?? 'CTR-' . date('Y') . '-' . rand(100, 999);
$existingAssignments = $existingAssignments ?? [];
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/admin/contracts') ?>" class="form-back-nav">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Contracts</span>
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Create Contract</h1>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <form method="POST" action="<?= url('/admin/contracts/create') ?>" id="contractForm" onsubmit="return validateContractForm()">
        <?= csrf_field() ?>

        <div class="register-layout-grid">
            
            <!-- LEFT COLUMN: Contract Details -->
            <div class="card card-padded register-panel">
                <div class="card-section-header">
                    <div class="card-section-info">
                        <div class="card-section-icon blue">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h2 class="card-section-title">Contract Details</h2>
                        </div>
                    </div>
                </div>

                <div class="register-form-fields">
                    <!-- Client & Contract Number / Code -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Client / Customer <span class="required-star">*</span></label>
                            <select name="customer_id" id="customerSelect" class="form-select" required onchange="onCustomerChange()">
                                <option value="">-- Select Client --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['client_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contract Number / Code</label>
                            <input type="text" name="contract_code" class="form-control" value="<?= e($contractCode) ?>" readonly style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: 600; background: #f8fafc;">
                        </div>
                    </div>

                    <!-- Start Date & End Date -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Start Date <span class="required-star">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                        </div>
                    </div>

                    <!-- Required Guards -->
                    <div class="form-group">
                        <label class="form-label">Required Guards <span class="required-star">*</span></label>
                        <input type="number" id="requiredGuardsInput" name="required_guard_count" class="form-control" value="4" min="1" max="100" required oninput="onRequiredGuardsChange()" style="font-weight: 600;">
                    </div>

                    <!-- Contract Notes / Special Instructions -->
                    <div class="form-group">
                        <label class="form-label">Contract Notes / Special Instructions</label>
                        <textarea name="extra_notes" class="form-control" rows="3" placeholder="24/7 security, night patrol, access control, client requests, etc."></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Guard Assignments -->
            <div class="card card-padded register-panel" style="display: flex; flex-direction: column;">
                <div class="card-section-header">
                    <div class="card-section-info">
                        <div class="card-section-icon blue">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="card-section-title">Guard Assignments</h2>
                        </div>
                    </div>
                    <button type="button" id="btnAddGuardTop" class="btn btn-primary btn-sm" onclick="addGuardRow()" style="white-space: nowrap; height: 36px; padding: 0 1rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        Add Guard Row
                    </button>
                </div>

                <!-- Empty State Placeholder (when 0 rows) -->
                <div id="emptyRowsPlaceholder" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem 1.5rem; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; margin-bottom: 1rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <div style="font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No guard assignments added yet</div>
                    <div style="font-size: 0.75rem; color: #64748b;">Click <strong>Add Guard Row</strong> above to allocate available guards to this contract.</div>
                </div>

                <!-- Dynamic Guard Assignment Cards Container -->
                <div id="guardAssignmentsContainer" style="display: flex; flex-direction: column; gap: 0.875rem; margin-bottom: 1.5rem;">
                    <!-- Rendered by JavaScript -->
                </div>

            </div>

        </div>

        <!-- Form Footer Actions -->
        <div class="form-actions-footer" style="margin-top: 2rem; display: flex; justify-content: flex-end; align-items: center; gap: 1rem;">
            <a href="<?= url('/admin/contracts') ?>" class="btn btn-outline" style="min-width: 110px;">
                Cancel
            </a>
            <button type="submit" id="submitBtn" class="btn btn-primary" style="min-width: 180px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                Create Contract
            </button>
        </div>
    </form>
</div>

<style>
.assignment-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.25rem;
    position: relative;
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04);
}
.assignment-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.875rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px dashed #e2e8f0;
}
.assignment-card-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.assignment-fields-grid {
    display: grid;
    grid-template-columns: 1.35fr 1.15fr 0.85fr 0.85fr 1.15fr;
    gap: 0.65rem;
    align-items: start;
}
@media (max-width: 1200px) and (min-width: 1101px) {
    .assignment-fields-grid {
        grid-template-columns: 1.2fr 1fr 0.85fr 0.85fr 1fr;
    }
}
@media (max-width: 860px) {
    .assignment-fields-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 580px) {
    .assignment-fields-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
const guardsData = <?= json_encode($guards) ?>;
const sitesData = <?= json_encode($sites) ?>;
const initialAssignments = <?= json_encode($existingAssignments) ?>;

const shiftPresets = [
    { name: 'Day Patrol', start: '08:00', end: '16:00' },
    { name: 'Evening Shift', start: '16:00', end: '00:00' },
    { name: 'Night Patrol', start: '00:00', end: '08:00' },
    { name: 'Morning Security', start: '06:00', end: '14:00' },
    { name: 'Gate Duty', start: '09:00', end: '17:00' },
];

function getMaxGuards() {
    const input = document.getElementById('requiredGuardsInput');
    const val = parseInt(input.value, 10);
    return isNaN(val) || val < 1 ? 1 : val;
}

function onRequiredGuardsChange() {
    const max = getMaxGuards();
    const rows = document.querySelectorAll('.assignment-card');
    
    // Trim excess rows if Required Guards decreased below current row count
    if (rows.length > max) {
        for (let i = max; i < rows.length; i++) {
            rows[i].remove();
        }
    }
    
    updateUIState();
}

function onCustomerChange() {
    updateAllRowSiteDropdowns();
}

function updateAllRowSiteDropdowns() {
    const customerId = document.getElementById('customerSelect').value;
    const rowSiteSelects = document.querySelectorAll('.assignment-site-select');

    rowSiteSelects.forEach(sel => {
        const options = sel.querySelectorAll('option');
        let firstMatch = '';
        options.forEach(opt => {
            if (!opt.value) return;
            if (!customerId || opt.getAttribute('data-customer') === customerId) {
                opt.style.display = '';
                if (!firstMatch) firstMatch = opt.value;
            } else {
                opt.style.display = 'none';
            }
        });

        if (sel.selectedOptions[0] && sel.selectedOptions[0].style.display === 'none') {
            sel.value = firstMatch || '';
        }
    });
}

function addGuardRow(presetData = null) {
    const max = getMaxGuards();
    const container = document.getElementById('guardAssignmentsContainer');
    const currentRows = container.querySelectorAll('.assignment-card');
    
    if (currentRows.length >= max) {
        return;
    }

    const rowIndex = currentRows.length;
    const displayNum = rowIndex + 1;
    const preset = shiftPresets[rowIndex % shiftPresets.length];

    const guardVal = presetData && presetData.guard_id !== undefined ? String(presetData.guard_id) : '';
    const shiftVal = presetData && presetData.shift_name ? presetData.shift_name : preset.name;
    let startVal = presetData && presetData.start_time ? presetData.start_time : preset.start;
    let endVal = presetData && presetData.end_time ? presetData.end_time : preset.end;
    if (startVal && startVal.length > 5) startVal = startVal.substring(0, 5);
    if (endVal && endVal.length > 5) endVal = endVal.substring(0, 5);

    const customerId = document.getElementById('customerSelect').value;
    const siteVal = presetData && (presetData.site_id || presetData.assignment_site_id) ? String(presetData.site_id || presetData.assignment_site_id) : '';

    // Guard dropdown options
    let guardOptions = `<option value="" data-base-label="-- Select Guard --">-- Select Guard --</option>`;
    guardsData.forEach(g => {
        const isSelected = String(g.guard_id) === guardVal ? 'selected' : '';
        const statusLabel = g.site_name ? ` (${g.employee_code} • ${g.site_name})` : ` (${g.employee_code})`;
        const baseLabel = `${g.full_name} ${statusLabel}`;
        guardOptions += `<option value="${g.guard_id}" data-base-label="${baseLabel}" ${isSelected}>${baseLabel}</option>`;
    });

    // Site dropdown options
    let siteOptions = `<option value="">-- Select Site --</option>`;
    sitesData.forEach(s => {
        const isSelected = String(s.id) === String(siteVal) ? 'selected' : '';
        const isVisible = (!customerId || String(s.customer_id) === String(customerId)) ? '' : 'style="display:none;"';
        siteOptions += `<option value="${s.id}" data-customer="${s.customer_id}" ${isVisible} ${isSelected}>${s.site_name}</option>`;
    });

    const card = document.createElement('div');
    card.className = 'assignment-card';
    card.innerHTML = `
        <div class="assignment-card-header">
            <span class="assignment-card-title">
                <svg width="15" height="15" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="assignment-card-title-text">Guard Assignment #${displayNum}</span>
            </span>
            <button type="button" class="btn-remove-site" onclick="removeGuardRow(this)" title="Delete Assignment">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </div>
        <div class="assignment-fields-grid">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.75rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Guard <span class="required-star">*</span></label>
                <select name="assignments[${rowIndex}][guard_id]" class="form-select assignment-guard-select" style="height:38px; font-size:0.8125rem;" required onchange="onGuardSelectionChange()">
                    ${guardOptions}
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.75rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Shift Name <span class="required-star">*</span></label>
                <input type="text" name="assignments[${rowIndex}][shift_name]" class="form-control assignment-shift-name" value="${escapeHtml(shiftVal)}" placeholder="Day Patrol" required style="height:38px; font-size:0.8125rem;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.75rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Start Time <span class="required-star">*</span></label>
                <input type="time" name="assignments[${rowIndex}][start_time]" class="form-control assignment-start-time" value="${escapeHtml(startVal)}" required style="height:38px; font-size:0.8125rem;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.75rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">End Time <span class="required-star">*</span></label>
                <input type="time" name="assignments[${rowIndex}][end_time]" class="form-control assignment-end-time" value="${escapeHtml(endVal)}" required style="height:38px; font-size:0.8125rem;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.75rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Duty Site <span class="required-star">*</span></label>
                <select name="assignments[${rowIndex}][site_id]" class="form-select assignment-site-select" style="height:38px; font-size:0.8125rem;" required>
                    ${siteOptions}
                </select>
            </div>
        </div>
    `;

    container.appendChild(card);
    updateUIState();
    onGuardSelectionChange();
}

function removeGuardRow(btn) {
    const card = btn.closest('.assignment-card');
    if (card) {
        card.remove();
        reindexRows();
        updateUIState();
        onGuardSelectionChange();
    }
}

function reindexRows() {
    const container = document.getElementById('guardAssignmentsContainer');
    const rows = container.querySelectorAll('.assignment-card');
    
    rows.forEach((r, idx) => {
        const displayNum = idx + 1;
        r.querySelector('.assignment-card-title-text').textContent = `Guard Assignment #${displayNum}`;
        
        const guardSel = r.querySelector('.assignment-guard-select');
        const shiftInput = r.querySelector('.assignment-shift-name');
        const startInput = r.querySelector('.assignment-start-time');
        const endInput = r.querySelector('.assignment-end-time');
        const siteSel = r.querySelector('.assignment-site-select');

        if (guardSel) guardSel.name = `assignments[${idx}][guard_id]`;
        if (shiftInput) shiftInput.name = `assignments[${idx}][shift_name]`;
        if (startInput) startInput.name = `assignments[${idx}][start_time]`;
        if (endInput) endInput.name = `assignments[${idx}][end_time]`;
        if (siteSel) siteSel.name = `assignments[${idx}][site_id]`;
    });
}

function updateUIState() {
    const max = getMaxGuards();
    const container = document.getElementById('guardAssignmentsContainer');
    const currentCount = container.querySelectorAll('.assignment-card').length;
    const placeholder = document.getElementById('emptyRowsPlaceholder');

    // Toggle empty state placeholder
    if (placeholder) {
        placeholder.style.display = currentCount === 0 ? 'flex' : 'none';
    }

    // Toggle / disable top Add button
    const btnTop = document.getElementById('btnAddGuardTop');
    if (btnTop) {
        if (currentCount >= max) {
            btnTop.disabled = true;
            btnTop.style.opacity = '0.5';
            btnTop.style.cursor = 'not-allowed';
        } else {
            btnTop.disabled = false;
            btnTop.style.opacity = '1';
            btnTop.style.cursor = 'pointer';
        }
    }
}

function onGuardSelectionChange() {
    const selects = document.querySelectorAll('.assignment-guard-select');
    const selectedGuards = [];

    selects.forEach(sel => {
        if (sel.value) selectedGuards.push(sel.value);
    });

    selects.forEach(sel => {
        const currentVal = sel.value;
        const options = sel.querySelectorAll('option');
        options.forEach(opt => {
            if (!opt.value) return;
            const isChosenElsewhere = selectedGuards.includes(opt.value) && opt.value !== currentVal;
            const baseText = opt.getAttribute('data-base-label') || opt.textContent;
            if (isChosenElsewhere) {
                opt.disabled = true;
                opt.textContent = `${baseText} [Already selected]`;
            } else {
                opt.disabled = false;
                opt.textContent = baseText;
            }
        });
    });
}

function validateContractForm() {
    const selects = document.querySelectorAll('.assignment-guard-select');
    const selectedGuards = [];
    for (let i = 0; i < selects.length; i++) {
        const val = selects[i].value;
        if (!val) {
            alert(`Please select a guard for Guard Assignment #${i + 1} or remove the unused assignment card.`);
            selects[i].focus();
            return false;
        }
        if (selectedGuards.includes(val)) {
            alert('A security guard cannot be assigned to multiple active rows on the same contract. Please adjust duplicate selections.');
            return false;
        }
        selectedGuards.push(val);
    }
    return true;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

document.addEventListener('DOMContentLoaded', () => {
    onCustomerChange();
    if (initialAssignments && initialAssignments.length > 0) {
        initialAssignments.forEach(a => addGuardRow(a));
    } else {
        updateUIState();
    }
});
</script>
