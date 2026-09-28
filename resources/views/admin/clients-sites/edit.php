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
                        <div class="registered-site-card" data-site-id="<?= $s['id'] ?>" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; background: #fafafa; transition: border-color 0.2s;">
                            <!-- Display Mode -->
                            <div class="site-display-mode" style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1; min-width: 0; padding-right: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                        <strong class="display-site-name" style="font-size: 0.9375rem; color: #0f172a;"><?= e($s['site_name']) ?></strong>
                                        <span class="display-site-code" style="background: #e2e8f0; color: #334155; font-size: 0.75rem; font-family: monospace; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                            <?= e($s['site_code']) ?>
                                        </span>
                                    </div>
                                    <p class="display-site-address" style="font-size: 0.8125rem; color: #64748b; margin-bottom: 0.2rem;">
                                        <?= e($s['site_address'] ?? 'No address specified') ?>
                                    </p>
                                    <span class="display-site-meta" style="font-size: 0.75rem; color: #94a3b8;">
                                        Zone: <span class="display-zone"><?= e($s['zone_gate'] ?? 'General') ?></span> | GPS: <span class="display-lat"><?= e($s['latitude'] ?? '—') ?></span>, <span class="display-lng"><?= e($s['longitude'] ?? '—') ?></span>
                                    </span>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <button type="button" class="btn btn-outline btn-sm btn-edit-site" style="color: #2563eb; border-color: #cbd5e1; font-size: 0.8125rem; padding: 0.35rem 0.75rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; background: #fff;">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm btn-remove-site" style="color: #ef4444; border-color: #fecaca; font-size: 0.8125rem; padding: 0.35rem 0.75rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; background: #fff;">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Remove Confirmation Prompt (hidden by default) -->
                            <div class="site-delete-confirm" style="display: none; margin-top: 0.75rem; padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px;">
                                <form method="POST" action="<?= url('/admin/sites/' . $s['id'] . '/delete') ?>" class="site-delete-form" style="margin: 0; width: 100%; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                    <?= csrf_field() ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: #991b1b; font-weight: 500;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>Are you sure you want to remove this site?</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <button type="button" class="btn btn-outline btn-sm btn-cancel-remove" style="background: #fff; border-color: #cbd5e1; color: #475569; font-size: 0.75rem; padding: 0.3rem 0.75rem; border-radius: 4px; cursor: pointer;">
                                            Cancel
                                        </button>
                                        <button type="submit" class="btn btn-sm btn-confirm-remove" style="background: #ef4444; color: #fff; border: 1px solid #dc2626; font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.85rem; border-radius: 4px; cursor: pointer;">
                                            Remove
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Edit Mode (hidden by default) -->
                            <div class="site-edit-mode" style="display: none; padding-top: 0.25rem;">
                                <form method="POST" action="<?= url('/admin/sites/' . $s['id'] . '/update') ?>" class="site-inline-edit-form">
                                    <?= csrf_field() ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 0.75rem;">
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Site Name *</label>
                                                <input type="text" name="site_name" class="form-control form-control-sm input-site-name" value="<?= e($s['site_name']) ?>" required>
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Site Code</label>
                                                <input type="text" name="site_code" class="form-control form-control-sm input-site-code" value="<?= e($s['site_code']) ?>" style="font-family: monospace;">
                                            </div>
                                        </div>

                                        <div style="position: relative;">
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Site Specific Address / Gate Details</label>
                                            <div class="autocomplete-container" style="position: relative;">
                                                <input type="text" name="site_address" class="form-control form-control-sm site-address-input input-site-address" value="<?= e($s['site_address'] ?? '') ?>" placeholder="Search address or type details" autocomplete="off">
                                                <div class="autocomplete-spinner" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" style="animation: spin 0.8s linear infinite;">
                                                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                                                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                                                    </svg>
                                                </div>
                                                <div class="autocomplete-dropdown" style="display: none;"></div>
                                            </div>
                                            <div class="geocode-msg" style="display: none; font-size: 0.75rem; margin-top: 0.35rem;"></div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Zone / Gate</label>
                                                <input type="text" name="zone_gate" class="form-control form-control-sm input-zone-gate" value="<?= e($s['zone_gate'] ?? '') ?>" placeholder="Gate B">
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Latitude (GPS)</label>
                                                <input type="number" step="0.0000001" name="latitude" class="form-control form-control-sm input-latitude" value="<?= e($s['latitude'] ?? '') ?>" placeholder="18.520000">
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Longitude (GPS)</label>
                                                <input type="number" step="0.0000001" name="longitude" class="form-control form-control-sm input-longitude" value="<?= e($s['longitude'] ?? '') ?>" placeholder="73.856000">
                                            </div>
                                        </div>

                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; margin-top: 0.25rem;">
                                            <div class="edit-status-msg" style="flex: 1; font-size: 0.75rem;"></div>
                                            <button type="button" class="btn btn-outline btn-sm btn-cancel-edit" style="color: #64748b; border-color: #cbd5e1; font-size: 0.8125rem; padding: 0.4rem 0.85rem; border-radius: 6px; background: #fff; cursor: pointer;">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-sm btn-save-site" style="padding: 0.4rem 1.15rem; font-size: 0.8125rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                                                Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Another Site Form -->
        <div class="card add-site-card" style="padding: 1.75rem;">
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

                <div style="margin-bottom: 0.75rem; position: relative;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Site Specific Address / Gate Details</label>
                    <div class="autocomplete-container" style="position: relative;">
                        <input type="text" name="site_address" class="form-control site-address-input" placeholder="Type address to search (e.g. Infipre Goa / 500 Commerce Way)" autocomplete="off">
                        <div class="autocomplete-spinner" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" style="animation: spin 0.8s linear infinite;">
                                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                            </svg>
                        </div>
                        <div class="autocomplete-dropdown" style="display: none;"></div>
                    </div>
                    <div class="geocode-msg" style="display: none; font-size: 0.75rem; margin-top: 0.35rem;"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Zone / Gate</label>
                        <input type="text" name="zone_gate" class="form-control" placeholder="Gate B">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Latitude (GPS)</label>
                        <input type="number" step="0.0000001" name="latitude" class="form-control" placeholder="18.520000">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Longitude (GPS)</label>
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

<style>
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    margin-top: 4px;
    max-height: 250px;
    overflow-y: auto;
}
.autocomplete-item {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    transition: background 0.15s ease;
}
.autocomplete-item:last-child {
    border-bottom: none;
}
.autocomplete-item:hover, .autocomplete-item.active {
    background: #eff6ff;
}
.autocomplete-item .place-icon {
    margin-top: 3px;
    color: #64748b;
    flex-shrink: 0;
}
.autocomplete-item:hover .place-icon, .autocomplete-item.active .place-icon {
    color: #2563eb;
}
.autocomplete-item .place-main {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
}
.autocomplete-item .place-secondary {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 2px;
    line-height: 1.3;
}
.autocomplete-empty {
    padding: 0.75rem;
    font-size: 0.8rem;
    color: #94a3b8;
    text-align: center;
}
.autocomplete-loading {
    padding: 0.75rem;
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    /**
     * Reusable, isolated autocomplete initializer for any card/container
     */
    function initAutocomplete(card) {
        const addrInput = card.querySelector('.site-address-input');
        const autoContainer = card.querySelector('.autocomplete-container');
        const dropdown = card.querySelector('.autocomplete-dropdown');
        const spinner = card.querySelector('.autocomplete-spinner');
        const msgEl = card.querySelector('.geocode-msg');
        const latInput = card.querySelector('input[name="latitude"]');
        const lngInput = card.querySelector('input[name="longitude"]');

        if (!addrInput || !dropdown || !latInput || !lngInput) return;

        let debounceTimer = null;
        let activeIndex = -1;
        let currentPredictions = [];
        let latestRequestId = 0;
        let isSelectingSuggestion = false;
        let lastSelectedAddress = '';
        let selectedPlaceId = '';

        function showSpinner(show) {
            if (spinner) spinner.style.display = show ? 'block' : 'none';
        }

        function showMessage(text, isError = false) {
            if (!msgEl) return;
            msgEl.style.display = 'block';
            msgEl.innerHTML = `<span style="color: ${isError ? '#ef4444' : '#16a34a'}; font-weight: 500;">${text}</span>`;
            setTimeout(() => {
                msgEl.style.display = 'none';
                msgEl.innerHTML = '';
            }, isError ? 5000 : 4000);
        }

        function closeDropdown() {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            activeIndex = -1;
            currentPredictions = [];
        }

        function renderDropdown(predictions) {
            currentPredictions = predictions;
            activeIndex = -1;
            if (!predictions || predictions.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-empty">No matching places found</div>';
                dropdown.style.display = 'block';
                return;
            }

            let html = '';
            predictions.forEach((p, idx) => {
                const main = escapeHtml(p.main_text || p.description);
                const secondary = escapeHtml(p.secondary_text || '');
                html += `
                    <div class="autocomplete-item" data-index="${idx}">
                        <svg class="place-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <div style="flex: 1; min-width: 0;">
                            <div class="place-main">${main}</div>
                            ${secondary ? `<div class="place-secondary">${secondary}</div>` : ''}
                        </div>
                    </div>
                `;
            });
            dropdown.innerHTML = html;
            dropdown.style.display = 'block';

            dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const idx = parseInt(this.getAttribute('data-index'), 10);
                    selectPrediction(currentPredictions[idx]);
                });
            });
        }

        async function selectPrediction(p) {
            if (!p || !p.place_id) return;
            isSelectingSuggestion = true;
            clearTimeout(debounceTimer);
            closeDropdown();
            showSpinner(true);

            selectedPlaceId = p.place_id;
            const chosenText = p.description || p.main_text || '';
            lastSelectedAddress = chosenText;
            addrInput.value = chosenText;

            try {
                const detailsEndpoint = '<?= url('/admin/geocode/details') ?>?place_id=' + encodeURIComponent(p.place_id);
                const res = await fetch(detailsEndpoint, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (data.success && data.latitude != null && data.longitude != null) {
                    const selectedAddress = data.formatted_address || chosenText;
                    lastSelectedAddress = selectedAddress;
                    addrInput.value = selectedAddress;

                    latInput.value = parseFloat(data.latitude).toFixed(6);
                    lngInput.value = parseFloat(data.longitude).toFixed(6);
                    latInput.dispatchEvent(new Event('change', { bubbles: true }));
                    lngInput.dispatchEvent(new Event('change', { bubbles: true }));

                    showMessage(`✓ Coordinates set (${parseFloat(data.latitude).toFixed(6)}, ${parseFloat(data.longitude).toFixed(6)})`);
                } else {
                    showMessage(data.message || 'Unable to get coordinates for selected place.', true);
                }
            } catch (err) {
                showMessage('Network error while fetching place details.', true);
            } finally {
                showSpinner(false);
                setTimeout(() => {
                    isSelectingSuggestion = false;
                }, 200);
            }
        }

        async function fetchAutocomplete(query) {
            const thisRequestId = ++latestRequestId;
            showSpinner(true);
            dropdown.innerHTML = `
                <div class="autocomplete-loading">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="animation: spin 0.8s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                    </svg>
                    <span>Searching places...</span>
                </div>
            `;
            dropdown.style.display = 'block';

            try {
                let url = '<?= url('/admin/geocode/autocomplete') ?>?input=' + encodeURIComponent(query);
                const existingLat = parseFloat(latInput.value);
                const existingLng = parseFloat(lngInput.value);
                if (!isNaN(existingLat) && !isNaN(existingLng) && existingLat !== 0 && existingLng !== 0) {
                    url += '&location=' + encodeURIComponent(existingLat + ',' + existingLng);
                }

                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (thisRequestId !== latestRequestId) return;

                if (data.success && Array.isArray(data.predictions)) {
                    renderDropdown(data.predictions);
                } else {
                    renderDropdown([]);
                }
            } catch (err) {
                if (thisRequestId === latestRequestId) {
                    renderDropdown([]);
                }
            } finally {
                if (thisRequestId === latestRequestId) {
                    showSpinner(false);
                }
            }
        }

        addrInput.addEventListener('input', function () {
            if (isSelectingSuggestion) return;
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query === '' || query === lastSelectedAddress) {
                closeDropdown();
                showSpinner(false);
                return;
            }

            if (query.length < 3) {
                closeDropdown();
                showSpinner(false);
                return;
            }

            debounceTimer = setTimeout(() => {
                if (isSelectingSuggestion) return;
                fetchAutocomplete(query);
            }, 300);
        });

        addrInput.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.autocomplete-item');
            if (dropdown.style.display !== 'none' && items.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    updateActiveItem(items);
                    return;
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + items.length) % items.length;
                    updateActiveItem(items);
                    return;
                } else if (e.key === 'Enter') {
                    if (activeIndex >= 0 && activeIndex < currentPredictions.length) {
                        e.preventDefault();
                        selectPrediction(currentPredictions[activeIndex]);
                        return;
                    }
                } else if (e.key === 'Escape') {
                    closeDropdown();
                    return;
                }
            }
        });

        function updateActiveItem(items) {
            items.forEach((item, idx) => {
                if (idx === activeIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (autoContainer && !autoContainer.contains(e.target)) {
                closeDropdown();
            }
        });
    }

    // Initialize Add Another Site card
    const addSiteCard = document.querySelector('.add-site-card');
    if (addSiteCard) {
        initAutocomplete(addSiteCard);
    }

    // Initialize and wire up Registered Site cards (Inline Edit / Cancel / Save)
    const siteCards = document.querySelectorAll('.registered-site-card');
    siteCards.forEach(card => {
        initAutocomplete(card);

        const btnEdit = card.querySelector('.btn-edit-site');
        const btnCancel = card.querySelector('.btn-cancel-edit');
        const form = card.querySelector('.site-inline-edit-form');
        const displayMode = card.querySelector('.site-display-mode');
        const editMode = card.querySelector('.site-edit-mode');
        const btnSave = card.querySelector('.btn-save-site');
        const statusMsg = card.querySelector('.edit-status-msg');

        const inputName = card.querySelector('.input-site-name');
        const inputCode = card.querySelector('.input-site-code');
        const inputAddress = card.querySelector('.input-site-address');
        const inputZone = card.querySelector('.input-zone-gate');
        const inputLat = card.querySelector('.input-latitude');
        const inputLng = card.querySelector('.input-longitude');

        let initialValues = {
            name: inputName ? inputName.value : '',
            code: inputCode ? inputCode.value : '',
            address: inputAddress ? inputAddress.value : '',
            zone: inputZone ? inputZone.value : '',
            lat: inputLat ? inputLat.value : '',
            lng: inputLng ? inputLng.value : '',
        };

        const btnRemove = card.querySelector('.btn-remove-site');
        const deleteConfirm = card.querySelector('.site-delete-confirm');
        const btnCancelRemove = card.querySelector('.btn-cancel-remove');
        const deleteForm = card.querySelector('.site-delete-form');
        const btnConfirmRemove = card.querySelector('.btn-confirm-remove');

        if (btnEdit && displayMode && editMode) {
            btnEdit.addEventListener('click', function () {
                // Snapshot original values on opening
                initialValues = {
                    name: inputName ? inputName.value : '',
                    code: inputCode ? inputCode.value : '',
                    address: inputAddress ? inputAddress.value : '',
                    zone: inputZone ? inputZone.value : '',
                    lat: inputLat ? inputLat.value : '',
                    lng: inputLng ? inputLng.value : '',
                };

                if (deleteConfirm) deleteConfirm.style.display = 'none';
                displayMode.style.display = 'none';
                editMode.style.display = 'block';
                if (statusMsg) statusMsg.innerHTML = '';
                if (inputName) inputName.focus();
            });
        }

        if (btnCancel && displayMode && editMode) {
            btnCancel.addEventListener('click', function () {
                // Discard changes & restore original values
                if (inputName) inputName.value = initialValues.name;
                if (inputCode) inputCode.value = initialValues.code;
                if (inputAddress) inputAddress.value = initialValues.address;
                if (inputZone) inputZone.value = initialValues.zone;
                if (inputLat) inputLat.value = initialValues.lat;
                if (inputLng) inputLng.value = initialValues.lng;

                const dropdown = card.querySelector('.autocomplete-dropdown');
                if (dropdown) dropdown.style.display = 'none';
                const spinner = card.querySelector('.autocomplete-spinner');
                if (spinner) spinner.style.display = 'none';
                if (statusMsg) statusMsg.innerHTML = '';

                editMode.style.display = 'none';
                displayMode.style.display = 'flex';
            });
        }

        // Remove Site Handlers
        if (btnRemove && deleteConfirm) {
            btnRemove.addEventListener('click', function () {
                if (editMode) editMode.style.display = 'none';
                if (displayMode) displayMode.style.display = 'flex';
                deleteConfirm.style.display = 'block';
            });
        }

        if (btnCancelRemove && deleteConfirm) {
            btnCancelRemove.addEventListener('click', function () {
                deleteConfirm.style.display = 'none';
            });
        }

        if (deleteForm) {
            deleteForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (btnConfirmRemove) {
                    btnConfirmRemove.disabled = true;
                    btnConfirmRemove.textContent = 'Removing...';
                }

                try {
                    const formData = new FormData(deleteForm);
                    const res = await fetch(deleteForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await res.json();

                    if (data.success) {
                        card.style.transition = 'all 0.25s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.96)';
                        setTimeout(() => {
                            card.remove();
                            const remainingCards = document.querySelectorAll('.registered-site-card');
                            const countHeader = document.querySelector('.card h3');
                            if (countHeader && countHeader.textContent.includes('Registered Sites')) {
                                countHeader.textContent = `Registered Sites (${remainingCards.length})`;
                            }
                            if (remainingCards.length === 0) {
                                const listContainer = card.parentElement;
                                if (listContainer) {
                                    listContainer.innerHTML = '<p style="font-size: 0.875rem; color: #64748b;">No sites currently registered for this client.</p>';
                                }
                            }
                        }, 250);
                    } else {
                        alert(data.message || 'Failed to remove site.');
                        if (btnConfirmRemove) {
                            btnConfirmRemove.disabled = false;
                            btnConfirmRemove.textContent = 'Remove';
                        }
                    }
                } catch (err) {
                    // Fallback to standard HTTP POST form submission
                    deleteForm.submit();
                }
            });
        }

        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (!inputName || !inputName.value.trim()) {
                    if (statusMsg) {
                        statusMsg.innerHTML = '<span style="color: #ef4444; font-weight: 500;">Site name is required.</span>';
                    }
                    return;
                }

                if (btnSave) {
                    btnSave.disabled = true;
                    btnSave.textContent = 'Saving...';
                }
                if (statusMsg) {
                    statusMsg.innerHTML = '<span style="color: #64748b;">Saving changes...</span>';
                }

                try {
                    const formData = new FormData(form);
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await res.json();

                    if (data.success && data.site) {
                        // Update baseline snapshot with saved values
                        initialValues = {
                            name: data.site.site_name || '',
                            code: data.site.site_code || '',
                            address: data.site.site_address || '',
                            zone: data.site.zone_gate || '',
                            lat: data.site.latitude != null ? data.site.latitude : '',
                            lng: data.site.longitude != null ? data.site.longitude : '',
                        };

                        // Update display mode UI
                        const displayName = card.querySelector('.display-site-name');
                        if (displayName) displayName.textContent = data.site.site_name;

                        const displayCode = card.querySelector('.display-site-code');
                        if (displayCode) displayCode.textContent = data.site.site_code || '';

                        const displayAddr = card.querySelector('.display-site-address');
                        if (displayAddr) displayAddr.textContent = data.site.site_address || 'No address specified';

                        const displayZone = card.querySelector('.display-zone');
                        if (displayZone) displayZone.textContent = data.site.zone_gate || 'General';

                        const displayLat = card.querySelector('.display-lat');
                        if (displayLat) displayLat.textContent = data.site.latitude != null ? data.site.latitude : '—';

                        const displayLng = card.querySelector('.display-lng');
                        if (displayLng) displayLng.textContent = data.site.longitude != null ? data.site.longitude : '—';

                        // Exit edit mode
                        editMode.style.display = 'none';
                        displayMode.style.display = 'flex';
                        if (statusMsg) statusMsg.innerHTML = '';
                    } else {
                        if (statusMsg) {
                            statusMsg.innerHTML = `<span style="color: #ef4444; font-weight: 500;">${escapeHtml(data.message || 'Failed to update site.')}</span>`;
                        }
                    }
                } catch (err) {
                    // Fallback to standard form submission if network or json parse fails
                    form.submit();
                } finally {
                    if (btnSave) {
                        btnSave.disabled = false;
                        btnSave.textContent = 'Save Changes';
                    }
                }
            });
        }
    });
});
</script>
