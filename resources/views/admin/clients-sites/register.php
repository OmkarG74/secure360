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
    <div class="page-header">
        <div>
            <h1 class="page-header-title"style="margin-bottom: 20px;">Register Client</h1>
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

                        <!-- Row 2: Address with Autocomplete -->
                        <div class="site-grid-row-2">
                            <div class="form-group" style="position: relative;">
                                <label class="form-label">Site Specific Address / Gate Details</label>
                                <div class="autocomplete-container" style="position: relative;">
                                    <input type="text" name="sites[0][site_address]" class="form-control site-address-input" placeholder="Type address to search (e.g. Infipre Goa / 500 Commerce Way)" autocomplete="off">
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

<!-- Client-side Dynamic Site Addition & Autocomplete Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('sitesContainer');
    const btnAdd = document.getElementById('btnAddSite');
    let siteCount = 1;

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function initAutocomplete(card) {
        const addrInput = card.querySelector('.site-address-input');
        const autoContainer = card.querySelector('.autocomplete-container');
        const dropdown = card.querySelector('.autocomplete-dropdown');
        const spinner = card.querySelector('.autocomplete-spinner');
        const msgEl = card.querySelector('.geocode-msg');
        const latInput = card.querySelector('input[name$="[latitude]"], input[name="latitude"]');
        const lngInput = card.querySelector('input[name$="[longitude]"], input[name="longitude"]');

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

        // Keyboard navigation
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

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (autoContainer && !autoContainer.contains(e.target)) {
                closeDropdown();
            }
        });
    }

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

    // Initialize autocomplete for initial static Site #1 card
    const firstCard = container.querySelector('.site-card');
    if (firstCard) {
        initAutocomplete(firstCard);
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
                <div class="form-group" style="position: relative;">
                    <label class="form-label">Site Specific Address / Gate Details</label>
                    <div class="autocomplete-container" style="position: relative;">
                        <input type="text" name="sites[\${newIndex}][site_address]" class="form-control site-address-input" placeholder="Type address (e.g. 510 Commerce Way, Bay 3)" autocomplete="off">
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
        initAutocomplete(card);

        card.querySelector('.btn-remove-site').addEventListener('click', function () {
            card.remove();
            updateRemoveButtons();
        });
    });

    updateRemoveButtons();
});
</script>
