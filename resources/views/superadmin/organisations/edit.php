<?php
/**
 * Edit Organisation View (Superadmin)
 * Supports editing organisation profile, updating existing administrators, and adding new administrators.
 */
$org = $org ?? [];
$admins = $admins ?? [];
$orgId = (int)($org['id'] ?? 0);
$orgStatus = (int)($org['status'] ?? 0);
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/superadmin/organisations/' . $orgId) ?>" class="form-back-nav" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; font-size: 0.8125rem; font-weight: 500; text-decoration: none; margin-bottom: 1.25rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Organisation Overview</span>
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Edit Organisation &bull; <?= e($org['name']) ?></h1>
            <p class="page-header-desc">Update tenant organization details, manage existing administrator credentials, and provision new administrators.</p>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <form method="POST" action="<?= url('/superadmin/organisations/' . $orgId . '/edit') ?>" id="editOrgForm" onsubmit="return validateAdminEmails(event)">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 1fr 1.15fr; gap: 1.75rem; align-items: start;">
            
            <!-- Left Column: Organisation Entity Profile -->
            <div class="card" style="padding: 1.75rem;">
                <h3 style="font-size: 1.0625rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    Organisation Entity
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Organisation / Agency Name <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" value="<?= e($org['name'] ?? '') ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Tenant Code</label>
                            <input type="text" class="form-control" value="<?= e($org['organization_code'] ?? '') ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 700;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" value="<?= e($org['contact_person'] ?? '') ?>" placeholder="Managing Director">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Official Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($org['email'] ?? '') ?>" placeholder="agency@example.com">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Phone / Hotline</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($org['phone'] ?? '') ?>" placeholder="+91 ...">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">HQ Physical Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= e($org['address'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Organisation Status</label>
                        <select name="status" class="form-control" style="font-weight: 600;">
                            <option value="0" <?= $orgStatus === 0 ? 'selected' : '' ?>>Active & Operational</option>
                            <option value="1" <?= $orgStatus === 1 ? 'selected' : '' ?>>Suspended</option>
                        </select>
                        <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">
                            Suspending an organisation blocks all of its administrators and guards from accessing the portal.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Organisation Administrators (Edit Existing & Add New) -->
            <div class="card" style="padding: 1.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <h3 style="font-size: 1.0625rem; font-weight: 700; color: #0f172a; margin: 0;">
                            Organisation Administrators
                        </h3>
                        <p style="font-size: 0.75rem; color: #64748b; margin: 0.2rem 0 0 0;">
                            Update credentials of current admins or provision new admins.
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline" onclick="addNewAdminSection()" style="font-weight: 600; font-size: 0.8125rem; padding: 0.35rem 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; color: #2563eb; border-color: #cbd5e1; background: #ffffff;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>+ Add Admin</span>
                    </button>
                </div>

                <!-- 1. Existing Administrators List -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 1.5rem;">
                    <?php if (empty($admins)): ?>
                        <div style="padding: 1.5rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: 8px; font-size: 0.8125rem;">
                            No administrators found. Click <strong>+ Add Admin</strong> below to create one.
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $index => $adm): ?>
                            <?php
                                $admId = (int)$adm['id'];
                                $admStatus = (int)($adm['status'] ?? 0);
                            ?>
                            <div class="admin-edit-card" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.875rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e2e8f0;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-weight: 700; font-size: 0.8125rem; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em;">
                                            Admin #<?= $index + 1 ?> &bull; <?= e($adm['full_name']) ?>
                                        </span>
                                        <span style="font-family: monospace; font-size: 0.7rem; color: #64748b; background: #ffffff; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid #cbd5e1;">
                                            <?= e($adm['employee_code'] ?? 'ADM') ?>
                                        </span>
                                    </div>
                                    <span class="sa-status-pill <?= $admStatus === 0 ? 'active' : 'suspended' ?>" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">
                                        <?= $admStatus === 0 ? 'Active' : 'Suspended' ?>
                                    </span>
                                </div>

                                <div style="display: flex; flex-direction: column; gap: 0.875rem;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                                                Full Name <span style="color: #ef4444;">*</span>
                                            </label>
                                            <input type="text" name="admins[<?= $admId ?>][name]" class="form-control" value="<?= e($adm['full_name']) ?>" required style="font-size: 0.8125rem;">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                                                Login Email <span style="color: #ef4444;">*</span>
                                            </label>
                                            <input type="email" name="admins[<?= $admId ?>][email]" class="form-control admin-email-input" value="<?= e($adm['email']) ?>" required style="font-size: 0.8125rem;">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                                                Phone Number
                                            </label>
                                            <input type="text" name="admins[<?= $admId ?>][phone]" class="form-control" value="<?= e($adm['phone'] ?? '') ?>" placeholder="+91 ..." style="font-size: 0.8125rem;">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                                                Admin Account Status
                                            </label>
                                            <select name="admins[<?= $admId ?>][status]" class="form-control" style="font-size: 0.8125rem; font-weight: 600;">
                                                <option value="0" <?= $admStatus === 0 ? 'selected' : '' ?>>Active</option>
                                                <option value="1" <?= $admStatus === 1 ? 'selected' : '' ?>>Suspended</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                                            Reset Password <span style="font-weight: normal; color: #64748b;">(leave blank to keep current)</span>
                                        </label>
                                        <input type="password" name="admins[<?= $admId ?>][password]" class="form-control" placeholder="••••••••" minlength="6" style="font-size: 0.8125rem;">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 2. Container for Adding New Administrators Dynamically -->
                <div id="newAdminsContainer" style="display: flex; flex-direction: column; gap: 1rem;"></div>

                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn btn-outline" onclick="addNewAdminSection()" style="font-weight: 600; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 0.35rem; color: #2563eb; border-color: #cbd5e1; background: #ffffff;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>+ Add Another Admin</span>
                    </button>
                    <span style="font-size: 0.75rem; color: #64748b;" id="newAdminNotice"></span>
                </div>
            </div>

        </div>

        <!-- Sticky / Bottom Form Action Bar -->
        <div style="margin-top: 1.75rem; padding: 1.25rem 1.5rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: flex-end; align-items: center; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <a href="<?= url('/superadmin/organisations/' . $orgId) ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.6rem 1.25rem;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.75rem; font-weight: 600;">
                Save Organisation &amp; Administrator Changes
            </button>
        </div>
    </form>
</div>

<script>
let newAdminCounter = 0;

function addNewAdminSection() {
    newAdminCounter++;
    const container = document.getElementById('newAdminsContainer');
    if (!container) return;

    const block = document.createElement('div');
    block.className = 'new-admin-card';
    block.id = `newAdminBlock_${newAdminCounter}`;
    block.style.background = '#f0fdf4';
    block.style.border = '1px solid #bbf7d0';
    block.style.borderRadius = '10px';
    block.style.padding = '1.25rem';

    block.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.875rem; padding-bottom: 0.5rem; border-bottom: 1px solid #bbf7d0;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-weight: 700; font-size: 0.8125rem; color: #166534; text-transform: uppercase; letter-spacing: 0.03em;">
                    New Administrator #${newAdminCounter}
                </span>
                <span style="font-size: 0.7rem; color: #16a34a; background: #ffffff; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid #86efac; font-weight: 600;">
                    To Be Added
                </span>
            </div>
            <button type="button" class="btn" style="padding: 0.2rem 0.6rem; font-size: 0.725rem; color: #dc2626; background: #ffffff; border: 1px solid #fecaca; border-radius: 4px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="removeNewAdminSection('${block.id}')">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                <span>Remove</span>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.875rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="new_admins[${newAdminCounter}][name]" class="form-control" placeholder="Jane Doe" required style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                        Login Email <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="new_admins[${newAdminCounter}][email]" class="form-control admin-email-input" placeholder="jane@example.com" required style="font-size: 0.8125rem;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                        Phone Number
                    </label>
                    <input type="text" name="new_admins[${newAdminCounter}][phone]" class="form-control" placeholder="+91 ..." style="font-size: 0.8125rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">
                        Temporary Password <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="password" name="new_admins[${newAdminCounter}][password]" class="form-control" placeholder="Min 6 characters" required minlength="6" style="font-size: 0.8125rem;">
                </div>
            </div>
        </div>
    `;

    container.appendChild(block);
}

function removeNewAdminSection(blockId) {
    const el = document.getElementById(blockId);
    if (el) {
        el.remove();
    }
}

function validateAdminEmails(e) {
    const emailInputs = Array.from(document.querySelectorAll('.admin-email-input'));
    const emails = [];

    for (let i = 0; i < emailInputs.length; i++) {
        const val = emailInputs[i].value.toLowerCase().trim();
        if (val !== '') {
            if (emails.includes(val)) {
                alert(`Duplicate email address "${val}" found. Each administrator must have a unique email address.`);
                emailInputs[i].focus();
                e.preventDefault();
                return false;
            }
            emails.push(val);
        }
    }
    return true;
}
</script>
