<?php
/**
 * Edit Organisation View (Superadmin)
 */
$org = $org ?? [];
$orgId = (int)($org['id'] ?? 0);
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
            <h1 class="page-header-title">Edit Organisation</h1>
            <p class="page-header-desc">Update tenant details, contact credentials, and registered office profile.</p>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <form method="POST" action="<?= url('/superadmin/organisations/' . $orgId . '/edit') ?>">
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
                        <input type="text" name="name" class="form-control" value="<?= e($org['name'] ?? '') ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Tenant Code</label>
                            <input type="text" class="form-control" value="<?= e($org['organization_code'] ?? '') ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 700;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" value="<?= e($org['contact_person'] ?? '') ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Official Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($org['email'] ?? '') ?>">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Phone / Hotline</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($org['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">HQ Physical Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= e($org['address'] ?? '') ?></textarea>
                    </div>

                    <div style="margin-top: 1rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem;">
                        <a href="<?= url('/superadmin/organisations') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">Cancel</a>
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
