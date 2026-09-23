<?php
/**
 * Secure360 - Operational Reports & Analytics View
 * Strictly aligned with approved reference design ("Reports Module Overview").
 * Supports 6 report types, dynamic date presets, cascading dropdown filters,
 * 4 dynamic KPI cards, tailored responsive tables, and full Excel/PDF exports.
 */

$reportType = $reportType ?? 'all_operations';
$preset = $preset ?? 'this_month';
$fromDate = $fromDate ?? date('Y-m-01');
$toDate = $toDate ?? date('Y-m-d');
$customerId = $customerId ?? null;
$siteId = $siteId ?? null;
$guardId = $guardId ?? null;
$status = $status ?? 'all';
$search = $search ?? '';

$filterOptions = $filterOptions ?? ['clients' => [], 'sites' => [], 'guards' => []];
$records = $records ?? [];
$totalRecords = $totalRecords ?? 0;
$summary = $summary ?? [];
$currentPage = $currentPage ?? 1;
$pageSize = $pageSize ?? 25;
$queryParams = $queryParams ?? $_GET;

$dateError = $dateError ?? null;

// Report Type Labels - Section Titles strictly match specifications
$reportTypeNames = [
    'all_operations' => 'All Operations',
    'attendance' => 'Attendance',
    'guards' => 'Guard',
    'sites_clients' => 'Site & Client',
    'shifts' => 'Shift',
    'contracts' => 'Contract',
];
$activeReportTitle = $reportTypeNames[$reportType] ?? 'All Operations';
?>

<div class="page-container">
    <!-- 1. Page Header -->
    <div class="reports-header-section" style="margin-bottom: 1.5rem;">
        <h1 class="page-header-title" style="font-size: 1.75rem; font-weight: 700; color: #0f172a; margin: 0;">
            Operational Reports &amp; Analytics
        </h1>
    </div>

    <?php if (!empty($dateError)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 0.85rem 1.25rem; color: #991b1b; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= e($dateError) ?>
        </div>
    <?php endif; ?>

    <!-- 2. Standardized Reports Filter Card -->
    <div class="card reports-filter-card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <form method="GET" action="<?= url('/admin/reports') ?>" id="reportsFilterForm">
            <!-- Row 1: Report, Date Preset, Custom Date Pickers, Client, Site -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; margin-bottom: 1rem;">
                
                <!-- Report Type -->
                <div class="filter-field">
                    <label for="reportTypeSelect" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Report
                    </label>
                    <select name="report_type" id="reportTypeSelect" class="form-control filter-select" onchange="handleReportTypeChange(this.value)" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem; background-color: #ffffff; font-weight: 500;">
                        <option value="all_operations" <?= $reportType === 'all_operations' ? 'selected' : '' ?>>All Operations</option>
                        <option value="attendance" <?= $reportType === 'attendance' ? 'selected' : '' ?>>Attendance</option>
                        <option value="guards" <?= $reportType === 'guards' ? 'selected' : '' ?>>Guards</option>
                        <option value="sites_clients" <?= $reportType === 'sites_clients' ? 'selected' : '' ?>>Sites &amp; Clients</option>
                        <option value="shifts" <?= $reportType === 'shifts' ? 'selected' : '' ?>>Shifts</option>
                        <option value="contracts" <?= $reportType === 'contracts' ? 'selected' : '' ?>>Contracts</option>
                    </select>
                </div>

                <!-- Date Preset -->
                <div class="filter-field">
                    <label for="datePresetSelect" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Date Range
                    </label>
                    <select name="preset" id="datePresetSelect" class="form-control filter-select" onchange="handlePresetChange(this.value)" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem; background-color: #ffffff; font-weight: 500;">
                        <option value="today" <?= $preset === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="yesterday" <?= $preset === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="this_week" <?= $preset === 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="this_month" <?= $preset === 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $preset === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="custom" <?= $preset === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        <option value="all" <?= $preset === 'all' ? 'selected' : '' ?>>All Time</option>
                    </select>
                </div>

                <!-- From Date -->
                <div class="filter-field" id="fromDateWrapper" style="<?= $preset === 'custom' ? '' : 'display: none;' ?>">
                    <label for="fromDatePicker" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        From Date
                    </label>
                    <input type="date" name="from_date" id="fromDatePicker" class="form-control" value="<?= e($fromDate) ?>" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem;">
                </div>

                <!-- To Date -->
                <div class="filter-field" id="toDateWrapper" style="<?= $preset === 'custom' ? '' : 'display: none;' ?>">
                    <label for="toDatePicker" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        To Date
                    </label>
                    <input type="date" name="to_date" id="toDatePicker" class="form-control" value="<?= e($toDate) ?>" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem;">
                </div>

                <!-- Client Selector -->
                <div class="filter-field">
                    <label for="clientSelect" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Client
                    </label>
                    <select name="customer_id" id="clientSelect" class="form-control filter-select" onchange="handleClientChange(this.value)" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem; background-color: #ffffff;">
                        <option value="">All Clients</option>
                        <?php foreach (($filterOptions['clients'] ?? []) as $client): ?>
                            <option value="<?= (int)$client['id'] ?>" <?= ((int)$customerId === (int)$client['id']) ? 'selected' : '' ?>>
                                <?= e($client['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Site Selector -->
                <div class="filter-field">
                    <label for="siteSelect" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Site
                    </label>
                    <select name="site_id" id="siteSelect" class="form-control filter-select" onchange="handleSiteChange(this.value)" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem; background-color: #ffffff;">
                        <option value="">All Sites</option>
                        <?php foreach (($filterOptions['sites'] ?? []) as $site): ?>
                            <option value="<?= (int)$site['id'] ?>" <?= ((int)$siteId === (int)$site['id']) ? 'selected' : '' ?>>
                                <?= e($site['site_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Guard, Status, Search, Action Buttons -->
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <!-- Guard Selector (hidden for contracts report) -->
                <div class="filter-field" id="guardFieldWrapper" style="flex: 1; min-width: 180px; <?= $reportType === 'contracts' ? 'display: none;' : '' ?>">
                    <label for="guardSelect" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Guard
                    </label>
                    <select name="guard_id" id="guardSelect" class="form-control filter-select" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem; background-color: #ffffff;">
                        <option value="">All Guards</option>
                        <?php foreach (($filterOptions['guards'] ?? []) as $guard): ?>
                            <option value="<?= (int)$guard['guard_id'] ?>" <?= ((int)$guardId === (int)$guard['guard_id']) ? 'selected' : '' ?>>
                                <?= e($guard['full_name']) ?> (<?= e($guard['employee_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Selector -->
                <div class="filter-field" style="flex: 1; min-width: 160px;">
                    <label for="statusSelect" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Status
                    </label>
                    <select name="status" id="statusSelect" class="form-control filter-select" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding: 0 0.75rem; background-color: #ffffff;">
                        <?php if ($reportType === 'contracts'): ?>
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="expiring_soon" <?= $status === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                            <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <?php elseif ($reportType === 'guards' || $reportType === 'sites_clients'): ?>
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <?php else: ?>
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="on_duty" <?= $status === 'on_duty' ? 'selected' : '' ?>>On Duty</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Quick Search Input -->
                <div class="filter-field" style="flex: 1.5; min-width: 220px;">
                    <label for="searchQueryInput" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">
                        Search Records
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 10px; top: 10px; color: #94a3b8;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" name="search" id="searchQueryInput" class="form-control" placeholder="Search by name, site, client..." value="<?= e($search) ?>" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.875rem; padding-left: 2rem; padding-right: 0.75rem;">
                    </div>
                </div>

                <!-- Action Buttons: Clear, Generate, Export Dropdown -->
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <!-- Clear Filters -->
                    <a href="<?= url('/admin/reports') ?>" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0 1rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none;" title="Reset all filters">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Clear Filters
                    </a>

                    <!-- Generate Report -->
                    <button type="submit" class="btn btn-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0 1.25rem; border-radius: 6px; border: none; background: #2563eb; color: #ffffff; font-size: 0.875rem; font-weight: 600; cursor: pointer;">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Generate Report
                    </button>

                    <!-- Export Dropdown Button -->
                    <div class="export-dropdown-wrapper" style="position: relative;">
                        <button type="button" id="exportDropdownBtn" onclick="toggleExportMenu(event)" class="btn" style="height: 38px; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0 1.15rem; border-radius: 6px; border: 1px solid #16a34a; background: #16a34a; color: #ffffff; font-size: 0.875rem; font-weight: 600; cursor: pointer;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Export
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="exportMenu" style="display: none; position: absolute; right: 0; top: 44px; z-index: 50; min-width: 210px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.05); padding: 0.35rem 0;">
                            <a href="javascript:void(0)" onclick="executeExport('excel')" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.65rem 1rem; color: #1e293b; font-size: 0.875rem; text-decoration: none; font-weight: 500;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                Export as Excel (.xlsx)
                            </a>
                            <a href="javascript:void(0)" onclick="executeExport('pdf')" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.65rem 1rem; color: #1e293b; font-size: 0.875rem; text-decoration: none; font-weight: 500;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 12h4m-4 4h4"/></svg>
                                Export as PDF (.pdf)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- 3. Dynamic Summary KPI Cards (4 Cards per Report Type) -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
        <?php foreach (['card1', 'card2', 'card3', 'card4'] as $idx => $cardKey): ?>
            <?php 
                $card = $summary[$cardKey] ?? ['label' => 'Metric', 'value' => 0, 'sub' => ''];
                $valColor = '#0f172a';
                if ($idx === 1) $valColor = '#16a34a';
                if ($idx === 2) $valColor = '#2563eb';
                if ($idx === 3) $valColor = '#d97706';
            ?>
            <div class="card" style="padding: 1.25rem 1.5rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                    <?= e($card['label']) ?>
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: <?= $valColor ?>; margin: 0.35rem 0 0.15rem 0; line-height: 1.2;">
                    <?= number_format((int)$card['value']) ?>
                </div>
                <div style="font-size: 0.8125rem; color: #94a3b8; font-weight: 500;">
                    <?= e($card['sub']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 4. Filtered Results Table Container -->
    <div class="card" style="padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
        <!-- Table Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin: 0;">
                    <?= e($activeReportTitle) ?> Records
                </h3>
                <span style="display: inline-block; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                    <?= number_format($totalRecords) ?> <?= $totalRecords === 1 ? 'Record' : 'Records' ?>
                </span>
            </div>
        </div>

        <!-- Responsive Table -->
        <div class="table-container" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="data-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <?php if ($reportType === 'contracts'): ?>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Contract Code</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Client</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Duty Site</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Start Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">End Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Required Guards</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Assigned Guards</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Status</th>

                        <?php elseif ($reportType === 'shifts'): ?>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Client</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Duty Site</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Scheduled Time</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Guard</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">In / Out</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift Status</th>

                        <?php elseif ($reportType === 'sites_clients'): ?>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Client</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Duty Site</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Site Status</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Guard</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Badge ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">In / Out</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Attendance</th>

                        <?php elseif ($reportType === 'guards'): ?>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Guard Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Badge ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Duty Site</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Client</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Assignment</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">In / Out</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Status</th>

                        <?php elseif ($reportType === 'attendance'): ?>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Guard Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Badge ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Client / Site</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Scheduled</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">In / Out</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Attendance</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">GPS Status</th>

                        <?php else: /* all_operations */ ?>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Guard Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Badge ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Client / Site</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">In / Out</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">GPS Verification</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Attendance</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">Shift Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 3rem 1.5rem; color: #94a3b8;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem;">
                                    <svg width="40" height="40" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span style="font-size: 1rem; font-weight: 600; color: #64748b;">No records found for the selected filters.</span>
                                    <span style="font-size: 0.8125rem; color: #94a3b8;">Try adjusting your date range, client, site, or status filters.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                <?php if ($reportType === 'contracts'): ?>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #2563eb;">
                                        <code><?= e($row['contract_code']) ?></code>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['customer_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <?= e($row['site_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= !empty($row['start_date']) ? format_date($row['start_date']) : '—' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= (!empty($row['end_date']) && $row['end_date'] !== 'Ongoing') ? format_date($row['end_date']) : '<span style="color: #10b981; font-weight: 500;">Ongoing</span>' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; text-align: center; font-weight: 600; color: #0f172a;">
                                        <?= (int)$row['guard_limit'] ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; text-align: center;">
                                        <span style="font-weight: 600; color: <?= (int)$row['assigned_guards'] >= (int)$row['guard_limit'] ? '#16a34a' : '#2563eb' ?>;">
                                            <?= (int)$row['assigned_guards'] ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['status_label'] === 'Active'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Active
                                            </span>
                                        <?php elseif (str_contains($row['status_label'], 'Expiring')): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fffbeb; color: #d97706; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;"></span> Expiring Soon
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Expired
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($reportType === 'shifts'): ?>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= !empty($row['date']) && $row['date'] !== '—' ? format_date($row['date']) : '—' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['shift_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <?= e($row['customer_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <?= e($row['site_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= e($row['scheduled_time']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['guard_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8125rem; color: #475569;">
                                        <strong><?= e($row['check_in']) ?></strong> / <?= e($row['check_out']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['shift_status'] === 'Completed'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Completed
                                            </span>
                                        <?php elseif ($row['shift_status'] === 'Active'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #3b82f6;"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Missed
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($reportType === 'sites_clients'): ?>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= !empty($row['date']) && $row['date'] !== '—' ? format_date($row['date']) : '—' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['customer_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <?= e($row['site_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.15rem 0.55rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                            <?= e($row['site_status_label'] ?? 'Active') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['guard_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <code><?= e($row['guard_badge']) ?></code>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569; font-size: 0.8125rem;">
                                        <?= e($row['shift_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8125rem; color: #475569;">
                                        <strong><?= e($row['check_in']) ?></strong> / <?= e($row['check_out']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['attendance_status'] === 'Present'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Present
                                            </span>
                                        <?php elseif ($row['attendance_status'] === 'Late'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fffbeb; color: #d97706; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;"></span> Late
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Missing
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($reportType === 'guards'): ?>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= !empty($row['date']) && $row['date'] !== '—' ? format_date($row['date']) : '—' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['guard_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <code><?= e($row['guard_badge']) ?></code>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <?= e($row['site_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <?= e($row['customer_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569; font-size: 0.8125rem;">
                                        <?= e($row['shift_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.15rem 0.55rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                                            <?= e($row['assignment_status_label'] ?? 'Assigned') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8125rem; color: #475569;">
                                        <strong><?= e($row['check_in']) ?></strong> / <?= e($row['check_out']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['attendance_status'] === 'Completed'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Completed
                                            </span>
                                        <?php elseif ($row['attendance_status'] === 'On Duty'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #3b82f6;"></span> On Duty
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($reportType === 'attendance'): ?>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= !empty($row['date']) && $row['date'] !== '—' ? format_date($row['date']) : '—' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['guard_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <code><?= e($row['guard_badge']) ?></code>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <strong><?= e($row['customer_name']) ?></strong><br>
                                        <span style="font-size: 0.75rem; color: #64748b;"><?= e($row['site_name']) ?></span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569; font-size: 0.8125rem;">
                                        <?= e($row['shift_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= e($row['scheduled_time']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8125rem; color: #475569;">
                                        <strong><?= e($row['check_in']) ?></strong> / <?= e($row['check_out']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['attendance_status'] === 'Completed'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Completed
                                            </span>
                                        <?php elseif ($row['attendance_status'] === 'On Duty'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #3b82f6;"></span> On Duty
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if (str_starts_with($row['gps_status'], 'Verified') || $row['gps_status'] === 'Captured'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #059669; font-weight: 600;">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                                <?= e($row['gps_status']) ?>
                                            </span>
                                        <?php elseif (str_starts_with($row['gps_status'], 'Outside')): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #d97706; font-weight: 600;">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                <?= e($row['gps_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #94a3b8;">No GPS</span>
                                        <?php endif; ?>
                                    </td>

                                <?php else: /* all_operations */ ?>
                                    <td style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8125rem;">
                                        <?= !empty($row['date']) && $row['date'] !== '—' ? format_date($row['date']) : '—' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($row['guard_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <code><?= e($row['guard_badge']) ?></code>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569;">
                                        <strong><?= e($row['customer_name']) ?></strong><br>
                                        <span style="font-size: 0.75rem; color: #64748b;"><?= e($row['site_name']) ?></span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569; font-size: 0.8125rem;">
                                        <?= e($row['shift_name']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8125rem; color: #475569;">
                                        <strong><?= e($row['check_in']) ?></strong> / <?= e($row['check_out']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if (str_starts_with($row['gps_status'], 'Verified') || $row['gps_status'] === 'Captured'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #059669; font-weight: 600;">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                                <?= e($row['gps_status']) ?>
                                            </span>
                                        <?php elseif (str_starts_with($row['gps_status'], 'Outside')): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #d97706; font-weight: 600;">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                <?= e($row['gps_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #94a3b8;">No GPS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['attendance_status'] === 'Completed'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Completed
                                            </span>
                                        <?php elseif ($row['attendance_status'] === 'On Duty'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #3b82f6;"></span> On Duty
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <?php if ($row['shift_status'] === 'Completed'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 600;">
                                                Completed
                                            </span>
                                        <?php elseif ($row['shift_status'] === 'On Duty'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 600;">
                                                On Duty
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; font-size: 0.75rem; font-weight: 600;">
                                                Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 5. Server-Side Pagination -->
        <?php if ($totalRecords > 0): ?>
            <div style="margin-top: 1.5rem; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                <?php 
                    \App\Core\View::component('components/pagination', [
                        'currentPage' => $currentPage,
                        'totalRecords' => $totalRecords,
                        'pageSize' => $pageSize,
                        'queryParams' => $queryParams,
                        'baseUrl' => url('/admin/reports'),
                    ]);
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic Client-Side Cascading Filters & Export Scripts -->
<script>
/**
 * Handle report type change: dynamically toggle guard filter and update status options
 */
function handleReportTypeChange(reportType) {
    const guardWrapper = document.getElementById('guardFieldWrapper');
    const statusSelect = document.getElementById('statusSelect');
    const prevStatus = statusSelect.value;

    if (reportType === 'contracts') {
        if (guardWrapper) guardWrapper.style.display = 'none';
        // Contracts status options
        statusSelect.innerHTML = `
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="expiring_soon">Expiring Soon</option>
            <option value="expired">Expired</option>
        `;
        const valid = ['all', 'active', 'expiring_soon', 'expired'];
        statusSelect.value = valid.includes(prevStatus) ? prevStatus : 'all';
    } else if (reportType === 'guards' || reportType === 'sites_clients') {
        if (guardWrapper) guardWrapper.style.display = 'block';
        // Entity status options (Active / Inactive)
        statusSelect.innerHTML = `
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        `;
        const valid = ['all', 'active', 'inactive'];
        statusSelect.value = valid.includes(prevStatus) ? prevStatus : 'all';
    } else {
        if (guardWrapper) guardWrapper.style.display = 'block';
        // Operational / Attendance status options
        statusSelect.innerHTML = `
            <option value="all">All Statuses</option>
            <option value="on_duty">On Duty</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        `;
        const valid = ['all', 'on_duty', 'completed', 'cancelled'];
        statusSelect.value = valid.includes(prevStatus) ? prevStatus : 'all';
    }
}

/**
 * Handle Date Preset selection
 */
function handlePresetChange(preset) {
    const fromWrapper = document.getElementById('fromDateWrapper');
    const toWrapper = document.getElementById('toDateWrapper');
    const fromInput = document.getElementById('fromDatePicker');
    const toInput = document.getElementById('toDatePicker');

    const today = new Date();
    const formatDate = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    if (preset === 'custom') {
        fromWrapper.style.display = 'block';
        toWrapper.style.display = 'block';
        return;
    } else {
        fromWrapper.style.display = 'none';
        toWrapper.style.display = 'none';
    }

    if (preset === 'today') {
        fromInput.value = formatDate(today);
        toInput.value = formatDate(today);
    } else if (preset === 'yesterday') {
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        fromInput.value = formatDate(y);
        toInput.value = formatDate(y);
    } else if (preset === 'this_week') {
        const d = new Date(today);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday
        d.setDate(diff);
        fromInput.value = formatDate(d);
        toInput.value = formatDate(new Date());
    } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        fromInput.value = formatDate(firstDay);
        toInput.value = formatDate(lastDay);
    } else if (preset === 'last_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
        fromInput.value = formatDate(firstDay);
        toInput.value = formatDate(lastDay);
    } else if (preset === 'all') {
        fromInput.value = '';
        toInput.value = '';
    }
}

/**
 * Cascading AJAX: Client change updates Site and Guard options
 */
function handleClientChange(clientId) {
    const siteSelect = document.getElementById('siteSelect');
    const guardSelect = document.getElementById('guardSelect');

    // Reset downstream selection immediately
    siteSelect.value = '';
    guardSelect.value = '';

    let url = '<?= url("/admin/reports/ajax/filter-options") ?>';
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
function handleSiteChange(siteId) {
    const clientSelect = document.getElementById('clientSelect');
    const guardSelect = document.getElementById('guardSelect');
    const clientId = clientSelect.value;

    // Reset guard selection immediately
    guardSelect.value = '';

    let url = '<?= url("/admin/reports/ajax/filter-options") ?>?';
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

/**
 * Export dropdown toggle & click-outside dismiss
 */
function toggleExportMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('exportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('exportMenu');
    if (menu && menu.style.display === 'block') {
        menu.style.display = 'none';
    }
});

/**
 * Execute full dataset export matching currently applied filters
 */
function executeExport(type) {
    const form = document.getElementById('reportsFilterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    const menu = document.getElementById('exportMenu');
    if (menu) menu.style.display = 'none';

    let exportUrl = '';
    if (type === 'excel') {
        exportUrl = '<?= url("/admin/reports/export/excel") ?>?' + params.toString();
    } else if (type === 'pdf') {
        exportUrl = '<?= url("/admin/reports/export/pdf") ?>?' + params.toString();
    }

    if (exportUrl) {
        window.location.href = exportUrl;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

// Client-side validation on filter submission
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('reportsFilterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            const presetSelect = document.getElementById('datePresetSelect');
            if (presetSelect && presetSelect.value === 'custom') {
                const fromPicker = document.getElementById('fromDatePicker');
                const toPicker = document.getElementById('toDatePicker');
                if (fromPicker && toPicker) {
                    if (!fromPicker.value || !toPicker.value) {
                        e.preventDefault();
                        alert('Invalid Date Range: Both From Date and To Date must be provided.');
                        return false;
                    }
                    if (fromPicker.value > toPicker.value) {
                        e.preventDefault();
                        alert('Invalid Date Range: "From Date" cannot be after "To Date".');
                        return false;
                    }
                }
            }
        });
    }
});
</script>
</div>
