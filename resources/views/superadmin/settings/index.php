<?php
/**
 * Superadmin Platform Settings View
 * Sleek, modern two-column platform configuration and security control center.
 */
$user = $user ?? [];
$activeTab = $activeTab ?? 'account';
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Platform Settings</h1>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- Two-Column Settings Layout -->
    <div class="settings-layout-grid">
        
        <!-- Left Column: Settings Navigation Panel -->
        <nav class="settings-nav-card" aria-label="Superadmin Settings Navigation">
            <button type="button" class="settings-nav-item <?= $activeTab === 'account' ? 'active' : '' ?>" data-tab="account" onclick="switchSettingsTab('account')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Account</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>" data-tab="security" onclick="switchSettingsTab('security')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                <span>Security</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'platform' ? 'active' : '' ?>" data-tab="platform" onclick="switchSettingsTab('platform')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Platform &amp; Tenants</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'system' ? 'active' : '' ?>" data-tab="system" onclick="switchSettingsTab('system')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span>System</span>
            </button>
            <button type="button" class="settings-nav-item <?= $activeTab === 'billing' ? 'active' : '' ?>" data-tab="billing" onclick="switchSettingsTab('billing')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Subscription &amp; Billing</span>
            </button>
        </nav>

        <!-- Right Column: Settings Content Panes -->
        <main class="settings-content-card">

            <!-- 1. ACCOUNT SETTINGS -->
            <div id="tab-account" class="settings-tab-pane <?= $activeTab === 'account' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Superadmin Profile</h2>
                <p class="settings-section-desc">Manage platform master administrator credentials and identity details.</p>

                <form method="POST" action="<?= url('/superadmin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="account">

                    <div class="settings-form-container">
                        
                        <!-- Account Identity Banner -->
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; width: 100%;">
                            <div class="client-avatar" style="width: 44px; height: 44px; font-size: 1.05rem; flex-shrink: 0; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">
                                <?= strtoupper(substr($user['full_name'] ?? $user['name'] ?? 'S', 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-size: 0.9375rem; font-weight: 700; color: #0f172a;">
                                    <?= e($user['full_name'] ?? $user['name'] ?? 'Super Administrator') ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.2rem;">
                                    <span class="badge-site-count" style="height: 20px; font-size: 0.7rem; padding: 0 0.5rem; background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;">
                                        Master Superadmin
                                    </span>
                                    <span style="font-size: 0.75rem; color: #64748b; font-family: monospace;">
                                        ROOT-001
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Full Name & Login Email -->
                        <div class="settings-grid-2">
                            <!-- Name -->
                            <div class="form-group">
                                <label class="form-label">Super Administrator Full Name <span class="required-star">*</span></label>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <input type="text" name="full_name" class="form-control" placeholder="Super Administrator" value="<?= e($user['full_name'] ?? $user['name'] ?? '') ?>" required>
                                </div>
                            </div>

                            <!-- Login Email (Read-Only) -->
                            <div class="form-group">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem;">
                                    <label class="form-label" style="margin-bottom: 0;">Master Login Email</label>
                                    <span style="font-size: 0.7rem; color: #64748b; background: #f1f5f9; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0; font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                        Read-Only
                                    </span>
                                </div>
                                <div class="input-icon-wrapper">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <input type="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" readonly style="background: #f8fafc; color: #475569; cursor: not-allowed;" title="Superadmin login email is protected and cannot be changed here.">
                                </div>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Primary platform master login email identity.</span>
                            </div>
                        </div>

                        <!-- Contact Phone Number -->
                        <div class="form-group">
                            <label class="form-label">Direct Contact Phone</label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 2. SECURITY SETTINGS -->
            <div id="tab-security" class="settings-tab-pane <?= $activeTab === 'security' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Security &amp; Authentication</h2>
                <p class="settings-section-desc">Manage platform master password, root authentication, and session security.</p>

                <form method="POST" action="<?= url('/superadmin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="security">

                    <div class="settings-form-container">
                        
                        <div class="form-group">
                            <label class="form-label">Current Master Password <span class="required-star">*</span></label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                <input type="password" name="current_password" class="form-control" placeholder="••••••••••••" required>
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">New Master Password <span class="required-star">*</span></label>
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

                        <!-- Session Security Card -->
                        <div style="padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; width: 100%;">
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">
                                Master Session &amp; System Hardening
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4;">
                                Superadmin access is protected with strict role-based authorization, BCrypt salted password hashing, and full tenant boundary isolation.
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Update Master Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 3. PLATFORM & TENANTS -->
            <div id="tab-platform" class="settings-tab-pane <?= $activeTab === 'platform' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Platform &amp; Tenant Governance</h2>
                <p class="settings-section-desc">Configure global tenant isolation, guard telemetry policies, and customer onboarding defaults.</p>

                <form method="POST" action="<?= url('/superadmin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="platform">

                    <div class="settings-form-container">
                        
                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Multi-Tenant Isolation Mode</label>
                                <select name="isolation_mode" class="form-select">
                                    <option value="strict" selected>Strict Multi-Tenant (Scoped via organization_id)</option>
                                    <option value="hybrid">Hybrid (Superadmin Global Override)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Tenant Onboarding Mode</label>
                                <select name="onboarding_mode" class="form-select">
                                    <option value="admin_provision" selected>Superadmin Provisioned (Manual Approval)</option>
                                    <option value="invite_only">Invite Token Provisioning</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Default Guard GPS Precision</label>
                                <select name="gps_precision" class="form-select">
                                    <option value="high" selected>High Accuracy (GPS Telemetry &lt; 15m)</option>
                                    <option value="balanced">Balanced Cellular / WiFi Telemetry</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Tenant Suspension Policy</label>
                                <select name="suspension_policy" class="form-select">
                                    <option value="immediate" selected>Immediate Lock (Block all tenant web &amp; mobile logins)</option>
                                    <option value="read_only">Read-Only Archive Access</option>
                                </select>
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Platform Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 4. SYSTEM PREFERENCES -->
            <div id="tab-system" class="settings-tab-pane <?= $activeTab === 'system' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Global System Preferences</h2>
                <p class="settings-section-desc">Customize default timezone, date formats, and live metrics refresh frequency.</p>

                <form method="POST" action="<?= url('/superadmin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="system">

                    <div class="settings-form-container">
                        
                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Platform Timezone</label>
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
                                <label class="form-label">Global Date Display Format</label>
                                <select name="date_format" class="form-select">
                                    <option value="d M Y" selected>13 Sep 2026 (DD MMM YYYY)</option>
                                    <option value="Y-m-d">2026-09-13 (YYYY-MM-DD)</option>
                                    <option value="d/m/Y">13/09/2026 (DD/MM/YYYY)</option>
                                    <option value="m/d/Y">09/13/2026 (MM/DD/YYYY)</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Live Dashboard Refresh Interval</label>
                                <select name="refresh_interval" class="form-select">
                                    <option value="30">Every 30 Seconds</option>
                                    <option value="60" selected>Every 1 Minute</option>
                                    <option value="300">Every 5 Minutes</option>
                                    <option value="manual">Manual Refresh</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Audit Log Retention</label>
                                <select name="log_retention" class="form-select">
                                    <option value="90" selected>90 Days Operational Retention</option>
                                    <option value="180">180 Days Compliance Retention</option>
                                    <option value="365">1 Year Full Enterprise History</option>
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

            <!-- 5. SUBSCRIPTION & BILLING -->
            <div id="tab-billing" class="settings-tab-pane <?= $activeTab === 'billing' ? 'active' : '' ?>">
                <h2 class="settings-section-title">Subscription &amp; Guard Licensing Policies</h2>
                <p class="settings-section-desc">Configure platform-wide base pricing per guard, billing currency, and renewal notification windows.</p>

                <form method="POST" action="<?= url('/superadmin/settings') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="billing">

                    <div class="settings-form-container">
                        <div class="alert alert-info" style="display: flex; align-items: flex-start; gap: 0.75rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 8px; padding: 0.875rem 1rem; margin-bottom: 1.25rem;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div style="font-size: 0.85rem; line-height: 1.45;">
                                <strong>Default Pricing Notice:</strong> Changes made here set the baseline rate for new organisations and new subscription cycles. Existing active subscriptions retain their locked contractual rates until manually adjusted or renewed.
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">Default Price Per Guard (Per Cycle) <span class="required-star">*</span></label>
                                <div class="input-icon-wrapper">
                                    <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b; font-size: 0.9rem;">₹</span>
                                    <input type="number" step="0.01" min="0" name="price_per_guard" class="form-control" style="padding-left: 2rem;" value="<?= e((string)($pricePerGuard ?? 500.00)) ?>" required placeholder="500.00">
                                </div>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Standard rate charged per guard slot per subscription period.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Platform Billing Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="INR" <?= ($currency ?? 'INR') === 'INR' ? 'selected' : '' ?>>INR (₹ - Indian Rupee)</option>
                                    <option value="USD" <?= ($currency ?? '') === 'USD' ? 'selected' : '' ?>>USD ($ - US Dollar)</option>
                                    <option value="AED" <?= ($currency ?? '') === 'AED' ? 'selected' : '' ?>>AED (د.إ - UAE Dirham)</option>
                                    <option value="EUR" <?= ($currency ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (€ - Euro)</option>
                                    <option value="GBP" <?= ($currency ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (£ - British Pound)</option>
                                </select>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Official currency code rendered on generated invoices.</span>
                            </div>
                        </div>

                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label">"Expiring Soon" Warning Window (Days)</label>
                                <input type="number" min="1" max="90" name="expiring_soon_days" class="form-control" value="<?= e((string)($expiringDays ?? 15)) ?>" required>
                                <span style="font-size: 0.725rem; color: #64748b; margin-top: 0.25rem; display: block;">Days prior to expiry when amber banners and renewal alerts appear in Admin portals.</span>
                            </div>
                        </div>

                        <!-- Submit Button Row -->
                        <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600;">
                                Save Subscription Defaults
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
    if (tabParam && ['account', 'security', 'platform', 'system', 'billing'].includes(tabParam)) {
        switchSettingsTab(tabParam);
    }
});
</script>
