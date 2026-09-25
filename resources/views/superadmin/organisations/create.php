<?php
/**
 * Onboard New Organisation View (Superadmin)
 * Supports provisioning single or multiple tenant admin accounts under the same organisation.
 */
$generatedCode = $generatedCode ?? 'ORG-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/superadmin/organisations') ?>" class="form-back-nav">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Organisations</span>
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Onboard New Tenant Organisation</h1>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <form method="POST" action="<?= url('/superadmin/organisations/create') ?>" id="onboardOrgForm" onsubmit="return validateAdminEmails(event)">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem; align-items: start;">
        
        <!-- Left Column: Organisation Profile & Subscription -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Organisation Profile (Entered Only Once) -->
            <div class="card" style="padding: 1.75rem;">
                <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    Organisation Entity
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Organisation / Agency Name <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" placeholder="Apex Security Services" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Tenant Code</label>
                            <input type="text" name="organization_code" class="form-control" value="<?= e($generatedCode) ?>" style="background: #f8fafc; font-family: monospace; font-weight: 700;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Managing Director">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Agency Email</label>
                            <input type="email" name="email" class="form-control" placeholder="contact@agency.com">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Agency Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="+1 (555) 0100">
                        </div>
                    </div>

                    <div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Headquarters Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Corporate HQ location, city, state, postal code"></textarea>
                        </div>

                        <div style="padding: 1rem 1.15rem; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; display: flex; gap: 0.75rem; align-items: flex-start; margin-top: 0.5rem;">
                            <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 0.15rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div style="font-size: 0.75rem; color: #1e40af; line-height: 1.45;">
                                <strong>Note:</strong> Subscriptions and guard licensing are managed exclusively in the <strong>Subscriptions</strong> section. After onboarding this organisation, you can provision its subscription there.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenant Admin Credentials (Supports 1 or More Admins) -->
        <div class="card" style="padding: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin: 0;">
                    Tenant Admin Credentials
                </h3>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;" id="adminCountBadge">
                    1 Administrator
                </span>
            </div>

            <!-- Dynamic Admins Container -->
            <div id="adminsList" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Admin 1 (Primary / Root Admin) -->
                <div class="admin-entry-block" id="adminBlock_0" data-index="0">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.04em;">
                            Admin 1 <span style="font-size: 0.7rem; color: #2563eb; font-weight: 600; text-transform: none; margin-left: 0.35rem;">(Primary Admin)</span>
                        </span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Admin Full Name</label>
                            <input type="text" name="admins[0][name]" class="form-control" placeholder="John Smith">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                                Admin Login Email <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="email" name="admins[0][email]" class="form-control admin-email-input" placeholder="admin@agency.com" required>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Admin Phone Number</label>
                            <input type="text" name="admins[0][phone]" class="form-control" placeholder="+91 98765 43210">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                                Admin Login Password <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="password" name="admins[0][password]" class="form-control" placeholder="••••••••" required minlength="6">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Add Admin Action Button -->
            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #f8fafc; display: flex; justify-content: flex-start;">
                <button type="button" class="btn btn-outline" id="btnAddAdmin" onclick="addNewAdminSection()" style="padding: 0.45rem 0.95rem; font-size: 0.8125rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; color: #2563eb; border-color: #cbd5e1; background: #ffffff;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Add Admin</span>
                </button>
            </div>

            <!-- Form Actions -->
            <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="<?= url('/superadmin/organisations') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                    Provision Organisation
                </button>
            </div>
        </div>

    </div>
</form>
</div>

<script>
let adminCounter = 1;

function addNewAdminSection() {
    const listContainer = document.getElementById('adminsList');
    if (!listContainer) return;

    adminCounter++;
    const adminIndex = adminCounter - 1;
    const currentBlocks = listContainer.querySelectorAll('.admin-entry-block').length;
    const displayNumber = currentBlocks + 1;

    const block = document.createElement('div');
    block.className = 'admin-entry-block';
    block.id = `adminBlock_${adminIndex}`;
    block.setAttribute('data-index', adminIndex);
    block.style.borderTop = '1px solid #e2e8f0';
    block.style.paddingTop = '1.25rem';
    block.style.marginTop = '0.5rem';

    block.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span class="admin-title-label" style="font-size: 0.8125rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.04em;">
                Admin ${displayNumber}
            </span>
            <button type="button" class="btn" style="padding: 0.2rem 0.6rem; font-size: 0.725rem; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="removeAdminSection(this)">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                <span>Remove</span>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Admin Full Name</label>
                <input type="text" name="admins[${adminIndex}][name]" class="form-control" placeholder="Sarah Wilson">
            </div>

            <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                    Admin Login Email <span style="color: #ef4444;">*</span>
                </label>
                <input type="email" name="admins[${adminIndex}][email]" class="form-control admin-email-input" placeholder="sarah@agency.com" required>
            </div>

            <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Admin Phone Number</label>
                <input type="text" name="admins[${adminIndex}][phone]" class="form-control" placeholder="+91 98765 43210">
            </div>

            <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                    Admin Login Password <span style="color: #ef4444;">*</span>
                </label>
                <input type="password" name="admins[${adminIndex}][password]" class="form-control" placeholder="••••••••" required minlength="6">
            </div>
        </div>
    `;

    listContainer.appendChild(block);
    updateAdminCount();
}

function removeAdminSection(btnElement) {
    const block = btnElement.closest('.admin-entry-block');
    if (block) {
        block.remove();
        reindexAdminLabels();
        updateAdminCount();
    }
}

function reindexAdminLabels() {
    const blocks = document.querySelectorAll('.admin-entry-block');
    blocks.forEach((block, idx) => {
        const titleLabel = block.querySelector('.admin-title-label');
        if (titleLabel && idx > 0) {
            titleLabel.innerText = `Admin ${idx + 1}`;
        }
    });
}

function updateAdminCount() {
    const blocks = document.querySelectorAll('.admin-entry-block');
    const badge = document.getElementById('adminCountBadge');
    if (badge) {
        const count = blocks.length;
        badge.innerText = count === 1 ? '1 Administrator' : `${count} Administrators`;
    }
}

function validateAdminEmails(e) {
    const emailInputs = Array.from(document.querySelectorAll('.admin-email-input'));
    const emails = [];

    for (let i = 0; i < emailInputs.length; i++) {
        const val = emailInputs[i].value.toLowerCase().trim();
        if (val !== '') {
            if (emails.includes(val)) {
                alert(`Duplicate email address "${val}" entered for multiple administrators. Please provide unique email addresses for each admin.`);
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
