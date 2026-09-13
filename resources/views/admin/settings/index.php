<?php
/**
 * Organisation Admin Settings & Control Center View
 * Clean, modern, sleek, and professional two-column settings interface.
 */
$user = $user ?? [];
$organization = $organization ?? [];
$activeTab = $activeTab ?? 'account';
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.75rem;">
        <div>
            <h1 class="page-header-title">Settings</h1>
            <p class="page-header-desc">Manage your Secure360 account, system preferences, and configuration.</p>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Two-Column Settings Layout -->
    <div class="settings-layout-grid">
        
        <!-- Left Column: Compact Settings Navigation Panel -->
        <nav class="settings-nav-card" aria-label="Settings Navigation">
            <button type="button" class="settings-nav-item <?= $activeTab === 'account' ? 'active' : '' ?>" data-tab="account" onclick="switchSettingsTab('account')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Account</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>" data-tab="security" onclick="switchSettingsTab('security')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                <span>Security</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'organisation' ? 'active' : '' ?>" data-tab="organisation" onclick="switchSettingsTab('organisation')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Organisation</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'notifications' ? 'active' : '' ?>" data-tab="notifications" onclick="switchSettingsTab('notifications')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span>Notification Preferences</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'system' ? 'active' : '' ?>" data-tab="system" onclick="switchSettingsTab('system')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span>System</span>
            </button>
        </nav>

        <!-- Right Column: Settings Content Panes -->
        <main class="settings-content-card">

            <!-- 1. ACCOUNT SETTINGS -->
            <div id="tab-account" class="settings-tab-pane <?= $activeTab === 'account' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Account Settings</h2>
                <p class="settings-section-desc">Manage your administrator profile, contact details, and account identity.</p>

                <form method="POST" action="<?= url('/admin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="account">

                    <div class="settings-form-container">
                        
                        <!-- Account Details Box (Compact & Full Width) -->
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; width: 100%;">
                            <div class="client-avatar" style="width: 44px; height: 44px; font-size: 1.05rem; flex-shrink: 0;">
                                <?= strtoupper(substr($user['full_name'] ?? $user['name'] ?? 'A', 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-size: 0.9375rem; font-weight: 700; color: #0f172a;">
                                    <?= e($user['full_name'] ?? $user['name'] ?? 'Admin User') ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.2rem;">
                                    <span class="badge-site-count" style="height: 20px; font-size: 0.7rem; padding: 0 0.5rem;">
                                        <?= e($user['role_name'] ?? 'Organisation Admin') ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: #64748b; font-family: monospace;">
                                        <?= e($user['employee_code'] ?? 'ADM-001') ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Two-Column Fields: Full Name & Login Email -->
                        <div class="settings-grid-2">
                            <!-- Name -->
                            <div class="form-group">
                                <label class="form-label">Admin Full Name <span class="required-star">*</span></label>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <input type="text" name="full_name" class="form-control" placeholder="Mayur Ghadi" value="<?= e($user['full_name'] ?? $user['name'] ?? '') ?>" required>
                                </div>
                            </div>

                            <!-- Email (Read-Only) -->
                            <div class="form-group">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem;">
                                    <label class="form-label" style="margin-bottom: 0;">Login Email Address</label>
                                    <span style="font-size: 0.7rem; color: #64748b; background: #f1f5f9; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0; font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                        Read-Only
                                    </span>
                                </div>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <input type="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" readonly style="background: #f8fafc; color: #475569; cursor: not-allowed;" title="Login email cannot be modified directly.">
                                </div>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Primary account login identity. Cannot be modified directly.</span>
                            </div>
                        </div>

                        <!-- Contact Phone Number -->
                        <div class="form-group">
                            <label class="form-label">Contact Phone Number</label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 2. SECURITY SETTINGS -->
            <div id="tab-security" class="settings-tab-pane <?= $activeTab === 'security' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Security Settings</h2>
                <p class="settings-section-desc">Manage account password, access authentication, and session security.</p>

                <form method="POST" action="<?= url('/admin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="security">

                    <div class="settings-form-container">
                        
                        <div class="form-group">
                            <label class="form-label">Current Password <span class="required-star">*</span></label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                <input type="password" name="current_password" class="form-control" placeholder="••••••••••••" required>
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">New Password <span class="required-star">*</span></label>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <input type="password" name="new_password" class="form-control" placeholder="••••••••••••" required minlength="6">
                                </div>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Minimum 6 characters.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirm New Password <span class="required-star">*</span></label>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••••••" required minlength="6">
                                </div>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Re-enter matching password.</span>
                            </div>
                        </div>

                        <!-- Session Security Info (Full Width) -->
                        <div style="padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; width: 100%;">
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">
                                Active Session &amp; Tenant Protection
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4;">
                                Your session is guarded with CSRF protection, secure HTTP-only cookies, and multi-tenant organization isolation.
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Update Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 3. ORGANISATION PROFILE -->
            <div id="tab-organisation" class="settings-tab-pane <?= $activeTab === 'organisation' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Organisation Profile</h2>
                <p class="settings-section-desc">Manage security agency profile, registered office, and dispatch contact details.</p>

                <form method="POST" action="<?= url('/admin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="organisation">

                    <div class="settings-form-container">
                        
                        <div class="form-group">
                            <label class="form-label">Organisation / Agency Name <span class="required-star">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Apex Security Services" value="<?= e($organization['name'] ?? '') ?>" required>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Tenant Code</label>
                                <input type="text" class="form-control" value="<?= e($organization['organization_code'] ?? '') ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 700; letter-spacing: 0.05em;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" placeholder="Managing Director" value="<?= e($organization['contact_person'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem;">
                                    <label class="form-label" style="margin-bottom: 0;">Organisation Email</label>
                                    <span style="font-size: 0.7rem; color: #64748b; background: #f1f5f9; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0; font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                        Read-Only
                                    </span>
                                </div>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <input type="email" class="form-control" value="<?= e($organization['email'] ?? '') ?>" readonly style="background: #f8fafc; color: #475569; cursor: not-allowed;" title="Organisation email cannot be modified directly.">
                                </div>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Official organisation email address. Cannot be modified directly.</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone / Dispatch Hotline</label>
                                <input type="text" name="phone" class="form-control" placeholder="+1 (555) 0100" value="<?= e($organization['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">HQ Physical / Dispatch Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Corporate HQ location"><?= e($organization['address'] ?? '') ?></textarea>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 4. NOTIFICATIONS -->
            <div id="tab-notifications" class="settings-tab-pane <?= $activeTab === 'notifications' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Notification Preferences</h2>
                <p class="settings-section-desc">Configure automated operational alerts, attendance events, and guard telemetry feeds.</p>

                <form method="POST" action="<?= url('/admin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="notifications">

                    <div class="settings-form-container">
                        
                        <!-- Toggle 1 -->
                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-title">Attendance &amp; Shift Alerts</div>
                                <div class="settings-toggle-desc">Receive real-time notifications for guard check-ins, check-outs, and late arrival alerts.</div>
                            </div>
                            <label class="toggle-switch" aria-label="Toggle Attendance Alerts">
                                <input type="checkbox" name="notify_attendance" value="1" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 2 -->
                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-title">Guard Patrol &amp; Assignment Activity</div>
                                <div class="settings-toggle-desc">Receive notifications for duty site reallocations, emergency SOS alerts, and patrol updates.</div>
                            </div>
                            <label class="toggle-switch" aria-label="Toggle Guard Activity">
                                <input type="checkbox" name="notify_guard_activity" value="1" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 3 -->
                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-title">System &amp; Maintenance Notices</div>
                                <div class="settings-toggle-desc">Receive platform updates, security patches, and periodic operational summary reports.</div>
                            </div>
                            <label class="toggle-switch" aria-label="Toggle System Notices">
                                <input type="checkbox" name="notify_system" value="1" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 1rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Preferences
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 5. SYSTEM & PREFERENCES -->
            <div id="tab-system" class="settings-tab-pane <?= $activeTab === 'system' ? 'active' : '' ?>">
                <h2 class="settings-section-title">System &amp; Operations Preferences</h2>
                <p class="settings-section-desc">Customize default timezone, date formats, map tiles, and live refresh frequencies.</p>

                <form method="POST" action="<?= url('/admin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="system">

                    <div class="settings-form-container">
                        
                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">System Timezone</label>
                                <select name="timezone" class="form-select">
                                    <option value="Asia/Kolkata" selected>Asia/Kolkata (IST +05:30)</option>
                                    <option value="UTC">UTC (Coordinated Universal Time)</option>
                                    <option value="America/New_York">America/New_York (EST -05:00)</option>
                                    <option value="Europe/London">Europe/London (GMT +00:00)</option>
                                    <option value="Asia/Dubai">Asia/Dubai (GST +04:00)</option>
                                    <option value="Asia/Singapore">Asia/Singapore (SGT +08:00)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Default Live Map Layer</label>
                                <select name="map_layer" class="form-select">
                                    <option value="hybrid" selected>Satellite / Hybrid</option>
                                    <option value="streets">OpenStreetMap Standard</option>
                                    <option value="carto_dark">Carto Dark (High Contrast)</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Date Display Format</label>
                                <select name="date_format" class="form-select">
                                    <option value="d M Y" selected>13 Sep 2026 (DD MMM YYYY)</option>
                                    <option value="Y-m-d">2026-09-13 (YYYY-MM-DD)</option>
                                    <option value="d/m/Y">13/09/2026 (DD/MM/YYYY)</option>
                                    <option value="m/d/Y">09/13/2026 (MM/DD/YYYY)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Live Attendance Refresh</label>
                                <select name="refresh_interval" class="form-select">
                                    <option value="30">Every 30 Seconds</option>
                                    <option value="60" selected>Every 1 Minute</option>
                                    <option value="300">Every 5 Minutes</option>
                                    <option value="manual">Manual Refresh</option>
                                </select>
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Preferences
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </main>
    </div>
</div>

<script>
function switchSettingsTab(tabName) {
    // Update navigation buttons
    document.querySelectorAll('.settings-nav-item').forEach(btn => {
        if (btn.getAttribute('data-tab') === tabName) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Update tab panes
    document.querySelectorAll('.settings-tab-pane').forEach(pane => {
        if (pane.id === 'tab-' + tabName) {
            pane.classList.add('active');
        } else {
            pane.classList.remove('active');
        }
    });

    // Update URL query string without reloading page
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && ['account', 'security', 'organisation', 'notifications', 'system'].includes(tabParam)) {
        switchSettingsTab(tabParam);
    }
});
</script>
