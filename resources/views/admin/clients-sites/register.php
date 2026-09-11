<?php
/**
 * Register Client & Sites View
 * Replicates Screenshot 2: Clean enterprise UI, dynamic multi-site row additions
 */
$generatedCode = $generatedCode ?? 'CLT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
?>

<div class="breadcrumb" style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">
    <a href="<?= url('/admin/clients-sites') ?>" style="color: #2563eb; text-decoration: none;">Clients &amp; Sites</a>
    <span style="margin: 0 0.5rem;">/</span>
    <span style="color: #0f172a; font-weight: 500;">Register Client</span>
</div>

<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Register Client</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Enter client account details and register one or more physical site posts.</p>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<form method="POST" action="<?= url('/admin/clients/register') ?>" id="clientRegisterForm">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1.75rem; align-items: start;">
        
        <!-- Left Column: Client Information -->
        <div class="card" style="padding: 1.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a;">Client Information</h3>
                    <p style="font-size: 0.8125rem; color: #64748b;">Primary billing and corporate entity details.</p>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Company / Client Name <span style="color: #ef4444;">*</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Metro Commercial Plaza" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Client Code
                        </label>
                        <input type="text" name="client_code" class="form-control" value="<?= e($generatedCode) ?>" style="background: #f8fafc; font-family: monospace; font-weight: 600;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Account Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="0" selected>Active</option>
                            <option value="1">Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Contact Person
                    </label>
                    <div class="input-icon-wrapper">
                        <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <input type="text" name="contact_person" class="form-control" placeholder="e.g. Sarah Jenkins">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Phone Number
                        </label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <input type="text" name="phone" class="form-control" placeholder="+1 (555) 0123">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Email Address
                        </label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <input type="email" name="email" class="form-control" placeholder="client@domain.com">
                        </div>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                        Physical / Billing Address
                    </label>
                    <div class="input-icon-wrapper">
                        <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <textarea name="address" class="form-control" rows="3" placeholder="500 Commerce Way, Suite 100, City, State"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Assigned Sites with dynamic addition -->
        <div class="card" style="padding: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a;">Assigned Sites</h3>
                        <p style="font-size: 0.8125rem; color: #64748b;">Physical posts &amp; geofenced patrol sites.</p>
                    </div>
                </div>
                <button type="button" id="btnAddSite" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.8125rem; border-color: #2563eb; color: #2563eb;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    + Add Site
                </button>
            </div>

            <!-- Container for dynamic site rows -->
            <div id="sitesContainer" style="display: flex; flex-direction: column; gap: 1.25rem;">
                <!-- Initial Site Row -->
                <div class="site-card" data-index="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;" class="site-card-title">
                            Site #1 - Primary Post
                        </span>
                        <button type="button" class="btn-remove-site" style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; display: none;" title="Remove Site">
                            ✕ Remove
                        </button>
                    </div>

                    <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                                Site Name <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" name="sites[0][site_name]" class="form-control" placeholder="e.g. Main Gate / Tower A" required style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                                Site Code
                            </label>
                            <input type="text" name="sites[0][site_code]" class="form-control" placeholder="e.g. SITE-MAIN-01" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem; font-family: monospace;">
                        </div>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                            Site Specific Address / Gate Details
                        </label>
                        <input type="text" name="sites[0][site_address]" class="form-control" placeholder="e.g. 500 Commerce Way, Gate 1 South" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                                Zone / Gate
                            </label>
                            <input type="text" name="sites[0][zone_gate]" class="form-control" placeholder="Gate A" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                                Latitude (GPS)
                            </label>
                            <input type="number" step="0.0000001" name="sites[0][latitude]" class="form-control" placeholder="18.520430" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                                Longitude (GPS)
                            </label>
                            <input type="number" step="0.0000001" name="sites[0][longitude]" class="form-control" placeholder="73.856743" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; align-items: center; gap: 1rem;">
                <a href="<?= url('/admin/clients-sites') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    Register Client &amp; Sites
                </button>
            </div>
        </div>

    </div>
</form>

<!-- Client-side Dynamic Site Addition Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('sitesContainer');
    const btnAdd = document.getElementById('btnAddSite');
    let siteCount = 1;

    function updateRemoveButtons() {
        const cards = container.querySelectorAll('.site-card');
        cards.forEach((card, index) => {
            const btnRemove = card.querySelector('.btn-remove-site');
            const title = card.querySelector('.site-card-title');
            if (title) {
                title.textContent = `Site #${index + 1} - Post Location`;
            }
            if (cards.length > 1) {
                btnRemove.style.display = 'inline-block';
            } else {
                btnRemove.style.display = 'none';
            }
        });
    }

    btnAdd.addEventListener('click', function () {
        const newIndex = siteCount;
        const card = document.createElement('div');
        card.className = 'site-card';
        card.setAttribute('data-index', newIndex);
        card.style = 'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; position: relative; animation: fadeIn 0.2s ease-in;';
        
        card.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <span style="font-size: 0.8125rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;" class="site-card-title">
                    Site #${newIndex + 1} - Post Location
                </span>
                <button type="button" class="btn-remove-site" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;" title="Remove Site">
                    ✕ Remove
                </button>
            </div>

            <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Site Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="sites[${newIndex}][site_name]" class="form-control" placeholder="e.g. North Warehouse / Gate 2" required style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Site Code
                    </label>
                    <input type="text" name="sites[${newIndex}][site_code]" class="form-control" placeholder="e.g. SITE-NORTH-02" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem; font-family: monospace;">
                </div>
            </div>

            <div style="margin-bottom: 0.75rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                    Site Specific Address / Gate Details
                </label>
                <input type="text" name="sites[${newIndex}][site_address]" class="form-control" placeholder="e.g. 510 Commerce Way, Bay 3" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Zone / Gate
                    </label>
                    <input type="text" name="sites[${newIndex}][zone_gate]" class="form-control" placeholder="Bay North" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Latitude (GPS)
                    </label>
                    <input type="number" step="0.0000001" name="sites[${newIndex}][latitude]" class="form-control" placeholder="18.521500" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Longitude (GPS)
                    </label>
                    <input type="number" step="0.0000001" name="sites[${newIndex}][longitude]" class="form-control" placeholder="73.858000" style="font-size: 0.8125rem; padding: 0.5rem 0.75rem;">
                </div>
            </div>
        `;

        container.appendChild(card);
        siteCount++;
        updateRemoveButtons();

        card.querySelector('.btn-remove-site').addEventListener('click', function () {
            card.remove();
            updateRemoveButtons();
        });
    });

    updateRemoveButtons();
});
</script>
