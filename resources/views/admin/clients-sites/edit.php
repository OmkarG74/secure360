<?php
/**
 * Edit Client & Sites View
 */
$client = $client ?? [];
$sites = $client['sites'] ?? [];
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/admin/clients-sites') ?>" class="form-back-nav">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Clients &amp; Sites</span>
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Edit Client</h1>
            <p class="page-header-desc">Manage company profile, contact details, and registered security sites.</p>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1.75rem; align-items: start;">
    
    <!-- Edit Client Info -->
    <div class="card" style="padding: 1.75rem;">
        <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
            Company Details
        </h3>

        <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/edit') ?>">
            <?= csrf_field() ?>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Company / Client Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" value="<?= e($client['name']) ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Client Code
                        </label>
                        <input type="text" class="form-control" value="<?= e($client['client_code']) ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 600;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Account Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="0" <?= (int)$client['status'] === 0 ? 'selected' : '' ?>>Active</option>
                            <option value="1" <?= (int)$client['status'] === 1 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Contact Person
                    </label>
                    <input type="text" name="contact_person" class="form-control" value="<?= e($client['contact_person'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Phone Number
                        </label>
                        <input type="text" name="phone" class="form-control" value="<?= e($client['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Email Address
                        </label>
                        <input type="email" name="email" class="form-control" value="<?= e($client['email'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Physical / Billing Address
                    </label>
                    <textarea name="address" class="form-control" rows="3"><?= e($client['address'] ?? '') ?></textarea>
                </div>

                <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Right: Existing Sites & Add Site -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Existing Sites List -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Registered Sites (<?= count($sites) ?>)
            </h3>

            <?php if (empty($sites)): ?>
                <p style="font-size: 0.875rem; color: #64748b;">No sites currently registered for this client.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($sites as $s): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                    <strong style="font-size: 0.9375rem; color: #0f172a;"><?= e($s['site_name']) ?></strong>
                                    <span style="background: #e2e8f0; color: #334155; font-size: 0.75rem; font-family: monospace; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                        <?= e($s['site_code']) ?>
                                    </span>
                                </div>
                                <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 0.2rem;">
                                    <?= e($s['site_address'] ?? 'No address specified') ?>
                                </p>
                                <span style="font-size: 0.75rem; color: #94a3b8;">
                                    Zone: <?= e($s['zone_gate'] ?? 'General') ?> | GPS: <?= e($s['latitude'] ?? '—') ?>, <?= e($s['longitude'] ?? '—') ?>
                                </span>
                            </div>
                            <form method="POST" action="<?= url('/admin/sites/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Remove this site?');">
                                <?= csrf_field() ?>
                                <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.8125rem; cursor: pointer; padding: 0.35rem 0.6rem; border-radius: 4px;">
                                    Remove
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Another Site Form -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Add Another Site
            </h3>

            <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/sites') ?>">
                <?= csrf_field() ?>
                <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Site Name *</label>
                        <input type="text" name="site_name" class="form-control" placeholder="West Perimeter" required>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Site Code</label>
                        <input type="text" name="site_code" class="form-control" placeholder="Auto-generated if blank">
                    </div>
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Address / Gate</label>
                    <input type="text" name="site_address" class="form-control" placeholder="Post location details">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Zone / Gate</label>
                        <input type="text" name="zone_gate" class="form-control" placeholder="Gate B">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Latitude</label>
                        <input type="number" step="0.0000001" name="latitude" class="form-control" placeholder="18.520000">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Longitude</label>
                        <input type="number" step="0.0000001" name="longitude" class="form-control" placeholder="73.856000">
                    </div>
                </div>

                <button type="submit" class="btn btn-outline" style="color: #2563eb; border-color: #2563eb; font-weight: 600;">
                     Add Site Post
                </button>
            </form>
        </div>

    </div>
</div>
</div>
