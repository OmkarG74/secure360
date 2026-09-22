<?php
/**
 * Superadmin Organisation Overview View
 * Matches Section 5 & 28 requirements:
 * Sections: Organisation Information, Organisation Admins, Subscription Summary, Billing Summary
 * Top Actions: Edit Organisation | Suspend / Activate Organisation
 */
$org = $org ?? [];
$admins = $admins ?? [];
$status = (int)($org['status'] ?? 0);
$orgId = (int)($org['id'] ?? 0);
?>

<style>
.sa-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.sa-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.sa-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.sa-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.sa-info-item label {
    display: block;
    font-size: 0.725rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 0.35rem;
}

.sa-info-item span {
    font-size: 0.875rem;
    font-weight: 600;
    color: #0f172a;
}

.sa-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.sa-status-pill.active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sa-status-pill.suspended {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.sa-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.sa-btn-action:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

.sa-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(2px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.sa-modal-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 480px;
    width: 90%;
    padding: 1.75rem;
}
</style>

<div class="page-container">
    <!-- Header with Top Actions -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="<?= url('/superadmin/organisations') ?>" style="color: #64748b; text-decoration: none; font-size: 0.8125rem;">&larr; Back to Organisations</a>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                <h1 class="page-header-title" style="margin: 0;"><?= e($org['name']) ?></h1>
                <span class="sa-org-code" style="font-size: 0.8125rem;"><?= e($org['organization_code']) ?></span>
                <?php if ($status === 0): ?>
                    <span class="sa-status-pill active">Active</span>
                <?php else: ?>
                    <span class="sa-status-pill suspended">Suspended</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="<?= url('/superadmin/organisations/' . $orgId . '/edit') ?>" class="btn btn-secondary" style="font-weight: 600; text-decoration: none;">
                Edit Organisation
            </a>

            <?php if ($status === 0): ?>
                <button type="button" class="btn btn-danger" onclick="openOrgSuspendModal('<?= e(addslashes($org['name'])) ?>', 0)">
                    Suspend Organisation
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-primary" onclick="openOrgSuspendModal('<?= e(addslashes($org['name'])) ?>', 1)">
                    Activate Organisation
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- 1. Organisation Information -->
    <div class="sa-card">
        <div class="sa-section-header">
            <h2 class="sa-section-title">Organisation Information</h2>
        </div>
        <div class="sa-info-grid">
            <div class="sa-info-item">
                <label>Organisation Name</label>
                <span><?= e($org['name']) ?></span>
            </div>
            <div class="sa-info-item">
                <label>Organisation Code</label>
                <span style="font-family: monospace;"><?= e($org['organization_code']) ?></span>
            </div>
            <div class="sa-info-item">
                <label>Contact Person</label>
                <span><?= e($org['contact_person'] ?: '—') ?></span>
            </div>
            <div class="sa-info-item">
                <label>Email Address</label>
                <span><?= e($org['email'] ?: '—') ?></span>
            </div>
            <div class="sa-info-item">
                <label>Phone Number</label>
                <span><?= e($org['phone'] ?: '—') ?></span>
            </div>
            <div class="sa-info-item">
                <label>Registered Address</label>
                <span><?= e($org['address'] ?: '—') ?></span>
            </div>
            <div class="sa-info-item">
                <label>Onboarding Date</label>
                <span><?= date('d M Y', strtotime($org['created_at'] ?? 'now')) ?></span>
            </div>
            <div class="sa-info-item">
                <label>Account Status</label>
                <span><?= $status === 0 ? 'Active & Operational' : 'Suspended' ?></span>
            </div>
        </div>
    </div>

    <!-- Organisation Admins Section -->
    <div class="sa-card">
        <div class="sa-section-header">
            <div>
                <h2 class="sa-section-title">Organisation Administrators</h2>
                <p style="font-size: 0.75rem; color: #64748b; margin: 0.25rem 0 0 0;">
                    Assigned administrators with operational access to this organisation.
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" class="btn btn-primary" onclick="openAddAdminModal()" style="font-weight: 600; font-size: 0.8125rem; padding: 0.4rem 0.85rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>+ Add Admin</span>
                </button>
                <a href="<?= url('/superadmin/organisations/' . $orgId . '/edit') ?>" class="btn btn-outline" style="font-weight: 600; font-size: 0.8125rem; text-decoration: none; padding: 0.4rem 0.85rem;">
                    Manage in Edit &rarr;
                </a>
            </div>
        </div>

        <?php if (empty($admins)): ?>
            <div style="padding: 2.5rem; text-align: center; color: #64748b; font-size: 0.8125rem;">
                No administrators assigned to this organisation yet. Click <strong>+ Add Admin</strong> to add one.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Name</th>
                            <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Email</th>
                            <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Phone</th>
                            <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569;">Status</th>
                            <th style="padding: 0.75rem 1rem; font-size: 0.725rem; text-transform: uppercase; color: #475569; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $adm): ?>
                            <?php
                                $admId = (int)$adm['id'];
                                $admStatus = (int)($adm['status'] ?? 0);
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.875rem 1rem; font-weight: 600; color: #0f172a;">
                                    <?= e($adm['full_name']) ?>
                                    <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace; margin-top: 0.15rem;">
                                        <?= e($adm['employee_code'] ?? 'ADM') ?>
                                    </div>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: #334155;">
                                    <?= e($adm['email']) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: #64748b;">
                                    <?= e($adm['phone'] ?: '—') ?>
                                </td>
                                <td style="padding: 0.875rem 1rem;">
                                    <?php if ($admStatus === 0): ?>
                                        <span class="sa-status-pill active">Active</span>
                                    <?php else: ?>
                                        <span class="sa-status-pill suspended">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <button type="button" class="sa-btn-action" onclick="openEditAdminModal(<?= $admId ?>, '<?= e(addslashes($adm['full_name'])) ?>', '<?= e(addslashes($adm['email'])) ?>', '<?= e(addslashes($adm['phone'] ?? '')) ?>')">
                                            Edit
                                        </button>

                                        <?php if ($admStatus === 0): ?>
                                            <button type="button" class="sa-btn-action" style="color: #b45309; border-color: #fde68a;" onclick="openAdminSuspendModal(<?= $admId ?>, '<?= e(addslashes($adm['full_name'])) ?>', 0)">
                                                Suspend
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="sa-btn-action" style="color: #059669; border-color: #a7f3d0;" onclick="openAdminSuspendModal(<?= $admId ?>, '<?= e(addslashes($adm['full_name'])) ?>', 1)">
                                                Activate
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Suspend / Activate Organisation -->
<div id="orgSuspendModal" class="sa-modal-backdrop">
    <div class="sa-modal-card">
        <h3 id="orgModalTitle" style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
            Suspend Organisation?
        </h3>
        <p id="orgModalBody" style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            This will prevent organisation administrators from using the organisation until it is activated again.
        </p>
        <form method="POST" action="<?= url('/superadmin/organisations/' . $orgId . '/toggle-status') ?>">
            <?= csrf_field() ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeOrgSuspendModal()" style="font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" id="orgModalSubmitBtn" class="btn btn-danger" style="font-weight: 600;">
                    Suspend Organisation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Admin -->
<div id="editAdminModal" class="sa-modal-backdrop">
    <div class="sa-modal-card">
        <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
            Edit Administrator
        </h3>
        <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1.25rem;">
            Update credentials and contact phone for this organisation administrator.
        </p>
        <form method="POST" id="editAdminForm" action="">
            <?= csrf_field() ?>
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="full_name" id="editAdminName" class="form-control" required style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Login Email <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="email" id="editAdminEmail" class="form-control" required style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Phone Number
                    </label>
                    <input type="text" name="phone" id="editAdminPhone" class="form-control" placeholder="+91 ..." style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Reset Password <span style="font-weight: normal; color: #64748b;">(leave blank to keep current)</span>
                    </label>
                    <input type="password" name="password" id="editAdminPassword" class="form-control" placeholder="••••••••" minlength="6" style="font-size: 0.8125rem;">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditAdminModal()" style="font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add New Admin -->
<div id="addAdminModal" class="sa-modal-backdrop">
    <div class="sa-modal-card">
        <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
            Add Organisation Administrator
        </h3>
        <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1.25rem;">
            Provision an additional administrator account for this organisation.
        </p>
        <form method="POST" action="<?= url('/superadmin/organisations/' . $orgId . '/admins/create') ?>">
            <?= csrf_field() ?>
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Login Email <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="john@example.com" required style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Phone Number
                    </label>
                    <input type="text" name="phone" class="form-control" placeholder="+91 ..." style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem;">
                        Temporary Password <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6" style="font-size: 0.8125rem;">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAddAdminModal()" style="font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                    Add Administrator
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Suspend / Activate Admin -->
<div id="adminSuspendModal" class="sa-modal-backdrop">
    <div class="sa-modal-card">
        <h3 id="adminModalTitle" style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
            Suspend Administrator?
        </h3>
        <p id="adminModalBody" style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            This will prevent this administrator from logging into the portal.
        </p>
        <form method="POST" id="adminSuspendForm" action="">
            <?= csrf_field() ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAdminSuspendModal()" style="font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" id="adminModalSubmitBtn" class="btn btn-danger" style="font-weight: 600;">
                    Suspend Administrator
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openOrgSuspendModal(orgName, currentStatus) {
    const modal = document.getElementById('orgSuspendModal');
    const title = document.getElementById('orgModalTitle');
    const body = document.getElementById('orgModalBody');
    const submitBtn = document.getElementById('orgModalSubmitBtn');

    if (currentStatus === 0) {
        title.innerText = 'Suspend ' + orgName + '?';
        body.innerText = 'This will prevent organisation administrators from using the organisation until it is activated again.';
        submitBtn.innerText = 'Suspend Organisation';
        submitBtn.className = 'btn btn-danger';
    } else {
        title.innerText = 'Activate ' + orgName + '?';
        body.innerText = 'This will restore operational access and normal functionality for this customer organisation.';
        submitBtn.innerText = 'Activate Organisation';
        submitBtn.className = 'btn btn-primary';
    }

    modal.style.display = 'flex';
}

function closeOrgSuspendModal() {
    document.getElementById('orgSuspendModal').style.display = 'none';
}

function openEditAdminModal(adminId, fullName, email, phone) {
    const form = document.getElementById('editAdminForm');
    form.action = '<?= url('/superadmin/organisations/' . $orgId . '/admins/') ?>' + adminId + '/edit';
    document.getElementById('editAdminName').value = fullName;
    document.getElementById('editAdminEmail').value = email;
    document.getElementById('editAdminPhone').value = phone;
    document.getElementById('editAdminPassword').value = '';
    document.getElementById('editAdminModal').style.display = 'flex';
}

function closeEditAdminModal() {
    document.getElementById('editAdminModal').style.display = 'none';
}

function openAddAdminModal() {
    document.getElementById('addAdminModal').style.display = 'flex';
}

function closeAddAdminModal() {
    document.getElementById('addAdminModal').style.display = 'none';
}

function openAdminSuspendModal(adminId, adminName, currentStatus) {
    const modal = document.getElementById('adminSuspendModal');
    const title = document.getElementById('adminModalTitle');
    const body = document.getElementById('adminModalBody');
    const submitBtn = document.getElementById('adminModalSubmitBtn');
    const form = document.getElementById('adminSuspendForm');

    form.action = '<?= url('/superadmin/organisations/' . $orgId . '/admins/') ?>' + adminId + '/toggle-status';

    if (currentStatus === 0) {
        title.innerText = 'Suspend ' + adminName + '?';
        body.innerText = 'This will suspend this administrator account and block their access to the portal.';
        submitBtn.innerText = 'Suspend Administrator';
        submitBtn.className = 'btn btn-danger';
    } else {
        title.innerText = 'Activate ' + adminName + '?';
        body.innerText = 'This will restore this administrator account and allow them to log in again.';
        submitBtn.innerText = 'Activate Administrator';
        submitBtn.className = 'btn btn-primary';
    }

    modal.style.display = 'flex';
}

function closeAdminSuspendModal() {
    document.getElementById('adminSuspendModal').style.display = 'none';
}
</script>
