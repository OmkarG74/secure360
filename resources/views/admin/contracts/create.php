<?php
/**
 * Create Service Contract View
 */
$customers = $customers ?? [];
$sites = $sites ?? [];
$guards = $guards ?? [];
$contractCode = $contractCode ?? 'CTR-' . date('Y') . '-' . rand(100, 999);
?>

<div class="breadcrumb" style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">
    <a href="<?= url('/admin/contracts') ?>" style="color: #2563eb; text-decoration: none;">Contracts</a>
    <span style="margin: 0 0.5rem;">/</span>
    <span style="color: #0f172a; font-weight: 500;">New Contract</span>
</div>

<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">New Security Service Contract</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Bind a client account to a specific physical site, shift schedule, and guard allocation.</p>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<form method="POST" action="<?= url('/admin/contracts/create') ?>">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.75rem; align-items: start;">
        
        <!-- Left: Contract Details -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Agreement &amp; Post Location
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Contract Code
                        </label>
                        <input type="text" name="contract_code" class="form-control" value="<?= e($contractCode) ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 700;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Guards Required
                        </label>
                        <input type="number" name="required_guard_count" class="form-control" value="1" min="1" required>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Client Organization <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="customer_id" id="customerSelect" class="form-control" required onchange="filterSites()">
                        <option value="">-- Select Client --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['client_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Assigned Physical Site <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="site_id" id="siteSelect" class="form-control" required>
                        <option value="">-- Select Site --</option>
                        <?php foreach ($sites as $s): ?>
                            <option value="<?= $s['id'] ?>" data-customer="<?= $s['customer_id'] ?>">
                                <?= e($s['site_name']) ?> (<?= e($s['site_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem; display: block;">
                        Note: Database constraint allows only 1 active contract per site at a time.
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Start Date <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            End Date (Optional)
                        </label>
                        <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Contract Notes / SLA Details
                    </label>
                    <textarea name="extra_notes" class="form-control" rows="3" placeholder="e.g. 24/7 security guarding, armed guard on night shift, daily geofence patrol reporting."></textarea>
                </div>

            </div>
        </div>

        <!-- Right: Primary Shift & Initial Guard Assignment -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Shift Schedule &amp; Allocation
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Shift Name
                    </label>
                    <input type="text" name="shift_name" class="form-control" value="Day Patrol Shift" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Shift Start Time
                        </label>
                        <input type="time" name="start_time" class="form-control" value="08:00" required>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Shift End Time
                        </label>
                        <input type="time" name="end_time" class="form-control" value="16:00" required>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Allocate Guard (Mobile App Operator)
                    </label>
                    <select name="guard_id" class="form-control">
                        <option value="">-- Leave Unassigned for Now --</option>
                        <?php foreach ($guards as $g): ?>
                            <option value="<?= $g['guard_id'] ?>">
                                <?= e($g['full_name']) ?> (<?= e($g['employee_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; display: block;">
                        Assigned guard will immediately see this site on their Flutter mobile dashboard under "Today's Duty".
                    </span>
                </div>

                <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="<?= url('/admin/contracts') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                        Create Contract &amp; Shift
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
function filterSites() {
    const customerId = document.getElementById('customerSelect').value;
    const siteSelect = document.getElementById('siteSelect');
    const options = siteSelect.querySelectorAll('option');

    options.forEach(opt => {
        if (!opt.value) return; // Keep placeholder
        if (!customerId || opt.getAttribute('data-customer') === customerId) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    });

    siteSelect.value = '';
}
</script>
