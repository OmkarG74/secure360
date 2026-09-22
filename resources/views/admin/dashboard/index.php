<?php
/**
 * Secure360 Admin Operations Dashboard
 * Premium Security Operations Console UI
 */

$m = $metrics ?? [
    'totalCustomers' => 0,
    'totalSites' => 0,
    'totalGuards' => 0,
    'assignedGuards' => 0,
    'onDutyGuards' => 0,
    'offDutyGuards' => 0,
    'activeContracts' => 0,
    'todayTotal' => 0,
    'todayCompleted' => 0,
];

$dutySites = $dutySites ?? [];
$guardLocations = $guardLocations ?? [];
$recentAttendance = $recentAttendance ?? [];
$recentActivities = $recentActivities ?? [];

$currentUser = $user ?? auth() ?? [];
$orgName = $currentUser['organization_name'] ?? 'Apex Security Services';
$userName = $currentUser['full_name'] ?? 'Admin';
$orgId = $currentUser['organization_id'] ?? (\App\Core\Auth::organisationId() ?? 1);

// Time of day greeting
$hour = (int)date('G');
$greeting = ($hour < 12) ? 'Good morning' : (($hour < 17) ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($userName))[0];

// Ola Maps Platform configuration
$olaApiKey = config('app.maps.ola_api_key', '');
?>

<!-- MapLibre & Ola Maps Web SDK v2 -->
<link href="https://unpkg.com/maplibre-gl@latest/dist/maplibre-gl.css" rel="stylesheet" />
<script src="https://www.unpkg.com/olamaps-web-sdk@latest/dist/olamaps-web-sdk.umd.js"></script>
<style>
/* MapLibre Popup Styling for Operational Dashboard */
.maplibregl-popup-content {
    padding: 10px 12px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    border: 1px solid #e2e8f0;
}
.maplibregl-popup-close-button {
    padding: 2px 6px;
    color: #64748b;
    font-size: 16px;
}
.maplibregl-popup-close-button:hover {
    color: #0f172a;
    background: transparent;
}
</style>

<div class="dashboard-container">

    <!-- 1. Compact Intro Header Section -->
    <div class="dashboard-intro-header">
        <div>
            <h1 class="dashboard-greeting"><?= e($greeting) ?>, <?= e($firstName) ?></h1>
        </div>
    </div>

    <?php
        $sub = $subDetails ?? null;
        $guardLimit = (int)($sub['guard_limit'] ?? max(30, $m['totalGuards']));
        $activeGuards = (int)($sub['active_guards'] ?? $m['totalGuards']);
        $validUntil = !empty($sub['end_date']) ? date('d-M-Y', strtotime($sub['end_date'])) : '—';
        $subStatus = ucfirst($sub['calculated_status'] ?? 'Active');
        $isLimitReached = $sub['is_limit_reached'] ?? ($activeGuards >= $guardLimit);
        $isApproaching = $sub['is_approaching_limit'] ?? (($guardLimit - $activeGuards) <= 3 && !$isLimitReached);
    ?>

    <!-- 2. Compact Operational KPI Strip -->
    <div class="metrics-grid-5">
        <!-- Guard Usage / Subscription -->
        <div class="metric-card-compact" style="<?= $isLimitReached ? 'border-color: #fecaca; background: #fffdfd;' : '' ?>">
            <div class="metric-header">
                <span class="metric-label" style="<?= $isLimitReached ? 'color: #dc2626;' : '' ?>">Guard Usage</span>
                <a href="<?= url('/admin/billing') ?>" class="metric-icon-box blue" title="Manage Subscription & Billing" style="text-decoration: none;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </a>
            </div>
            <div class="metric-value-row">
                <span class="metric-number" style="<?= $isLimitReached ? 'color: #dc2626;' : '' ?>"><?= e($activeGuards) ?> <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">/ <?= $guardLimit ?></span></span>
            </div>
            <div class="metric-subtext" style="display: flex; align-items: center; justify-content: space-between; font-size: 0.725rem;">
                <span style="color: <?= $isLimitReached ? '#dc2626; font-weight: 600;' : ($isApproaching ? '#d97706; font-weight: 600;' : '#2563eb;') ?>">
                    <?= $isLimitReached ? 'Limit Reached' : ($isApproaching ? 'Approaching Limit' : $subStatus) ?>
                </span>
                <span style="color: #64748b;" title="Valid Until">Till <?= e($validUntil) ?></span>
            </div>
        </div>

        <!-- Assigned Guards -->
        <div class="metric-card-compact">
            <div class="metric-header">
                <span class="metric-label">Assigned</span>
                <div class="metric-icon-box blue">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <div class="metric-value-row">
                <span class="metric-number"><?= e($m['assignedGuards']) ?></span>
            </div>
            <div class="metric-subtext">Rostered to posts</div>
        </div>

        <!-- On Duty -->
        <div class="metric-card-compact" style="border-color: #bbf7d0; background: #fcfdfc;">
            <div class="metric-header">
                <span class="metric-label" style="color: #166534;">On Duty</span>
                <div class="metric-icon-box green">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #16a34a;"></span>
                </div>
            </div>
            <div class="metric-value-row">
                <span class="metric-number" style="color: #15803d;"><?= e($m['onDutyGuards']) ?></span>
            </div>
            <div class="metric-subtext" style="color: #16a34a;">Active on shift</div>
        </div>

        <!-- Active Sites -->
        <div class="metric-card-compact">
            <div class="metric-header">
                <span class="metric-label">Active Sites</span>
                <div class="metric-icon-box">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <div class="metric-value-row">
                <span class="metric-number"><?= e($m['totalSites']) ?></span>
            </div>
            <div class="metric-subtext">Monitored posts</div>
        </div>

        <!-- Active Contracts -->
        <div class="metric-card-compact">
            <div class="metric-header">
                <span class="metric-label">Contracts</span>
                <div class="metric-icon-box">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
            <div class="metric-value-row">
                <span class="metric-number"><?= e($m['activeContracts']) ?></span>
            </div>
            <div class="metric-subtext">Active agreements</div>
        </div>
    </div>

    <!-- 3. Primary Operational Grid: Live Security Map (Left) + Attendance & Actions (Right) -->
    <div class="ops-grid-top">
        
        <!-- Live Security Map Panel (Visual Centerpiece) -->
        <div class="dashboard-map-panel">
            <div class="map-header-bar">
                <div>
                    <h3 class="ops-card-title">Live Security Overview</h3>
                    <p class="ops-card-subtitle">Real-time view of duty sites and guard locations</p>
                </div>

                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="map-legend-group">
                        <span><span class="map-legend-dot blue"></span> Duty Site</span>
                        <span><span class="map-legend-dot green"></span> Guard (On Duty)</span>
                        <span><span class="map-legend-dot gray"></span> Guard (Off Duty)</span>
                    </div>

                    <a href="<?= url('/admin/attendance') ?>" class="ops-card-link">
                        View All &rarr;
                    </a>
                </div>
            </div>

            <?php if (empty($dutySites) && empty($guardLocations)): ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; text-align: center; color: #64748b; background: #fafafa;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; color: #94a3b8;">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <p style="font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">Live location data unavailable</p>
                    <p style="font-size: 0.75rem; color: #94a3b8; margin: 0;">Configure GPS coordinates on client sites or wait for guard mobile check-ins.</p>
                </div>
            <?php elseif (empty($olaApiKey)): ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3.5rem 2rem; text-align: center; color: #64748b; background: #fafafa; border-radius: 8px;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #eff6ff; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; color: #2563eb;">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    </div>
                    <p style="font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">Ola Maps API Key Required</p>
                    <p style="font-size: 0.75rem; color: #64748b; margin: 0; max-width: 420px;">
                        Configure <code>OLA_MAPS_API_KEY</code> in your <code>.env</code> file to activate the interactive Krutrim / Ola Maps operational dashboard view.
                    </p>
                </div>
            <?php else: ?>
                <div id="dashboardMap"></div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Today's Attendance & Quick Actions -->
        <div class="ops-sidebar-stack">
            
            <!-- Card 1: Today's Attendance -->
            <div class="ops-card">
                <div class="ops-card-header">
                    <div>
                        <h3 class="ops-card-title">Today's Attendance</h3>
                        <p class="ops-card-subtitle">Shift check-in and duty audit logs</p>
                    </div>
                    <a href="<?= url('/admin/attendance') ?>" class="ops-card-link">
                        View All &rarr;
                    </a>
                </div>

                <div class="att-mini-grid">
                    <div class="att-mini-card">
                        <div class="att-mini-label">Today's Check-ins</div>
                        <div class="att-mini-number"><?= e($m['todayTotal']) ?></div>
                    </div>

                    <div class="att-mini-card active-card">
                        <div class="att-mini-label" style="color: #166534;">Currently On Duty</div>
                        <div class="att-mini-number"><?= e($m['onDutyGuards']) ?></div>
                    </div>

                    <div class="att-mini-card">
                        <div class="att-mini-label">Completed Shifts</div>
                        <div class="att-mini-number"><?= e($m['todayCompleted']) ?></div>
                    </div>

                    <div class="att-mini-card">
                        <div class="att-mini-label">Active Clients</div>
                        <div class="att-mini-number"><?= e($m['totalCustomers']) ?></div>
                    </div>
                </div>

                <div style="margin-top: auto; padding-top: 0.65rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; font-size: 0.725rem; color: #64748b;">
                    <span>Mobile Telemetry Sync</span>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #16a34a; font-weight: 600;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
                        Online
                    </span>
                </div>
            </div>

            <!-- Card 2: Quick Actions -->
            <div class="ops-card">
                <div class="ops-card-header">
                    <div>
                        <h3 class="ops-card-title">Quick Actions</h3>
                        <p class="ops-card-subtitle">Administrative shortcuts</p>
                    </div>
                </div>

                <div class="quick-actions-list">
                    <a href="<?= url('/admin/guards/setup') ?>" class="btn-quick-action-row">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>Setup Guard</span>
                    </a>
                    <a href="<?= url('/admin/clients/register') ?>" class="btn-quick-action-row">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>Register Client</span>
                    </a>
                    <a href="<?= url('/admin/clients-sites') ?>" class="btn-quick-action-row">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>Add Duty Site</span>
                    </a>
                    <a href="<?= url('/admin/contracts/create') ?>" class="btn-quick-action-row">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        <span>Create Contract</span>
                    </a>
                </div>
            </div>

        </div>

    </div>

    <!-- 4. Bottom Operational Grid: Recent Attendance (Left) + Recent Activity (Right) -->
    <div class="ops-grid-bottom">
        
        <!-- Left: Recent Attendance Table -->
        <div class="ops-card">
            <div class="ops-card-header">
                <div>
                    <h3 class="ops-card-title">Recent Attendance</h3>
                    <p class="ops-card-subtitle">Latest check-in and check-out records</p>
                </div>
                <a href="<?= url('/admin/attendance') ?>" class="ops-card-link">
                    View All &rarr;
                </a>
            </div>

            <?php if (empty($recentAttendance)): ?>
                <div style="padding: 3rem 1.5rem; text-align: center; color: #94a3b8;">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom: 0.5rem; opacity: 0.6;"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p style="font-size: 0.8125rem; font-weight: 500; color: #475569; margin-bottom: 0.2rem;">No attendance records recorded today.</p>
                    <p style="font-size: 0.725rem; color: #94a3b8; margin: 0;">Duty check-ins from personnel will display here in real time.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="compact-data-table">
                        <thead>
                            <tr>
                                <th>Guard</th>
                                <th>Site</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendance as $att): 
                                $guardName = $att['guard_name'] ?? 'Guard';
                                $initials = strtoupper(substr($guardName, 0, 1));
                                $badge = $att['employee_code'] ?? 'GRD';
                                $isOnDuty = ((int)$att['status'] === 0 && empty($att['check_out_at']));
                                
                                $duration = 'In Progress';
                                if (!empty($att['check_out_at']) && !empty($att['check_in_at'])) {
                                    $diffHours = round((strtotime($att['check_out_at']) - strtotime($att['check_in_at'])) / 3600, 1);
                                    $duration = $diffHours . ' hrs';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 26px; height: 26px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;">
                                                <?= e($initials) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #0f172a; font-size: 0.8125rem; line-height: 1.2;"><?= e($guardName) ?></div>
                                                <div style="font-size: 0.675rem; color: #64748b; font-family: monospace;"><?= e($badge) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 500; color: #334155;"><?= e($att['site_name'] ?? 'Unassigned') ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.75rem; color: #475569;"><?= format_time($att['check_in_at']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($att['check_out_at'])): ?>
                                            <span style="font-size: 0.75rem; color: #475569;"><?= format_time($att['check_out_at']) ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 500;">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.75rem; color: #64748b;"><?= e($duration) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($isOnDuty): ?>
                                            <span class="badge-clean-status on-duty">
                                                <span style="width: 5px; height: 5px; border-radius: 50%; background: #16a34a;"></span>
                                                On Duty
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-clean-status completed">
                                                Completed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Recent Activity Audit Trail -->
        <div class="ops-card">
            <div class="ops-card-header">
                <div>
                    <h3 class="ops-card-title">Recent Activity</h3>
                    <p class="ops-card-subtitle">Latest system activities</p>
                </div>
            </div>

            <?php if (empty($recentActivities)): ?>
                <div style="padding: 2.5rem 1rem; text-align: center; color: #94a3b8;">
                    <p style="font-size: 0.8125rem; font-weight: 500; color: #475569; margin-bottom: 0.2rem;">No recent activity.</p>
                    <p style="font-size: 0.725rem; color: #94a3b8; margin: 0;">Audit events will appear as personnel interact with the system.</p>
                </div>
            <?php else: ?>
                <div class="activity-feed-list">
                    <?php foreach ($recentActivities as $act): ?>
                        <div class="activity-feed-item">
                            <span class="activity-dot-indicator"></span>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?= e($act['user_name'] ?? 'System') ?></strong> 
                                    <?= e($act['title'] ?? $act['action_type'] ?? 'performed an action') ?>
                                </div>
                                <div class="activity-time">
                                    <?= format_time_ago($act['created_at'] ?? null) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Ola Maps Web SDK v2 Map Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('dashboardMap');
    if (!mapElement) return;

    const OLA_MAPS_API_KEY = <?= json_encode($olaApiKey ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    if (!OLA_MAPS_API_KEY) {
        return;
    }

    if (typeof OlaMaps === 'undefined') {
        console.warn('[Secure360] Ola Maps Web SDK failed to load from CDN.');
        mapElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:0.875rem;">Unable to load Ola Maps SDK. Please check your network connection.</div>';
        return;
    }

    const dutySites = <?= json_encode($dutySites, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];
    const guardLocations = <?= json_encode($guardLocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];

    // Fallback geographic center coordinates (Pune, Maharashtra fallback)
    const defaultLng = 73.8567;
    const defaultLat = 18.5204;
    const defaultZoom = 11;

    let olaMapsInstance;
    try {
        olaMapsInstance = new OlaMaps({
            apiKey: OLA_MAPS_API_KEY
        });
    } catch (e) {
        console.error('[Secure360] Error initializing OlaMaps client:', e);
        return;
    }

    let map;
    try {
        map = olaMapsInstance.init({
            style: "https://api.olamaps.io/tiles/vector/v1/styles/default-light-standard/style.json",
            container: 'dashboardMap',
            center: [defaultLng, defaultLat], // NOTE: [longitude, latitude] for Ola Maps / MapLibre
            zoom: defaultZoom
        });
    } catch (e) {
        console.error('[Secure360] Error rendering Ola Map:', e);
        return;
    }

    // Add navigation control (zoom controls)
    if (map && typeof map.addControl === 'function') {
        try {
            const NavControl = window.maplibregl?.NavigationControl || (typeof olaMapsInstance.NavigationControl === 'function' ? olaMapsInstance.NavigationControl : null);
            if (NavControl) {
                map.addControl(new NavControl({ showCompass: false }), 'top-right');
            }
        } catch (ctrlErr) {
            console.warn('[Secure360] Navigation control skipped:', ctrlErr);
        }
    }

    const boundsCoords = [];

    // Helper: Create custom DOM element for Site Pin (exact existing design)
    function createSitePinElement(siteName) {
        const el = document.createElement('div');
        el.className = 'custom-site-pin';
        el.title = 'Site: ' + (siteName || '');
        el.style.cursor = 'pointer';
        el.innerHTML = `
            <div style="width: 28px; height: 28px; border-radius: 50%; background: #2563eb; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
            </div>
        `;
        return el;
    }

    // Helper: Create custom DOM element for Guard On Duty Pin (exact existing design)
    function createGuardOnDutyPinElement(guardName) {
        const el = document.createElement('div');
        el.className = 'custom-guard-on-duty-pin';
        el.title = 'Guard: ' + (guardName || '');
        el.style.cursor = 'pointer';
        el.innerHTML = `
            <div style="width: 24px; height: 24px; border-radius: 50%; background: #16a34a; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #ffffff;"></span>
            </div>
        `;
        return el;
    }

    // Helper: Create custom DOM element for Guard Off Duty Pin (exact existing design)
    function createGuardOffDutyPinElement(guardName) {
        const el = document.createElement('div');
        el.className = 'custom-guard-off-duty-pin';
        el.title = 'Guard: ' + (guardName || '');
        el.style.cursor = 'pointer';
        el.innerHTML = `
            <div style="width: 22px; height: 22px; border-radius: 50%; background: #94a3b8; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ffffff;"></span>
            </div>
        `;
        return el;
    }

    function createPopupInstance(htmlContent, offset) {
        const PopupClass = window.maplibregl?.Popup;
        if (typeof olaMapsInstance.addPopup === 'function') {
            return olaMapsInstance.addPopup({ offset: offset || [0, -14], anchor: 'bottom', closeButton: true })
                .setHTML(htmlContent);
        } else if (PopupClass) {
            return new PopupClass({ offset: offset || [0, -14], anchor: 'bottom', closeButton: true })
                .setHTML(htmlContent);
        }
        return null;
    }

    function addCustomMarker(pinElement, lng, lat, popup) {
        const MarkerClass = window.maplibregl?.Marker;
        let marker = null;
        if (typeof olaMapsInstance.addMarker === 'function') {
            marker = olaMapsInstance.addMarker({
                element: pinElement,
                anchor: 'center'
            }).setLngLat([lng, lat]);
        } else if (MarkerClass) {
            marker = new MarkerClass({
                element: pinElement,
                anchor: 'center'
            }).setLngLat([lng, lat]);
        }
        if (marker) {
            if (popup) {
                marker.setPopup(popup);
            }
            marker.addTo(map);
        }
        return marker;
    }

    // Plot Duty Sites
    dutySites.forEach(function(site) {
        if (site.latitude && site.longitude) {
            const lat = parseFloat(site.latitude);
            const lng = parseFloat(site.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                // COORDINATE INVARIANT: Ola Maps expects [longitude, latitude]
                boundsCoords.push([lng, lat]);

                const popupContent = `
                    <div style="font-family: inherit; font-size: 0.8125rem; min-width: 170px; line-height: 1.4;">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">${site.site_name}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 4px;">Client: <strong>${site.customer_name || 'N/A'}</strong></div>
                        <div style="font-size: 0.7rem; color: #2563eb; font-weight: 600; margin-bottom: 4px;">${site.guard_count || 0} Guard(s) Rostered</div>
                        ${site.address ? `<div style="font-size: 0.7rem; color: #64748b; border-top: 1px dashed #e2e8f0; padding-top: 4px;">${site.address}</div>` : ''}
                    </div>
                `;

                const pinEl = createSitePinElement(site.site_name);
                const popup = createPopupInstance(popupContent, [0, -14]);
                addCustomMarker(pinEl, lng, lat, popup);
            }
        }
    });

    // Plot Guard Locations
    guardLocations.forEach(function(guard) {
        if (guard.latitude && guard.longitude) {
            const lat = parseFloat(guard.latitude);
            const lng = parseFloat(guard.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                // COORDINATE INVARIANT: Ola Maps expects [longitude, latitude]
                boundsCoords.push([lng, lat]);

                const isOnDuty = (guard.status === 0);
                const popupContent = `
                    <div style="font-family: inherit; font-size: 0.8125rem; min-width: 170px; line-height: 1.4;">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">${guard.guard_name}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 2px;">Badge: <code>${guard.guard_badge || 'GRD'}</code></div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 4px;">Site: <strong>${guard.site_name || 'Unassigned'}</strong></div>
                        <div style="font-size: 0.7rem; color: ${isOnDuty ? '#16a34a' : '#64748b'}; font-weight: 600;">Status: ${guard.status_label || (isOnDuty ? 'On Duty' : 'Off Duty')}</div>
                        ${guard.last_update ? `<div style="font-size: 0.675rem; color: #94a3b8; margin-top: 4px; border-top: 1px dashed #e2e8f0; padding-top: 4px;">Updated: ${guard.last_update}</div>` : ''}
                    </div>
                `;

                const pinEl = isOnDuty ? createGuardOnDutyPinElement(guard.guard_name) : createGuardOffDutyPinElement(guard.guard_name);
                const popup = createPopupInstance(popupContent, [0, -12]);
                addCustomMarker(pinEl, lng, lat, popup);
            }
        }
    });

    // Auto-fit map viewport to active coordinates
    if (boundsCoords.length > 0 && map && typeof map.fitBounds === 'function') {
        if (boundsCoords.length === 1) {
            map.setCenter(boundsCoords[0]);
            map.setZoom(14);
        } else {
            const BoundsClass = window.maplibregl?.LngLatBounds;
            if (BoundsClass) {
                const bounds = boundsCoords.reduce((b, coord) => b.extend(coord), new BoundsClass(boundsCoords[0], boundsCoords[0]));
                map.fitBounds(bounds, { padding: 35, maxZoom: 15 });
            }
        }
    }

    // Responsive Map Resize
    window.addEventListener('resize', function() {
        if (map && typeof map.resize === 'function') {
            map.resize();
        }
    });
    setTimeout(function() {
        if (map && typeof map.resize === 'function') {
            map.resize();
        }
    }, 300);
});
</script>
