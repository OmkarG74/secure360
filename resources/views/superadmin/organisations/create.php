<?php
/**
 * Onboard New Organisation View (Superadmin)
 */
$generatedCode = $generatedCode ?? 'ORG-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
?>

<div class="breadcrumb" style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">
    <a href="<?= url('/superadmin/organisations') ?>" style="color: #2563eb; text-decoration: none;">Organisations</a>
    <span style="margin: 0 0.5rem;">/</span>
    <span style="color: #0f172a; font-weight: 500;">Onboard Organisation</span>
</div>

<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Onboard New Tenant Organisation</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Create a new customer tenant entity and provision root Admin access.</p>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<form method="POST" action="<?= url('/superadmin/organisations/create') ?>">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem; align-items: start;">
        
        <!-- Organisation Profile -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Organisation Entity
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Organisation / Agency Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Apex Security Services" required>
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
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Headquarters Address</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="Corporate HQ location"></textarea>
                </div>
            </div>
        </div>

        <!-- Root Admin Credentials -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Tenant Admin Credentials
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Admin Full Name</label>
                    <input type="text" name="admin_name" class="form-control" placeholder="John Smith">
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Admin Login Email <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="admin_email" class="form-control" placeholder="admin@agency.com" required>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Admin Login Password <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="password" name="admin_password" class="form-control" placeholder="••••••••" required minlength="6">
                    <span style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; display: block;">
                        The administrator will use this password to sign into the Organisation Admin Web Portal.
                    </span>
                </div>

                <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="<?= url('/superadmin/organisations') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                        Provision Organisation
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>
