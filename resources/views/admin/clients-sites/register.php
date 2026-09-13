<?php
/**
 * Register Client & Sites View
 * Secure360 Enterprise SaaS UI: Balanced two-column grid, styled form controls, dynamic site additions
 */
$generatedCode = $generatedCode ?? 'CLT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/admin/clients-sites') ?>" class="form-back-nav">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Clients &amp; Sites</span>
    </a>

    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.75rem;">
        <div>
            <h1 class="page-header-title">Register Client</h1>
            <p class="page-header-desc">Enter client account details and register one or more physical site posts.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('/admin/clients/register') ?>" id="clientRegisterForm">
        <?= csrf_field() ?>

        <div class="register-layout-grid">
            
            <!-- Left Column: Client Information Card -->
            <div class="card card-padded register-panel">
                <div class="card-section-header">
                    <div class="card-section-info">
                        <div class="card-section-icon blue">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div>
                            <h2 class="card-section-title">Client Information</h2>
                            <p class="card-section-desc">Primary billing and corporate entity details.</p>
                        </div>
                    </div>
                </div>

                <div class="register-form-fields">
                    <!-- Company Name -->
                    <div class="form-group">
                        <label class="form-label">
                            Company / Client Name <span class="required-star">*</span>
                        </label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <input type="text" name="name" class="form-control" placeholder="Metro Commercial Plaza" required>
                        </div>
                    </div>

                    <!-- Client Code & Status Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Client Code</label>
                            <input type="text" name="client_code" class="form-control" value="<?= e($generatedCode) ?>" readonly style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: 600; letter-spacing: 0.05em;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="0" selected>Active</option>
                                <option value="1">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Contact Person -->
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <input type="text" name="contact_person" class="form-control" placeholder="Sarah Jenkins">
                        </div>
                    </div>

                    <!-- Phone & Email Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <input type="text" name="phone" class="form-control" placeholder="+1 (555) 0123">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <input type="email" name="email" class="form-control" placeholder="client@domain.com">
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Physical / Billing Address</label>
                        <div class="input-icon-wrapper textarea-wrapper" style="height: calc(100% - 24px);">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <textarea name="address" class="form-control" rows="4" placeholder="500 Commerce Way, Suite 100, City, State" style="height: 100%; min-height: 120px;"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Assigned Sites Card -->
            <div class="card card-padded register-panel">
                <div class="card-section-header">
                    <div class="card-section-info">
                        <div class="card-section-icon green">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="card-section-title">Assigned Sites</h2>
                            <p class="card-section-desc">Physical posts &amp; geofenced patrol sites.</p>
                        </div>
                    </div>
                    <button type="button" id="btnAddSite" class="btn btn-outline btn-sm" style="color: #2563eb; border-color: #bfdbfe; background: #eff6ff;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        Add Site
                    </button>
                </div>

                <!-- Container for dynamic site rows -->
                <div id="sitesContainer" style="display: flex; flex-direction: column; gap: 1.25rem; flex: 1;">
                    <!-- Initial Site Row -->
                    <div class="site-card" data-index="0">
                        <div class="site-card-header">
                            <span class="site-card-title">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="site-card-title-text">Site #1 - Post Location</span>
                            </span>
                            <button type="button" class="btn-remove-site" style="display: none;" title="Remove Site">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                Remove
                            </button>
                        </div>

                        <!-- Row 1: Site Name & Code -->
                        <div class="site-grid-row-1">
                            <div class="form-group">
                                <label class="form-label">
                                    Site Name <span class="required-star">*</span>
                                </label>
                                <input type="text" name="sites[0][site_name]" class="form-control" placeholder="Main Gate / Tower A" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Site Code</label>
                                <input type="text" name="sites[0][site_code]" class="form-control" placeholder="SITE-MAIN-01" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                            </div>
                        </div>

                        <!-- Row 2: Address -->
                        <div class="site-grid-row-2">
                            <div class="form-group">
                                <label class="form-label">Site Specific Address / Gate Details</label>
                                <input type="text" name="sites[0][site_address]" class="form-control" placeholder="500 Commerce Way, Gate 1 South">
                            </div>
                        </div>

                        <!-- Row 3: Zone, Lat, Long -->
                        <div class="site-grid-row-3">
                            <div class="form-group">
                                <label class="form-label">Zone / Gate</label>
                                <input type="text" name="sites[0][zone_gate]" class="form-control" placeholder="Gate A">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Latitude (GPS)</label>
                                <input type="number" step="0.0000001" name="sites[0][latitude]" class="form-control" placeholder="18.520430">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Longitude (GPS)</label>
                                <input type="number" step="0.0000001" name="sites[0][longitude]" class="form-control" placeholder="73.858743">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="form-actions-footer">
                    <a href="<?= url('/admin/clients-sites') ?>" class="btn btn-outline" style="min-width: 110px;">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" style="min-width: 210px;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Register Client &amp; Sites
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

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
            const titleText = card.querySelector('.site-card-title-text');
            if (titleText) {
                titleText.textContent = `Site #${index + 1} - Post Location`;
            }
            if (cards.length > 1) {
                btnRemove.style.display = 'inline-flex';
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
        card.style.animation = 'fadeIn 0.2s ease-in';
        
        card.innerHTML = `
            <div class="site-card-header">
                <span class="site-card-title">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="site-card-title-text">Site #${newIndex + 1} - Post Location</span>
                </span>
                <button type="button" class="btn-remove-site" title="Remove Site">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    Remove
                </button>
            </div>

            <div class="site-grid-row-1">
                <div class="form-group">
                    <label class="form-label">
                        Site Name <span class="required-star">*</span>
                    </label>
                    <input type="text" name="sites[\${newIndex}][site_name]" class="form-control" placeholder="North Warehouse / Gate 2" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Site Code</label>
                    <input type="text" name="sites[\${newIndex}][site_code]" class="form-control" placeholder="SITE-NORTH-02" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                </div>
            </div>

            <div class="site-grid-row-2">
                <div class="form-group">
                    <label class="form-label">Site Specific Address / Gate Details</label>
                    <input type="text" name="sites[\${newIndex}][site_address]" class="form-control" placeholder="510 Commerce Way, Bay 3">
                </div>
            </div>

            <div class="site-grid-row-3">
                <div class="form-group">
                    <label class="form-label">Zone / Gate</label>
                    <input type="text" name="sites[\${newIndex}][zone_gate]" class="form-control" placeholder="Bay North">
                </div>
                <div class="form-group">
                    <label class="form-label">Latitude (GPS)</label>
                    <input type="number" step="0.0000001" name="sites[\${newIndex}][latitude]" class="form-control" placeholder="18.521500">
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude (GPS)</label>
                    <input type="number" step="0.0000001" name="sites[\${newIndex}][longitude]" class="form-control" placeholder="73.858000">
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
