<?php
/**
 * Superadmin Master Control Dashboard View
 * Sleek, modern, enterprise tenant management & platform console
 */
$m = $metrics ?? [
    'totalOrgs' => 0,
    'activeOrgs' => 0,
    'suspendedOrgs' => 0,
    'newOrgs' => 0,
    'totalUsers' => 0,
    'totalGuards' => 0,
];

$recentOrgs = $recentOrgs ?? [];
$recentActivity = $recentActivity ?? [];
$todayDate = format_date(date('Y-m-d'));

$activePct = $m['totalOrgs'] > 0 ? round(($m['activeOrgs'] / $m['totalOrgs']) * 100) : 100;
?>

<style>
/* ==========================================================================
   Superadmin Dashboard Styles
   ========================================================================== */
.sa-dashboard-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
}

/* Header */
.sa-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.sa-title-group h1 {
    font-size: 1.375rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin: 0;
}

.sa-title-group p {
    font-size: 0.8125rem;
    color: #64748b;
    margin-top: 0.25rem;
}

.sa-date-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 0.45rem 0.875rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

/* Compact KPI Strip */
.sa-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.sa-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
    min-height: 95px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.sa-kpi-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.07);
}

.sa-kpi-label {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sa-kpi-val {
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.1;
    margin-top: 0.35rem;
}

.sa-kpi-footer {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.25rem;
}

/* Main Two-Column Layout */
.sa-content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    align-items: start;
}

/* Section Cards */
.sa-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.sa-card-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
}

.sa-card-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sa-card-body {
    padding: 1.25rem;
}

/* Overview Bar */
.sa-status-bar-wrapper {
    margin-bottom: 1rem;
}

.sa-status-bar {
    height: 8px;
    border-radius: 9999px;
    background: #f1f5f9;
    display: flex;
    overflow: hidden;
    margin-top: 0.5rem;
}

.sa-bar-active {
    background: #10b981;
    height: 100%;
}

.sa-bar-suspended {
    background: #f59e0b;
    height: 100%;
}

.sa-status-breakdown {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f8fafc;
}

.sa-breakdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
}

/* Recent Table */
.sa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.sa-table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #475569;
    font-size: 0.725rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid #e2e8f0;
}

.sa-table td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.sa-table tr:hover td {
    background: #fbfcfe;
}

.sa-code-tag {
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: #1e293b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.sa-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
}

.sa-status-pill.active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sa-status-pill.suspended {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

/* Timeline */
.sa-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sa-timeline-item {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}

.sa-timeline-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 0.35rem;
    flex-shrink: 0;
}

.sa-timeline-content {
    flex: 1;
}

.sa-timeline-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.25;
}

.sa-timeline-desc {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.15rem;
    line-height: 1.4;
}

.sa-timeline-time {
    font-size: 0.6875rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}

/* Quick Action Button */
.sa-btn-primary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: #2563eb;
    color: #ffffff;
    padding: 0.65rem 1.25rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s ease;
    border: 1px solid #1d4ed8;
    width: 100%;
}

.sa-btn-primary:hover {
    background: #1d4ed8;
}

@media (max-width: 1024px) {
    .sa-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .sa-content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .sa-kpi-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="sa-dashboard-container">
    <!-- Header -->
    <div class="sa-header-row">
        <div class="sa-title-group">
            <h1>Good morning, Super Administrator</h1>
        </div>
        <div class="sa-date-badge">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span><?= e($todayDate) ?></span>
        </div>
    </div>

    <!-- Compact KPI Strip (Height ~95-110px) -->
    <div class="sa-kpi-grid">
        <!-- 1. Total Organisations -->
        <div class="sa-kpi-card">
            <div class="sa-kpi-label">
                <span>Total Organisations</span>
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
            <div class="sa-kpi-val"><?= e($m['totalOrgs']) ?></div>
            <div class="sa-kpi-footer">Registered tenant accounts</div>
        </div>

        <!-- 2. Active Organisations -->
        <div class="sa-kpi-card">
            <div class="sa-kpi-label">
                <span style="color: #059669;">Active Organisations</span>
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
            </div>
            <div class="sa-kpi-val" style="color: #059669;"><?= e($m['activeOrgs']) ?></div>
            <div class="sa-kpi-footer"><?= e($activePct) ?>% operational rate</div>
        </div>

        <!-- 3. Suspended Organisations -->
        <div class="sa-kpi-card">
            <div class="sa-kpi-label">
                <span style="color: #b45309;">Suspended Organisations</span>
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
            </div>
            <div class="sa-kpi-val" style="color: <?= $m['suspendedOrgs'] > 0 ? '#b45309' : '#64748b' ?>;"><?= e($m['suspendedOrgs']) ?></div>
            <div class="sa-kpi-footer">Under administrative lock</div>
        </div>

        <!-- 4. New Organisations -->
        <div class="sa-kpi-card">
            <div class="sa-kpi-label">
                <span>New Organisations</span>
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            </div>
            <div class="sa-kpi-val" style="color: #2563eb;"><?= e($m['newOrgs']) ?></div>
            <div class="sa-kpi-footer">Recent tenant onboardings</div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="sa-content-grid">
        
        <!-- Left / Main: Overview & Recent Organisations -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Organisation Overview Section -->
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-title">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>Organisation Overview</span>
                    </div>
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                        <?= e($m['totalOrgs']) ?> Total Tenants
                    </span>
                </div>
                <div class="sa-card-body">
                    <div class="sa-status-bar-wrapper">
                        <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 600; color: #334155;">
                            <span>Platform Health &amp; Active Ratio</span>
                            <span style="color: #059669;"><?= e($activePct) ?>% Active</span>
                        </div>
                        <div class="sa-status-bar">
                            <div class="sa-bar-active" style="width: <?= e($activePct) ?>%;"></div>
                            <div class="sa-bar-suspended" style="width: <?= e(100 - $activePct) ?>%;"></div>
                        </div>
                    </div>

                    <div class="sa-status-breakdown">
                        <div class="sa-breakdown-item">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                            <span style="color: #64748b;">Active:</span>
                            <strong style="color: #0f172a;"><?= e($m['activeOrgs']) ?></strong>
                        </div>
                        <div class="sa-breakdown-item">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
                            <span style="color: #64748b;">Suspended:</span>
                            <strong style="color: #0f172a;"><?= e($m['suspendedOrgs']) ?></strong>
                        </div>
                        <div class="sa-breakdown-item">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #2563eb;"></span>
                            <span style="color: #64748b;">Guards Deployed:</span>
                            <strong style="color: #0f172a;"><?= e($m['totalGuards']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Organisations Table -->
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-title">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Recent Organisations</span>
                    </div>
                    <a href="<?= url('/superadmin/organisations') ?>" style="font-size: 0.8125rem; font-weight: 600; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                        <span>View All</span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <?php if (empty($recentOrgs)): ?>
                    <div style="padding: 2.5rem 1.5rem; text-align: center; color: #64748b;">
                        <p style="font-size: 0.875rem; font-weight: 500;">No organisations registered yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th>Organisation</th>
                                    <th>Tenant Code</th>
                                    <th>Contact Person</th>
                                    <th>Email / Phone</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrgs as $org): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #0f172a;"><?= e($org['name'] ?? '—') ?></div>
                                            <div style="font-size: 0.7rem; color: #64748b;"><?= e($org['address'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="sa-code-tag"><?= e($org['organization_code'] ?? 'ORG') ?></span>
                                        </td>
                                        <td style="font-weight: 500; color: #334155;">
                                            <?= e($org['contact_person'] ?? '—') ?>
                                        </td>
                                        <td style="color: #475569;">
                                            <div><?= e($org['email'] ?? '—') ?></div>
                                            <div style="font-size: 0.7rem; color: #94a3b8;"><?= e($org['phone'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <?php if ((int)($org['status'] ?? 0) === 0): ?>
                                                <span class="sa-status-pill active">
                                                    <span style="width: 5px; height: 5px; border-radius: 50%; background: #10b981;"></span>
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="sa-status-pill suspended">
                                                    <span style="width: 5px; height: 5px; border-radius: 50%; background: #f59e0b;"></span>
                                                    Suspended
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #64748b; font-size: 0.75rem; white-space: nowrap;">
                                            <?= format_date($org['created_at'] ?? null) ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="<?= url('/superadmin/organisations') ?>" class="btn" style="padding: 0.25rem 0.6rem; font-size: 0.725rem; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; border-radius: 4px; font-weight: 600;">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right / Secondary Column: Quick Action & Recent Activity -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Quick Action Card -->
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-title">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Quick Action</span>
                    </div>
                </div>
                <div class="sa-card-body">
                    <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem; line-height: 1.45;">
                        Provision a new customer security agency and automatically create their tenant admin credentials.
                    </p>
                    <a href="<?= url('/superadmin/organisations/create') ?>" class="sa-btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>Onboard Organisation</span>
                    </a>
                </div>
            </div>

            <!-- Recent Platform Activity -->
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-title">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Recent Activity</span>
                    </div>
                </div>
                <div class="sa-card-body">
                    <?php if (empty($recentActivity)): ?>
                        <div style="padding: 1.5rem 0.5rem; text-align: center; color: #94a3b8; font-size: 0.8125rem;">
                            No recent activity.
                        </div>
                    <?php else: ?>
                        <div class="sa-timeline">
                            <?php foreach ($recentActivity as $act): ?>
                                <div class="sa-timeline-item">
                                    <div class="sa-timeline-dot" style="background: <?= $act['badge_color'] === 'green' ? '#10b981' : ($act['badge_color'] === 'amber' ? '#f59e0b' : '#2563eb') ?>;"></div>
                                    <div class="sa-timeline-content">
                                        <div class="sa-timeline-title"><?= e($act['title']) ?></div>
                                        <div class="sa-timeline-desc"><?= e($act['description']) ?></div>
                                        <div class="sa-timeline-time">
                                            <?= !empty($act['created_at']) ? format_datetime($act['created_at']) : 'Just now' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>
