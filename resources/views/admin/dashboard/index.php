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

// Relative time helper
function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Recently';
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $timestamp);
}
?>

<!-- Leaflet CSS for Operational Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

<div class="dashboard-container">

    <!-- 1. Compact Intro Header Section -->
    <div class="dashboard-intro-header">
        <div>
            <h1 class="dashboard-greeting"><?= e($greeting) ?>, <?= e($firstName) ?></h1>
            <p class="dashboard-subtitle">Here's an overview of your security operations for <strong><?= e($orgName) ?></strong>.</p>
        </div>

        <div class="dashboard-date-selector">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span><?= date('l, M j, Y') ?></span>
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
        </div>
    </div>

    <!-- 2. Compact Operational KPI Strip -->
    <div class="metrics-grid-5">
        <!-- Total Guards -->
        <div class="metric-card-compact">
            <div class="metric-header">
                <span class="metric-label">Total Guards</span>
                <div class="metric-icon-box blue">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            </div>
            <div class="metric-value-row">
                <span class="metric-number"><?= e($m['totalGuards']) ?></span>
            </div>
            <div class="metric-subtext">Operational</div>
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
                                        <span style="font-size: 0.75rem; color: #475569;"><?= date('g:i A', strtotime($att['check_in_at'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($att['check_out_at'])): ?>
                                            <span style="font-size: 0.75rem; color: #475569;"><?= date('g:i A', strtotime($att['check_out_at'])) ?></span>
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
                                    <?= timeAgo($act['created_at'] ?? date('Y-m-d H:i:s')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Leaflet JS & Map Initialization -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('dashboardMap');
    if (!mapElement) return;

    const dutySites = <?= json_encode($dutySites, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];
    const guardLocations = <?= json_encode($guardLocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];

    // Fallback center coordinates
    let defaultLat = 18.5204, defaultLng = 73.8567, defaultZoom = 11;

    const map = L.map('dashboardMap', {
        zoomControl: true,
        attributionControl: false
    }).setView([defaultLat, defaultLng], defaultZoom);

    // Clean OpenStreetMap Layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    const bounds = [];

    // Custom Blue Pin for Duty Sites
    const siteIcon = L.divIcon({
        className: 'custom-site-pin',
        html: `<div style="width: 28px; height: 28px; border-radius: 50%; background: #2563eb; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                 <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
               </div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14]
    });

    // Custom Green Pin for Guard On Duty Locations
    const guardOnDutyIcon = L.divIcon({
        className: 'custom-guard-on-duty-pin',
        html: `<div style="width: 24px; height: 24px; border-radius: 50%; background: #16a34a; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                 <span style="width: 7px; height: 7px; border-radius: 50%; background: #ffffff;"></span>
               </div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12]
    });

    // Custom Gray Pin for Guard Off Duty Locations
    const guardOffDutyIcon = L.divIcon({
        className: 'custom-guard-off-duty-pin',
        html: `<div style="width: 22px; height: 22px; border-radius: 50%; background: #94a3b8; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                 <span style="width: 6px; height: 6px; border-radius: 50%; background: #ffffff;"></span>
               </div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        popupAnchor: [0, -11]
    });

    // Plot Duty Sites
    dutySites.forEach(function(site) {
        if (site.latitude && site.longitude) {
            const lat = parseFloat(site.latitude);
            const lng = parseFloat(site.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                bounds.push([lat, lng]);
                const popupContent = `
                    <div style="font-family: inherit; font-size: 0.8125rem; min-width: 170px; line-height: 1.4;">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">${site.site_name}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 4px;">Client: <strong>${site.customer_name || 'N/A'}</strong></div>
                        <div style="font-size: 0.7rem; color: #2563eb; font-weight: 600; margin-bottom: 4px;">${site.guard_count || 0} Guard(s) Rostered</div>
                        ${site.address ? `<div style="font-size: 0.7rem; color: #64748b; border-top: 1px dashed #e2e8f0; padding-top: 4px;">${site.address}</div>` : ''}
                    </div>
                `;
                L.marker([lat, lng], { icon: siteIcon }).addTo(map).bindPopup(popupContent);
            }
        }
    });

    // Plot Guard Locations
    guardLocations.forEach(function(guard) {
        if (guard.latitude && guard.longitude) {
            const lat = parseFloat(guard.latitude);
            const lng = parseFloat(guard.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                bounds.push([lat, lng]);
                const isOnDuty = (guard.status === 0);
                const currentIcon = isOnDuty ? guardOnDutyIcon : guardOffDutyIcon;

                const popupContent = `
                    <div style="font-family: inherit; font-size: 0.8125rem; min-width: 170px; line-height: 1.4;">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">${guard.guard_name}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 2px;">Badge: <code>${guard.guard_badge || 'GRD'}</code></div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 4px;">Site: <strong>${guard.site_name || 'Unassigned'}</strong></div>
                        <div style="font-size: 0.7rem; color: ${isOnDuty ? '#16a34a' : '#64748b'}; font-weight: 600;">Status: ${guard.status_label || (isOnDuty ? 'On Duty' : 'Off Duty')}</div>
                        ${guard.last_update ? `<div style="font-size: 0.675rem; color: #94a3b8; margin-top: 4px; border-top: 1px dashed #e2e8f0; padding-top: 4px;">Updated: ${guard.last_update}</div>` : ''}
                    </div>
                `;
                L.marker([lat, lng], { icon: currentIcon }).addTo(map).bindPopup(popupContent);
            }
        }
    });

    // Auto-fit map viewport to active coordinates
    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 15 });
    }
});
</script>
