<?php
/**
 * Secure360 Admin - Guard Attendance & Duty Telemetry View
 * 
 * Simplified strictly into TWO main sections with ONE standardized Reports-matching filter toolbar:
 * 1. Standardized Filter Toolbar (Date preset, status, search guards/sites, apply filter)
 * 2. ATTENDANCE MAP (Top Section) - Interactive Ola Maps Web SDK v2 Map for Duty Sites & Guard Live GPS
 * 3. ATTENDANCE RECORDS TABLE (Bottom Section) - Full attendance records with 10 records/page Pagination
 */

$records = $records ?? [];
$dutySites = $dutySites ?? [];
$guardLocations = $guardLocations ?? [];
$filterOptions = $filterOptions ?? ['clients' => [], 'sites' => [], 'guards' => []];
$preset = $preset ?? 'all';
$fromDate = $fromDate ?? '';
$toDate = $toDate ?? '';
$customerId = $customerId ?? null;
$siteId = $siteId ?? null;
$guardId = $guardId ?? null;
$status = $status ?? 'all';
$search = $search ?? '';

// Calculate initial helper counts
$totalRecords = count($records);

// Ola Maps Platform configuration
$olaApiKey = config('app.maps.ola_api_key', '');
?>

<!-- MapLibre & Ola Maps Web SDK v2 for Interactive Map -->
<link href="https://unpkg.com/maplibre-gl@latest/dist/maplibre-gl.css" rel="stylesheet" />
<script src="https://www.unpkg.com/olamaps-web-sdk@latest/dist/olamaps-web-sdk.umd.js"></script>

<style>
/* ==========================================================================
   Secure360 Attendance Page Styles
   ========================================================================== */
.attendance-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}

.page-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 0;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin: 0;
}

.page-subtitle {
    font-size: 0.875rem;
    color: #64748b;
    margin-top: 0.25rem;
}

.section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Map Header & Controls */
.map-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    background: #ffffff;
}

.map-title-group {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.map-title-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
}

.map-title-text {
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
}

.map-controls-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.map-filter-pills {
    display: inline-flex;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 8px;
    gap: 0.25rem;
}

.map-filter-btn {
    border: none;
    background: transparent;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.map-filter-btn.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.map-btn-action {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease;
}

.map-btn-action:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.map-wrapper {
    position: relative;
    width: 100%;
    height: 440px;
    background: #f8fafc;
}

#attendanceMap {
    width: 100%;
    height: 100%;
    z-index: 1;
}

/* Custom Marker Pins */
.custom-pin {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
    border: 2px solid #ffffff;
    position: relative;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.custom-pin:hover {
    transform: scale(1.12);
}

.pin-site { background: #1d4ed8; }
.pin-guard { background: #10b981; }
.pin-guard-offduty { background: #64748b; }
.pin-guard-cancelled { background: #ef4444; }

.radar-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.4);
    animation: radarRipple 2s infinite ease-out;
    pointer-events: none;
    z-index: -1;
}

@keyframes radarRipple {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(2.2); opacity: 0; }
}

/* MapLibre / Ola Maps Popup Styling */
.maplibregl-popup-content {
    padding: 0;
    border-radius: 10px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 0;
    line-height: 1.4;
    font-family: inherit;
    font-size: 0.8125rem;
    min-width: 260px;
    max-width: 320px;
}

.maplibregl-popup-close-button {
    padding: 4px 8px;
    color: #ffffff;
    font-size: 16px;
    z-index: 10;
}

.maplibregl-popup-close-button:hover {
    color: #f1f5f9;
    background: transparent;
}

.popup-card {
    display: flex;
    flex-direction: column;
}

.popup-header {
    padding: 0.75rem 1rem;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.popup-header.site-header { background: #1d4ed8; }
.popup-header.guard-header { background: #059669; }

.popup-title {
    font-size: 0.9375rem;
    font-weight: 700;
    margin: 0;
    color: #ffffff;
}

.popup-tag {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

.popup-body {
    padding: 0.875rem 1rem;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.popup-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.popup-label {
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 500;
    flex-shrink: 0;
}

.popup-val {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
    font-size: 0.75rem;
}

.popup-guards-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.25rem;
}

.popup-guard-chip {
    background: #eff6ff;
    color: #1e40af;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 600;
    border: 1px solid #dbeafe;
}

.map-empty-overlay {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(6px);
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 1000;
    pointer-events: none;
}

/* Table Header & Toolbar */
.table-toolbar-title-group {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.table-toolbar-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.record-count-badge {
    background: #eff6ff;
    color: #2563eb;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    border: 1px solid #dbeafe;
}

/* Data Table */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.8125rem;
}

.attendance-table thead th {
    background: #f8fafc;
    padding: 0.875rem 1rem;
    font-weight: 600;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
    text-transform: uppercase;
    font-size: 0.6875rem;
    letter-spacing: 0.05em;
}

.attendance-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.15s ease;
}

.attendance-table tbody tr:hover {
    background-color: #f8fafc;
}

.attendance-table td {
    padding: 0.875rem 1rem;
    vertical-align: middle;
}

.guard-cell {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.guard-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #dbeafe;
}

.guard-info-name {
    font-weight: 600;
    color: #0f172a;
    line-height: 1.25;
}

.guard-info-badge {
    font-size: 0.7rem;
    color: #64748b;
    font-family: monospace;
}

.site-cell-title {
    font-weight: 600;
    color: #0f172a;
}

.site-cell-sub {
    font-size: 0.725rem;
    color: #64748b;
}

.contract-code-tag {
    font-family: monospace;
    font-size: 0.75rem;
    color: #475569;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.shift-tag {
    display: inline-flex;
    flex-direction: column;
    font-size: 0.75rem;
    color: #334155;
    font-weight: 500;
}

.shift-hours {
    font-size: 0.6875rem;
    color: #64748b;
    font-family: monospace;
}

.time-stamp {
    font-weight: 500;
    color: #0f172a;
    white-space: nowrap;
}

.time-stamp-active {
    color: #059669;
    font-weight: 600;
    font-style: italic;
}

.duration-badge {
    font-family: monospace;
    font-weight: 600;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
}

.duration-active {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #047857;
}

/* Status Badges */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-on-duty {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.status-on-duty .status-dot { background: #10b981; }

.status-completed {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}
.status-completed .status-dot { background: #3b82f6; }

.status-cancelled {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.status-cancelled .status-dot { background: #ef4444; }

/* Table Action Button */
.btn-action-view {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.btn-action-view:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.1);
}

/* Pagination Footer */
.table-footer-pagination {
    padding: 0.875rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    background: #ffffff;
}

.pagination-info {
    font-size: 0.8125rem;
    color: #64748b;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.page-btn {
    min-width: 32px;
    height: 32px;
    padding: 0 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}

.page-btn:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.page-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}

.page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.empty-table-state {
    padding: 3.5rem 1.5rem;
    text-align: center;
    color: #64748b;
}

.empty-state-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    color: #94a3b8;
}
</style>

<div class="attendance-container">
    <!-- Clean Minimalist Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-title">Attendance &amp; Field Telemetry</h1>
        </div>
    </div>

    <!-- ====================================================================
         SECTION 1: ATTENDANCE MAP (TOP SECTION)
         ==================================================================== -->
    <div class="section-card">
        <div class="map-header">
            <div class="map-title-group">
                <div class="map-title-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <div class="map-title-text">Duty Sites &amp; Guard GPS Map</div>
                </div>
            </div>

            <div class="map-controls-group">
                <!-- Layer Filter Toggle -->
                <div class="map-filter-pills">
                    <button type="button" class="map-filter-btn active" id="btnMapAll" onclick="setMapLayerFilter('all')">
                        All Pins
                    </button>
                    <button type="button" class="map-filter-btn" id="btnMapSites" onclick="setMapLayerFilter('sites')">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #1d4ed8; display: inline-block;"></span>
                        Duty Sites (<span id="countSitesBadge">0</span>)
                    </button>
                    <button type="button" class="map-filter-btn" id="btnMapGuards" onclick="setMapLayerFilter('guards')">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                        Guards GPS (<span id="countGuardsBadge">0</span>)
                    </button>
                </div>

                <button type="button" class="map-btn-action" onclick="fitMapBounds()" title="Fit Map to All Markers">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    Fit View
                </button>
            </div>
        </div>

        <div class="map-wrapper">
            <div id="attendanceMap"></div>
            <div id="mapLocationNotice" class="map-empty-overlay" style="display: none;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span id="mapNoticeText">Showing duty sites with registered GPS coordinates.</span>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         STANDARDIZED FILTER & SEARCH TOOLBAR (BELOW THE MAP)
         ==================================================================== -->
    <div class="reports-filter-card" style="margin-bottom: 0;">
        <form method="GET" action="<?= url('/admin/attendance') ?>" class="reports-toolbar-form" id="attendanceFilterForm">
            <!-- Date Preset Dropdown -->
            <div class="filter-control-group">
                <label for="datePresetSelect" class="filter-control-label">Date:</label>
                <select name="preset" id="datePresetSelect" class="filter-select" onchange="handlePresetChange(this.value)">
                    <option value="all" <?= ($preset === 'all') ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= ($preset === 'today') ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= ($preset === 'yesterday') ? 'selected' : '' ?>>Yesterday</option>
                    <option value="this_week" <?= ($preset === 'this_week') ? 'selected' : '' ?>>This Week</option>
                    <option value="this_month" <?= ($preset === 'this_month') ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= ($preset === 'last_month') ? 'selected' : '' ?>>Last Month</option>
                    <option value="custom" <?= ($preset === 'custom') ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>

            <!-- From Date Selector (Shown for Custom Range) -->
            <div class="filter-control-group" id="fromDateGroup" style="<?= ($preset === 'custom') ? 'display: inline-flex;' : 'display: none;' ?>">
                <label for="fromDatePicker" class="filter-control-label" id="fromDateLabel">From:</label>
                <input type="date" name="from_date" id="fromDatePicker" class="filter-date-input" value="<?= e($fromDate ?? '') ?>">
            </div>

            <!-- To Date Selector (Shown for Custom Range) -->
            <div class="filter-control-group" id="toDateGroup" style="<?= ($preset === 'custom') ? 'display: inline-flex;' : 'display: none;' ?>">
                <label for="toDatePicker" class="filter-control-label">To:</label>
                <input type="date" name="to_date" id="toDatePicker" class="filter-date-input" value="<?= e($toDate ?? '') ?>">
            </div>

            <!-- Client Dropdown -->
            <div class="filter-control-group">
                <label for="filterClientSelect" class="filter-control-label">Client:</label>
                <select name="customer_id" id="filterClientSelect" class="filter-select" onchange="onClientChange(this.value)">
                    <option value="">All Clients</option>
                    <?php foreach ($filterOptions['clients'] as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ((int)$customerId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Site Dropdown -->
            <div class="filter-control-group">
                <label for="filterSiteSelect" class="filter-control-label">Site:</label>
                <select name="site_id" id="filterSiteSelect" class="filter-select" onchange="onSiteChange(this.value)">
                    <option value="">All Sites</option>
                    <?php foreach ($filterOptions['sites'] as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ((int)$siteId === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['site_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Guard Dropdown -->
            <div class="filter-control-group">
                <label for="filterGuardSelect" class="filter-control-label">Guard:</label>
                <select name="guard_id" id="filterGuardSelect" class="filter-select">
                    <option value="">All Guards</option>
                    <?php foreach ($filterOptions['guards'] as $g): ?>
                        <option value="<?= $g['guard_id'] ?>" <?= ((int)$guardId === (int)$g['guard_id']) ? 'selected' : '' ?>><?= e($g['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter Dropdown -->
            <div class="filter-control-group">
                <label for="tableStatusFilter" class="filter-control-label">Status:</label>
                <select name="status" id="tableStatusFilter" class="filter-select">
                    <option value="all" <?= ($status === 'all') ? 'selected' : '' ?>>All Statuses</option>
                    <option value="0" <?= ($status === '0') ? 'selected' : '' ?>>On Duty</option>
                    <option value="1" <?= ($status === '1') ? 'selected' : '' ?>>Completed</option>
                    <option value="2" <?= ($status === '2') ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Standardized Search Bar -->
            <div class="toolbar-search" style="flex: 1; min-width: 200px;">
                <div class="input-icon-wrapper" style="width: 100%;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" id="tableSearchInput" class="form-control" placeholder="Search guards, sites, clients..." value="<?= e($search ?? '') ?>" oninput="filterRecords()">
                </div>
            </div>

            <!-- Action Button: Apply Filter -->
            <button type="submit" class="btn-filter-submit">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                Apply Filter
            </button>

            <!-- Reset Filter Button -->
            <?php
                $isFiltered = ($preset !== 'all') || (!empty($customerId)) || (!empty($siteId)) || (!empty($guardId)) || ($status !== 'all') || (!empty($search));
            ?>
            <button type="button" class="btn-filter-reset" id="btnResetFilter" style="<?= $isFiltered ? 'display: inline-flex;' : 'display: none;' ?>" onclick="resetTableFilters()">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                Reset
            </button>
        </form>
    </div>

    <!-- ====================================================================
         SECTION 2: ATTENDANCE RECORDS TABLE (BELOW THE TOOLBAR)
         ==================================================================== -->
    <div class="section-card">
        <!-- Clean Table Header (Old Filter Toolbar Completely Removed) -->
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #ffffff;">
            <div class="table-toolbar-title-group">
                <span class="table-toolbar-title">Attendance Records</span>
                <span class="record-count-badge" id="totalRecordsBadge"><?= $totalRecords ?> Records</span>
            </div>
        </div>

        <!-- Table Responsive Container -->
        <div class="table-responsive">
            <table class="attendance-table" id="attendanceTable">
                <thead>
                    <tr>
                        <th>Guard</th>
                        <th>Site</th>
                        <th>Contract</th>
                        <th>Shift</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <!-- Populated dynamically via JavaScript with max 10 records per page -->
                </tbody>
            </table>

            <!-- Empty State Container -->
            <div id="tableEmptyState" class="empty-table-state" style="display: none;">
                <div class="empty-state-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div style="font-weight: 600; color: #334155; font-size: 0.9375rem; margin-bottom: 0.25rem;">No attendance records found</div>
                <div style="font-size: 0.8125rem; color: #94a3b8;">Try adjusting your search query, date, or status filter.</div>
            </div>
        </div>

        <!-- Pagination Footer (Strictly Max 10 Records Per Page) -->
        <div class="table-footer-pagination" id="tablePaginationFooter">
            <div class="pagination-info" id="paginationInfoText">
                Showing 0 to 0 of 0 records
            </div>
            <div class="pagination-controls" id="paginationButtons">
                <!-- Previous, Page numbers, Next rendered dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Raw Backend Data Injected for Frontend Processing -->
<script>
const RAW_DUTY_SITES = <?= json_encode($dutySites, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const RAW_GUARD_LOCATIONS = <?= json_encode($guardLocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const RAW_ATTENDANCE_RECORDS = <?= json_encode($records, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const ATTENDANCE_BASE_URL = '<?= url('/admin/attendance') ?>';

// Global Map & Table State
const OLA_MAPS_API_KEY = <?= json_encode($olaApiKey ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let mapInstance = null;
let olaMapsClient = null;
let siteMarkers = [];
let guardMarkers = [];
let currentLayerFilter = 'all';

// Pagination & Table Filter State
const PAGE_SIZE = 10;
let currentPage = 1;
let filteredRecords = [];

const todayStr = '<?= date('Y-m-d') ?>';
const yesterdayStr = '<?= date('Y-m-d', strtotime('-1 day')) ?>';

// ==========================================================================
// 1. Interactive Map Logic (Ola Maps Web SDK v2)
// ==========================================================================
function initAttendanceMap() {
    const mapElement = document.getElementById('attendanceMap');
    if (!mapElement) return;

    // Graceful fallback if API key is not yet configured
    if (!OLA_MAPS_API_KEY) {
        const noticeElem = document.getElementById('mapLocationNotice');
        if (noticeElem) {
            noticeElem.style.display = 'flex';
            document.getElementById('mapNoticeText').textContent = 'Ola Maps API Key Required. Configure OLA_MAPS_API_KEY in your .env file to activate interactive telemetry map.';
        }
        // Count valid coordinates from raw data to populate badge counters
        let vSites = 0, vGuards = 0;
        RAW_DUTY_SITES.forEach(s => { if (s.latitude !== null && s.longitude !== null && !isNaN(s.latitude) && !isNaN(s.longitude)) vSites++; });
        RAW_GUARD_LOCATIONS.forEach(g => { if (g.latitude !== null && g.longitude !== null && !isNaN(g.latitude) && !isNaN(g.longitude)) vGuards++; });
        document.getElementById('countSitesBadge').textContent = vSites;
        document.getElementById('countGuardsBadge').textContent = vGuards;
        return;
    }

    if (typeof OlaMaps === 'undefined') {
        console.warn('[Secure360] Ola Maps Web SDK failed to load from CDN.');
        const noticeElem = document.getElementById('mapLocationNotice');
        if (noticeElem) {
            noticeElem.style.display = 'flex';
            document.getElementById('mapNoticeText').textContent = 'Unable to load Ola Maps SDK. Please check your network connection.';
        }
        return;
    }

    // Default center fallback (Pune, Maharashtra)
    const defaultCenter = [73.8567, 18.5204]; // NOTE: [longitude, latitude] for Ola Maps / MapLibre

    // Calculate dynamic initial center from available duty sites and guard locations
    const initialCoords = [];
    RAW_DUTY_SITES.forEach(s => {
        if (s.latitude !== null && s.longitude !== null) {
            const lat = parseFloat(s.latitude);
            const lng = parseFloat(s.longitude);
            if (!isNaN(lat) && !isNaN(lng)) initialCoords.push([lng, lat]);
        }
    });
    RAW_GUARD_LOCATIONS.forEach(g => {
        if (g.latitude !== null && g.longitude !== null) {
            const lat = parseFloat(g.latitude);
            const lng = parseFloat(g.longitude);
            if (!isNaN(lat) && !isNaN(lng)) initialCoords.push([lng, lat]);
        }
    });

    let initialCenter = defaultCenter;
    let initialZoom = 12;
    if (initialCoords.length === 1) {
        initialCenter = initialCoords[0];
        initialZoom = 14;
    } else if (initialCoords.length > 1) {
        let minLng = initialCoords[0][0], maxLng = initialCoords[0][0];
        let minLat = initialCoords[0][1], maxLat = initialCoords[0][1];
        for (let i = 1; i < initialCoords.length; i++) {
            const lng = initialCoords[i][0];
            const lat = initialCoords[i][1];
            if (lng < minLng) minLng = lng;
            if (lng > maxLng) maxLng = lng;
            if (lat < minLat) minLat = lat;
            if (lat > maxLat) maxLat = lat;
        }
        initialCenter = [(minLng + maxLng) / 2, (minLat + maxLat) / 2];
    }

    try {
        olaMapsClient = new OlaMaps({
            apiKey: OLA_MAPS_API_KEY
        });
    } catch (e) {
        console.error('[Secure360] Error initializing OlaMaps client:', e);
        return;
    }

    try {
        mapInstance = olaMapsClient.init({
            style: "https://api.olamaps.io/tiles/vector/v1/styles/default-light-standard/style.json",
            container: 'attendanceMap',
            center: initialCenter, // Dynamically computed from real Secure360 data
            zoom: initialZoom
        });
    } catch (e) {
        console.error('[Secure360] Error rendering Ola Map:', e);
        return;
    }

    // Add navigation control
    if (mapInstance && typeof mapInstance.addControl === 'function') {
        try {
            const NavControl = window.OlaMaps?.NavigationControl || window.maplibregl?.NavigationControl;
            if (NavControl) {
                mapInstance.addControl(new NavControl({ showCompass: false }), 'top-right');
            }
        } catch (ctrlErr) {
            console.warn('[Secure360] Nav control skipped:', ctrlErr);
        }
    }

    renderMapMarkers();
}

function renderMapMarkers(sitesToRender, guardsToRender) {
    if (!mapInstance) return;

    const sites = (sitesToRender !== undefined) ? sitesToRender : RAW_DUTY_SITES;
    const guards = (guardsToRender !== undefined) ? guardsToRender : RAW_GUARD_LOCATIONS;

    // Clear existing marker instances cleanly
    siteMarkers.forEach(item => {
        if (item.marker && typeof item.marker.remove === 'function') {
            item.marker.remove();
        }
    });
    guardMarkers.forEach(item => {
        if (item.marker && typeof item.marker.remove === 'function') {
            item.marker.remove();
        }
    });
    siteMarkers = [];
    guardMarkers = [];

    let validSitesCount = 0;
    let validGuardsCount = 0;

    const PopupClass = window.OlaMaps?.Popup || window.maplibregl?.Popup;
    const MarkerClass = window.OlaMaps?.Marker || window.maplibregl?.Marker;

    // 1. Render Duty Site Markers
    sites.forEach(site => {
        if (site.latitude !== null && site.longitude !== null && !isNaN(site.latitude) && !isNaN(site.longitude)) {
            validSitesCount++;
            const lat = parseFloat(site.latitude);
            const lng = parseFloat(site.longitude);
            // COORDINATE INVARIANT: Ola Maps expects [longitude, latitude]
            const lngLat = [lng, lat];

            const pinWrapper = document.createElement('div');
            pinWrapper.className = 'custom-pin-wrapper';
            pinWrapper.innerHTML = `
                <div class="custom-pin pin-site" title="Site: ${escapeHtml(site.name)}">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            `;

            let guardsHtml = '<span style="color:#94a3b8; font-style:italic;">No guards assigned</span>';
            if (site.guards && site.guards.length > 0) {
                guardsHtml = '<div class="popup-guards-list">' + 
                    site.guards.map(g => `<span class="popup-guard-chip">${escapeHtml(g.name)} (${escapeHtml(g.badge || 'No ID')})</span>`).join('') + 
                    '</div>';
            }

            const shiftDisplay = site.shift_name ? 
                `${escapeHtml(site.shift_name)} ${site.shift_start ? `(${formatTime(site.shift_start)} - ${site.shift_end ? formatTime(site.shift_end) : ''})` : ''}` : 
                'Standard Shift';

            const popupContent = `
                <div class="popup-card">
                    <div class="popup-header site-header">
                        <span class="popup-title">${escapeHtml(site.name)}</span>
                        <span class="popup-tag">Duty Site</span>
                    </div>
                    <div class="popup-body">
                        <div class="popup-row">
                            <span class="popup-label">Client:</span>
                            <span class="popup-val">${escapeHtml(site.customer_name || 'Standard Client')}</span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">Contract:</span>
                            <span class="popup-val" style="font-family: monospace;">${escapeHtml(site.contract_code || '—')}</span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">Shift:</span>
                            <span class="popup-val">${shiftDisplay}</span>
                        </div>
                        <div class="popup-row" style="flex-direction: column; align-items: flex-start;">
                            <span class="popup-label">Assigned Guard(s):</span>
                            ${guardsHtml}
                        </div>
                        ${site.address ? `<div class="popup-row" style="border-top: 1px dashed #e2e8f0; padding-top: 0.35rem; margin-top: 0.25rem;"><span class="popup-label">Address:</span><span class="popup-val" style="font-size:0.6875rem; color:#64748b;">${escapeHtml(site.address)}</span></div>` : ''}
                    </div>
                </div>
            `;

            let popup = null;
            if (olaMapsClient && typeof olaMapsClient.addPopup === 'function') {
                popup = olaMapsClient.addPopup({ offset: [0, -20], anchor: 'bottom', closeButton: true }).setHTML(popupContent);
            } else if (PopupClass) {
                popup = new PopupClass({ offset: [0, -20], anchor: 'bottom', closeButton: true }).setHTML(popupContent);
            }

            let marker = null;
            if (olaMapsClient && typeof olaMapsClient.addMarker === 'function') {
                marker = olaMapsClient.addMarker({ element: pinWrapper, anchor: 'center' }).setLngLat(lngLat);
            } else if (MarkerClass) {
                marker = new MarkerClass({ element: pinWrapper, anchor: 'center' }).setLngLat(lngLat);
            }

            if (marker) {
                if (popup) marker.setPopup(popup);
                marker.addTo(mapInstance);
                siteMarkers.push({ marker: marker, lngLat: lngLat, type: 'site', visible: true });
            }
        }
    });

    // 2. Render Guard GPS Markers
    guards.forEach(guard => {
        if (guard.latitude !== null && guard.longitude !== null && !isNaN(guard.latitude) && !isNaN(guard.longitude)) {
            validGuardsCount++;
            const lat = parseFloat(guard.latitude);
            const lng = parseFloat(guard.longitude);
            // COORDINATE INVARIANT: Ola Maps expects [longitude, latitude]
            const lngLat = [lng, lat];

            const statusInt = parseInt(guard.status, 10);
            const isOnDuty = (statusInt === 0);
            let pinClass = 'pin-guard';
            if (statusInt === 1) {
                pinClass = 'pin-guard pin-guard-offduty';
            } else if (statusInt === 2) {
                pinClass = 'pin-guard pin-guard-cancelled';
            }

            const pinWrapper = document.createElement('div');
            pinWrapper.className = 'custom-pin-wrapper';
            pinWrapper.innerHTML = `
                <div class="custom-pin ${pinClass}" title="Guard: ${escapeHtml(guard.guard_name)}">
                    ${isOnDuty ? '<div class="radar-ring"></div>' : ''}
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
            `;

            const statusClass = (statusInt === 0) ? 'status-on-duty' : ((statusInt === 1) ? 'status-completed' : 'status-cancelled');
            const statusLabel = guard.status_label || (statusInt === 0 ? 'On Duty' : (statusInt === 1 ? 'Completed' : 'Cancelled'));

            let sourceLabel = 'Live Telemetry';
            if (guard.source === 'attendance_checkout') {
                sourceLabel = 'Checkout GPS';
            } else if (guard.source === 'attendance_checkin') {
                sourceLabel = 'Check-in GPS';
            } else if (guard.source === 'live_telemetry') {
                sourceLabel = 'Live Telemetry';
            }

            const dateLabel = guard.location_date ? formatDate(guard.location_date) : 'Today';
            const lastUpdateFormatted = guard.last_update ? formatDateTime(guard.last_update) : 'Recent telemetry';

            const popupContent = `
                <div class="popup-card">
                    <div class="popup-header guard-header">
                        <span class="popup-title">${escapeHtml(guard.guard_name)}</span>
                        <span class="popup-tag">Guard GPS</span>
                    </div>
                    <div class="popup-body">
                        <div class="popup-row">
                            <span class="popup-label">Badge ID:</span>
                            <span class="popup-val" style="font-family: monospace;">${escapeHtml(guard.guard_badge || '—')}</span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">Assigned Site:</span>
                            <span class="popup-val">${escapeHtml(guard.site_name || 'Unassigned')}</span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">Status:</span>
                            <span class="popup-val">
                                <span class="status-pill ${statusClass}" style="font-size:0.6875rem; padding: 0.15rem 0.5rem;">
                                    <span class="status-dot"></span>
                                    ${statusLabel}
                                </span>
                            </span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">Date:</span>
                            <span class="popup-val" style="font-weight: 700;">${dateLabel}</span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">Last Recorded:</span>
                            <span class="popup-val" style="color:#059669; font-weight:600;">${lastUpdateFormatted}</span>
                        </div>
                        <div class="popup-row">
                            <span class="popup-label">GPS Source:</span>
                            <span class="popup-val" style="font-size:0.6875rem; color:#64748b;">${sourceLabel}</span>
                        </div>
                        ${guard.address ? `<div class="popup-row" style="border-top: 1px dashed #e2e8f0; padding-top: 0.35rem; margin-top: 0.25rem;"><span class="popup-label">Address:</span><span class="popup-val" style="font-size:0.6875rem; color:#64748b;">${escapeHtml(guard.address)}</span></div>` : ''}
                    </div>
                </div>
            `;

            let popup = null;
            if (olaMapsClient && typeof olaMapsClient.addPopup === 'function') {
                popup = olaMapsClient.addPopup({ offset: [0, -20], anchor: 'bottom', closeButton: true }).setHTML(popupContent);
            } else if (PopupClass) {
                popup = new PopupClass({ offset: [0, -20], anchor: 'bottom', closeButton: true }).setHTML(popupContent);
            }

            let marker = null;
            if (olaMapsClient && typeof olaMapsClient.addMarker === 'function') {
                marker = olaMapsClient.addMarker({ element: pinWrapper, anchor: 'center' }).setLngLat(lngLat);
            } else if (MarkerClass) {
                marker = new MarkerClass({ element: pinWrapper, anchor: 'center' }).setLngLat(lngLat);
            }

            if (marker) {
                if (popup) marker.setPopup(popup);
                marker.addTo(mapInstance);
                guardMarkers.push({ marker: marker, lngLat: lngLat, type: 'guard', visible: true });
            }
        }
    });

    // Update Counter Badges
    document.getElementById('countSitesBadge').textContent = validSitesCount;
    document.getElementById('countGuardsBadge').textContent = validGuardsCount;

    // Apply current layer filter state to newly rendered markers
    setMapLayerFilter(currentLayerFilter);

    // Viewport bounds adjustment
    fitMapBounds();
}

/**
 * Single source of truth for currently visible marker coordinates
 * @returns {Array<[number, number]>} Array of [longitude, latitude] pairs
 */
function getVisibleMapCoordinates() {
    const coords = [];
    if (currentLayerFilter === 'all' || currentLayerFilter === 'sites') {
        siteMarkers.forEach(item => {
            if (item.visible && item.lngLat) {
                coords.push(item.lngLat);
            }
        });
    }
    if (currentLayerFilter === 'all' || currentLayerFilter === 'guards') {
        guardMarkers.forEach(item => {
            if (item.visible && item.lngLat) {
                coords.push(item.lngLat);
            }
        });
    }
    return coords;
}

function setMapLayerFilter(type) {
    currentLayerFilter = type;

    const btnAll = document.getElementById('btnMapAll');
    const btnSites = document.getElementById('btnMapSites');
    const btnGuards = document.getElementById('btnMapGuards');

    if (btnAll) btnAll.classList.toggle('active', type === 'all');
    if (btnSites) btnSites.classList.toggle('active', type === 'sites');
    if (btnGuards) btnGuards.classList.toggle('active', type === 'guards');

    const showSites = (type === 'all' || type === 'sites');
    const showGuards = (type === 'all' || type === 'guards');

    // Toggle Site Markers visibility via Ola Maps marker lifecycle
    siteMarkers.forEach(item => {
        if (!item.marker) return;
        if (showSites) {
            if (!item.visible) {
                if (mapInstance && typeof item.marker.addTo === 'function') {
                    item.marker.addTo(mapInstance);
                }
                item.visible = true;
            }
        } else {
            if (item.visible) {
                if (typeof item.marker.remove === 'function') {
                    item.marker.remove();
                }
                item.visible = false;
            }
        }
    });

    // Toggle Guard Markers visibility via Ola Maps marker lifecycle
    guardMarkers.forEach(item => {
        if (!item.marker) return;
        if (showGuards) {
            if (!item.visible) {
                if (mapInstance && typeof item.marker.addTo === 'function') {
                    item.marker.addTo(mapInstance);
                }
                item.visible = true;
            }
        } else {
            if (item.visible) {
                if (typeof item.marker.remove === 'function') {
                    item.marker.remove();
                }
                item.visible = false;
            }
        }
    });

    // Notice & Empty-state overlay update
    const visibleCoords = getVisibleMapCoordinates();
    const noticeElem = document.getElementById('mapLocationNotice');
    const noticeText = document.getElementById('mapNoticeText');
    if (noticeElem) {
        if (visibleCoords.length === 0) {
            noticeElem.style.display = 'flex';
            if (noticeText) {
                if (type === 'sites') {
                    noticeText.textContent = 'No duty sites with registered GPS coordinates.';
                } else if (type === 'guards') {
                    noticeText.textContent = 'No guards with active GPS telemetry.';
                } else {
                    noticeText.textContent = 'No GPS coordinates configured yet for duty sites or guards.';
                }
            }
        } else {
            noticeElem.style.display = 'none';
        }
    }
}

function fitMapBounds() {
    if (!mapInstance || typeof mapInstance.fitBounds !== 'function') return;

    const activeCoords = getVisibleMapCoordinates();

    if (activeCoords.length === 0) {
        if (typeof mapInstance.setCenter === 'function') {
            mapInstance.setCenter(defaultCenter);
        }
        if (typeof mapInstance.setZoom === 'function') {
            mapInstance.setZoom(11);
        }
        return;
    }

    if (activeCoords.length === 1) {
        if (typeof mapInstance.setCenter === 'function') {
            mapInstance.setCenter(activeCoords[0]);
        }
        if (typeof mapInstance.setZoom === 'function') {
            mapInstance.setZoom(14);
        }
        return;
    }

    let minLng = activeCoords[0][0];
    let maxLng = activeCoords[0][0];
    let minLat = activeCoords[0][1];
    let maxLat = activeCoords[0][1];

    for (let i = 1; i < activeCoords.length; i++) {
        const lng = activeCoords[i][0];
        const lat = activeCoords[i][1];
        if (lng < minLng) minLng = lng;
        if (lng > maxLng) maxLng = lng;
        if (lat < minLat) minLat = lat;
        if (lat > maxLat) maxLat = lat;
    }

    const BoundsClass = window.OlaMaps?.LngLatBounds || window.maplibregl?.LngLatBounds;
    if (BoundsClass) {
        try {
            const bounds = new BoundsClass([minLng, minLat], [maxLng, maxLat]);
            mapInstance.fitBounds(bounds, { padding: 50, maxZoom: 15 });
            return;
        } catch (boundsErr) {
            console.warn('[Secure360] BoundsClass construction failed, using bbox array:', boundsErr);
        }
    }

    // Direct 2D coordinate bbox fallback supported natively by MapLibre / Ola Maps
    mapInstance.fitBounds([[minLng, minLat], [maxLng, maxLat]], { padding: 50, maxZoom: 15 });
}

// ==========================================================================
// 2. Attendance Records Table & Filter Logic
// ==========================================================================
function handlePresetChange(preset) {
    const fromGroup = document.getElementById('fromDateGroup');
    const toGroup = document.getElementById('toDateGroup');
    const fromPicker = document.getElementById('fromDatePicker');
    const toPicker = document.getElementById('toDatePicker');

    if (preset === 'custom') {
        fromGroup.style.display = 'inline-flex';
        toGroup.style.display = 'inline-flex';
        if (!fromPicker.value) fromPicker.value = todayStr;
        if (!toPicker.value) toPicker.value = todayStr;
    } else {
        fromGroup.style.display = 'none';
        toGroup.style.display = 'none';
    }
}

/**
 * Cascading AJAX: Client change updates Site and Guard options
 */
function onClientChange(clientId) {
    const siteSelect = document.getElementById('filterSiteSelect');
    const guardSelect = document.getElementById('filterGuardSelect');

    // Reset downstream selections immediately
    siteSelect.value = '';
    guardSelect.value = '';

    let url = '<?= url("/admin/attendance/ajax/filter-options") ?>';
    if (clientId) {
        url += '?customer_id=' + encodeURIComponent(clientId);
    }

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(response => {
            if (response && response.success && response.data) {
                // Update Sites
                let siteHtml = '<option value="">All Sites</option>';
                (response.data.sites || []).forEach(s => {
                    siteHtml += `<option value="${s.id}">${escapeHtml(s.site_name)}</option>`;
                });
                siteSelect.innerHTML = siteHtml;

                // Update Guards
                let guardHtml = '<option value="">All Guards</option>';
                (response.data.guards || []).forEach(g => {
                    guardHtml += `<option value="${g.guard_id}">${escapeHtml(g.full_name)} (${escapeHtml(g.employee_code)})</option>`;
                });
                guardSelect.innerHTML = guardHtml;
            }
        })
        .catch(err => console.error('Failed to update cascading filters:', err));
}

/**
 * Cascading AJAX: Site change updates Guard options and synchronizes Client
 */
function onSiteChange(siteId) {
    const clientSelect = document.getElementById('filterClientSelect');
    const guardSelect = document.getElementById('filterGuardSelect');
    const clientId = clientSelect.value;

    // Reset guard selection immediately
    guardSelect.value = '';

    let url = '<?= url("/admin/attendance/ajax/filter-options") ?>?';
    if (clientId) url += 'customer_id=' + encodeURIComponent(clientId) + '&';
    if (siteId) url += 'site_id=' + encodeURIComponent(siteId);

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(response => {
            if (response && response.success && response.data) {
                // If a site was picked without client, synchronize parent client automatically
                if (response.resolved_customer_id && !clientSelect.value) {
                    clientSelect.value = response.resolved_customer_id;
                }
                // If the selected site was rejected as invalid for the client, reset site selector
                if (response.resolved_site_id === null && siteId) {
                    siteSelect.value = '';
                }

                let guardHtml = '<option value="">All Guards</option>';
                (response.data.guards || []).forEach(g => {
                    guardHtml += `<option value="${g.guard_id}">${escapeHtml(g.full_name)} (${escapeHtml(g.employee_code)})</option>`;
                });
                guardSelect.innerHTML = guardHtml;
            }
        })
        .catch(err => console.error('Failed to update guard options:', err));
}

function filterRecords() {
    const searchQuery = (document.getElementById('tableSearchInput').value || '').toLowerCase().trim();

    // 1. In-memory filter on table records
    filteredRecords = RAW_ATTENDANCE_RECORDS.filter(record => {
        if (searchQuery) {
            const guardName = (record.guard_name || '').toLowerCase();
            const guardBadge = (record.guard_badge || '').toLowerCase();
            const siteName = (record.site_name || '').toLowerCase();
            const customerName = (record.customer_name || '').toLowerCase();
            const contractCode = (record.contract_code || '').toLowerCase();

            const matchesSearch = guardName.includes(searchQuery) ||
                guardBadge.includes(searchQuery) ||
                siteName.includes(searchQuery) ||
                customerName.includes(searchQuery) ||
                contractCode.includes(searchQuery);

            if (!matchesSearch) return false;
        }
        return true;
    });

    // 2. In-memory filter on Map Sites & Guard Pins
    let activeSites = RAW_DUTY_SITES;
    let activeGuards = RAW_GUARD_LOCATIONS;
    if (searchQuery) {
        activeSites = RAW_DUTY_SITES.filter(s => {
            const name = (s.name || s.site_name || '').toLowerCase();
            const cust = (s.customer_name || '').toLowerCase();
            const code = (s.contract_code || s.site_code || '').toLowerCase();
            const hasGuard = (s.guards || []).some(g => (g.name || '').toLowerCase().includes(searchQuery) || (g.badge || '').toLowerCase().includes(searchQuery));
            return name.includes(searchQuery) || cust.includes(searchQuery) || code.includes(searchQuery) || hasGuard;
        });
        activeGuards = RAW_GUARD_LOCATIONS.filter(g => {
            const name = (g.guard_name || '').toLowerCase();
            const badge = (g.guard_badge || '').toLowerCase();
            const site = (g.site_name || '').toLowerCase();
            const cust = (g.customer_name || '').toLowerCase();
            return name.includes(searchQuery) || badge.includes(searchQuery) || site.includes(searchQuery) || cust.includes(searchQuery);
        });
    }

    renderMapMarkers(activeSites, activeGuards);
    currentPage = 1;
    renderAttendanceTable();
}

function renderAttendanceTable() {
    const tbody = document.getElementById('attendanceTableBody');
    const emptyState = document.getElementById('tableEmptyState');
    const paginationFooter = document.getElementById('tablePaginationFooter');
    const totalRecordsBadge = document.getElementById('totalRecordsBadge');

    totalRecordsBadge.textContent = `${filteredRecords.length} Record${filteredRecords.length === 1 ? '' : 's'}`;

    if (filteredRecords.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        paginationFooter.style.display = 'none';
        return;
    }

    emptyState.style.display = 'none';
    paginationFooter.style.display = 'flex';

    // Calculate pagination slice (Strictly max 10 records per page)
    const totalPages = Math.ceil(filteredRecords.length / PAGE_SIZE) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIndex = (currentPage - 1) * PAGE_SIZE;
    const endIndex = Math.min(startIndex + PAGE_SIZE, filteredRecords.length);
    const pagedRecords = filteredRecords.slice(startIndex, endIndex);

    let rowsHtml = '';
    pagedRecords.forEach(r => {
        // Guard initials
        const name = r.guard_name || 'Guard';
        const initials = name.split(' ').filter(n => n.length > 0).slice(0, 2).map(n => n[0].toUpperCase()).join('') || 'GD';
        const badge = r.guard_badge || 'GRD-—';

        // Site details
        const siteName = r.site_name || 'Unassigned Site';
        const siteAddress = r.site_address || r.customer_name || '—';

        // Contract
        const contractCode = r.contract_code ? `<span class="contract-code-tag">${escapeHtml(r.contract_code)}</span>` : '<span style="color:#94a3b8;">—</span>';

        // Shift
        let shiftHtml = '<span style="color:#94a3b8;">Standard</span>';
        if (r.shift_name) {
            const hours = (r.shift_start && r.shift_end) ? `${formatTime(r.shift_start)} - ${formatTime(r.shift_end)}` : '';
            shiftHtml = `
                <div class="shift-tag">
                    <span>${escapeHtml(r.shift_name)}</span>
                    ${hours ? `<span class="shift-hours">${hours}</span>` : ''}
                </div>
            `;
        }

        // Check In Time
        const checkInFormatted = r.check_in_at ? formatDateTime(r.check_in_at) : '—';

        // Check Out Time & Duration
        let checkOutFormatted = '';
        let durationFormatted = '—';
        let isDurationActive = false;

        if (r.check_out_at) {
            checkOutFormatted = `<span class="time-stamp">${formatDateTime(r.check_out_at)}</span>`;
            if (r.check_in_at) {
                const diffSecs = Math.max(0, (parseToTimestampMs(r.check_out_at) - parseToTimestampMs(r.check_in_at)) / 1000);
                const hrs = Math.floor(diffSecs / 3600);
                const mins = Math.floor((diffSecs % 3600) / 60);
                durationFormatted = `${hrs}h ${mins}m`;
            }
        } else {
            // Still on duty or no check-out
            if (parseInt(r.status, 10) === 0) {
                checkOutFormatted = `<span class="time-stamp-active">Currently on duty</span>`;
                if (r.check_in_at) {
                    const diffSecs = Math.max(0, (Date.now() - parseToTimestampMs(r.check_in_at)) / 1000);
                    const hrs = Math.floor(diffSecs / 3600);
                    const mins = Math.floor((diffSecs % 3600) / 60);
                    durationFormatted = `${hrs}h ${mins}m (Active)`;
                    isDurationActive = true;
                }
            } else {
                checkOutFormatted = `<span style="color:#94a3b8; font-style:italic;">No check-out</span>`;
            }
        }

        // Status Badge
        const st = parseInt(r.status, 10);
        let statusBadge = '';
        if (st === 0) {
            statusBadge = '<span class="status-pill status-on-duty"><span class="status-dot"></span>On Duty</span>';
        } else if (st === 1) {
            statusBadge = '<span class="status-pill status-completed"><span class="status-dot"></span>Completed</span>';
        } else {
            statusBadge = '<span class="status-pill status-cancelled"><span class="status-dot"></span>Cancelled</span>';
        }

        rowsHtml += `
            <tr>
                <td>
                    <div class="guard-cell">
                        <div class="guard-avatar">${escapeHtml(initials)}</div>
                        <div>
                            <div class="guard-info-name">${escapeHtml(name)}</div>
                            <div class="guard-info-badge">${escapeHtml(badge)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="site-cell-title">${escapeHtml(siteName)}</div>
                    <div class="site-cell-sub">${escapeHtml(siteAddress)}</div>
                </td>
                <td>${contractCode}</td>
                <td>${shiftHtml}</td>
                <td><span class="time-stamp">${checkInFormatted}</span></td>
                <td>${checkOutFormatted}</td>
                <td>
                    <span class="duration-badge ${isDurationActive ? 'duration-active' : ''}">
                        ${durationFormatted}
                    </span>
                </td>
                <td>${statusBadge}</td>
                <td style="text-align: right;">
                    <a href="${ATTENDANCE_BASE_URL}/${r.id}" class="btn-action-view" title="View Attendance Details">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </a>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;

    // Render Pagination Controls
    renderPaginationControls(startIndex, endIndex, filteredRecords.length, totalPages);
}

function renderPaginationControls(start, end, total, totalPages) {
    const infoText = document.getElementById('paginationInfoText');
    const paginationControls = document.getElementById('paginationButtons');

    infoText.textContent = `Showing ${total > 0 ? start + 1 : 0} to ${end} of ${total} records`;

    let btnsHtml = '';

    // Prev Button
    btnsHtml += `
        <button type="button" class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})" title="Previous Page">
            &lsaquo;
        </button>
    `;

    // Max 5 page numbers visible
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let p = startPage; p <= endPage; p++) {
        btnsHtml += `
            <button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" onclick="goToPage(${p})">
                ${p}
            </button>
        `;
    }

    // Next Button
    btnsHtml += `
        <button type="button" class="page-btn ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})" title="Next Page">
            &rsaquo;
        </button>
    `;

    paginationControls.innerHTML = btnsHtml;
}

function goToPage(page) {
    currentPage = page;
    renderAttendanceTable();
    const tableElem = document.getElementById('attendanceTable');
    if (tableElem) {
        tableElem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function handleFilterChange() {
    currentPage = 1;
    filterRecords();
    renderAttendanceTable();
}

function resetTableFilters() {
    window.location.href = ATTENDANCE_BASE_URL;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const parts = String(dateStr).trim().split(/[ T]/)[0].split('-');
        if (parts.length < 3) return dateStr;
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1;
        const d = parseInt(parts[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(d)) return dateStr;
        const dayStr = String(d).padStart(2, '0');
        const monthStr = MONTH_NAMES_SHORT[m] || '';
        const yearStr = String(y).slice(-2);
        return `${dayStr}-${monthStr}-${yearStr}`;
    } catch (e) {
        return dateStr;
    }
}

// ==========================================================================
// Utility Helpers
// ==========================================================================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

const MONTH_NAMES_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function formatTime(timeStr) {
    if (!timeStr) return '';
    try {
        const parts = timeStr.trim().split(':');
        if (parts.length < 2) return timeStr;
        let hours = parseInt(parts[0], 10);
        const mins = parts[1].padStart(2, '0');
        const period = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        if (hours === 0) hours = 12;
        const hrStr = String(hours).padStart(2, '0');
        return `${hrStr}:${mins} ${period}`;
    } catch (e) {
        return timeStr;
    }
}

function parseToTimestampMs(str) {
    if (!str) return 0;
    const s = String(str).trim();
    if (s.endsWith('Z') || s.endsWith('z') || /[+-]\d{2}:?\d{2}$/.test(s)) {
        return new Date(s).getTime();
    }
    // Parse "YYYY-MM-DD HH:MM:SS" as IST:
    const parts = s.split(/[ T]/);
    const dateParts = parts[0].split('-');
    const timeParts = (parts[1] || '00:00:00').split(':');
    const y = parseInt(dateParts[0], 10);
    const m = parseInt(dateParts[1], 10) - 1;
    const d = parseInt(dateParts[2], 10);
    const h = parseInt(timeParts[0], 10) || 0;
    const min = parseInt(timeParts[1], 10) || 0;
    const sec = parseInt(timeParts[2], 10) || 0;
    // Date.UTC() creates UTC timestamp; subtract 5.5 hours to get exact absolute epoch ms of IST
    return Date.UTC(y, m, d, h, min, sec) - (5.5 * 3600 * 1000);
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    try {
        let year, month, day, hour = 0, min = 0;
        const str = String(dateStr).trim();

        // If string has explicit timezone offset or 'Z', parse in UTC and convert to IST (+05:30)
        if (str.endsWith('Z') || str.endsWith('z') || /[+-]\d{2}:?\d{2}$/.test(str)) {
            const dUtc = new Date(str);
            if (isNaN(dUtc.getTime())) return dateStr;
            const istTime = new Date(dUtc.getTime() + (5.5 * 60 * 60 * 1000));
            year = istTime.getUTCFullYear();
            month = istTime.getUTCMonth();
            day = istTime.getUTCDate();
            hour = istTime.getUTCHours();
            min = istTime.getUTCMinutes();
        } else {
            // Timestamp WITHOUT timezone (e.g. "2026-09-18 13:00:00" or "2026-09-18T13:00:00")
            // Generated by backend/MySQL in IST. Values ALREADY represent Indian time.
            const parts = str.split(/[ T]/);
            const dateParts = parts[0].split('-');
            year = parseInt(dateParts[0], 10);
            month = parseInt(dateParts[1], 10) - 1;
            day = parseInt(dateParts[2], 10);
            if (parts.length > 1 && parts[1]) {
                const timeParts = parts[1].split(':');
                hour = parseInt(timeParts[0], 10) || 0;
                min = parseInt(timeParts[1], 10) || 0;
            }
        }

        if (isNaN(year) || isNaN(month) || isNaN(day)) return dateStr;

        const dayStr = String(day).padStart(2, '0');
        const monthStr = MONTH_NAMES_SHORT[month] || '';
        const yearStr = String(year).slice(-2);

        const period = hour >= 12 ? 'PM' : 'AM';
        let hour12 = hour % 12;
        if (hour12 === 0) hour12 = 12;
        const hrStr = String(hour12).padStart(2, '0');
        const minStr = String(min).padStart(2, '0');

        return `${dayStr}-${monthStr}-${yearStr} ${hrStr}:${minStr} ${period}`;
    } catch (e) {
        return dateStr;
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initAttendanceMap();
    filterRecords();
    renderAttendanceTable();

    setTimeout(() => {
        if (mapInstance && typeof mapInstance.resize === 'function') {
            mapInstance.resize();
        }
    }, 250);

    window.addEventListener('resize', () => {
        if (mapInstance && typeof mapInstance.resize === 'function') {
            mapInstance.resize();
        }
    });
});
</script>
