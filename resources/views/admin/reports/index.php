<?php
/**
 * Admin Operational Reports & Analytics View
 */
$stats = $attendanceStats ?? [];
$coverage = $siteCoverage ?? [];
$logs = $attendanceLogs ?? [];
$preset = $preset ?? 'today';
$fromDate = $fromDate ?? '';
$toDate = $toDate ?? '';
$searchQuery = $searchQuery ?? '';

// Build human-friendly date subtitle
$periodLabel = 'Today (' . format_date(date('Y-m-d')) . ')';
if ($preset === 'yesterday') {
    $periodLabel = 'Yesterday (' . format_date('-1 day') . ')';
} elseif ($preset === 'specific') {
    $periodLabel = $fromDate ? format_date($fromDate) : 'Specific Date';
} elseif ($preset === 'custom') {
    $periodLabel = format_date_range($fromDate, $toDate);
} elseif ($preset === 'all') {
    $periodLabel = 'All Time';
}
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title">Operational Reports &amp; Analytics</h1>
            <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                Period: <strong style="color: #0f172a;"><?= e($periodLabel) ?></strong>
                <?php if ($searchQuery !== ''): ?>
                    &bull; Filtering by: <strong style="color: #2563eb;">"<?= e($searchQuery) ?>"</strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Standardized Reports Filter Toolbar -->
    <div class="reports-filter-card">
        <form method="GET" action="<?= url('/admin/reports') ?>" class="reports-toolbar-form" id="reportsFilterForm">
            <!-- Date Preset Dropdown -->
            <div class="filter-control-group">
                <label for="datePresetSelect" class="filter-control-label">Date:</label>
                <select name="preset" id="datePresetSelect" class="filter-select" onchange="handlePresetChange(this.value)">
                    <option value="today" <?= $preset === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $preset === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="specific" <?= $preset === 'specific' ? 'selected' : '' ?>>Specific Date</option>
                    <option value="custom" <?= $preset === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    <option value="all" <?= $preset === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </div>

            <!-- Specific / From Date Selector -->
            <div class="filter-control-group" id="fromDateGroup" style="<?= in_array($preset, ['today', 'yesterday', 'all']) ? 'display: none;' : 'display: inline-flex;' ?>">
                <label for="fromDatePicker" class="filter-control-label" id="fromDateLabel"><?= $preset === 'specific' ? 'Date:' : 'From:' ?></label>
                <input type="date" name="from_date" id="fromDatePicker" class="filter-date-input" value="<?= e($fromDate) ?>">
            </div>

            <!-- To Date Selector (Only shown for Custom Range) -->
            <div class="filter-control-group" id="toDateGroup" style="<?= $preset === 'custom' ? 'display: inline-flex;' : 'display: none;' ?>">
                <label for="toDatePicker" class="filter-control-label">To:</label>
                <input type="date" name="to_date" id="toDatePicker" class="filter-date-input" value="<?= e($toDate) ?>">
            </div>

            <!-- Standardized Search Bar matching Guards reference -->
            <div class="toolbar-search" style="flex: 1; min-width: 260px;">
                <div class="input-icon-wrapper" style="width: 100%;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search reports, guards, sites..." value="<?= e($searchQuery) ?>">
                </div>
            </div>

            <!-- Action Buttons -->
            <button type="submit" class="btn-filter-submit">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                View Report
            </button>

            <?php if ($preset !== 'today' || $searchQuery !== ''): ?>
                <a href="<?= url('/admin/reports') ?>" class="btn-filter-reset">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card" style="margin-bottom:0; padding: 1.25rem;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Attendance Records</div>
            <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
                <?= e($stats['total_records'] ?? 0) ?>
            </div>
            <span style="font-size: 0.75rem; color: #10b981; font-weight: 500;">In selected period</span>
        </div>

        <div class="card" style="margin-bottom:0; padding: 1.25rem;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Guards On Duty</div>
            <div style="font-size: 1.875rem; font-weight: 700; color: #16a34a; margin-top: 0.25rem;">
                <?= e($stats['on_duty'] ?? 0) ?>
            </div>
            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 500;">Active duty records</span>
        </div>

        <div class="card" style="margin-bottom:0; padding: 1.25rem;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Shifts Completed</div>
            <div style="font-size: 1.875rem; font-weight: 700; color: #2563eb; margin-top: 0.25rem;">
                <?= e($stats['completed'] ?? 0) ?>
            </div>
            <span style="font-size: 0.75rem; color: #2563eb; font-weight: 500;">Checked out safely</span>
        </div>

        <div class="card" style="margin-bottom:0; padding: 1.25rem;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Monitored Posts</div>
            <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
                <?= count($coverage) ?>
            </div>
            <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Configured physical sites</span>
        </div>
    </div>

<!-- Site Coverage Report Table -->
<div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <div style="margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a;">Site Coverage &amp; Guard Allocations</h3>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Site Code</th>
                    <th>Site Name</th>
                    <th>Client Name</th>
                    <th>Assigned Guards</th>
                    <th>Active Shifts</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coverage)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 2rem;">No sites registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($coverage as $site): ?>
                        <tr class="cov-row">
                            <td><code><?= e($site['site_code']) ?></code></td>
                            <td style="font-weight: 600; color: #0f172a;"><?= e($site['site_name']) ?></td>
                            <td><?= e($site['customer_name']) ?></td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; color: #2563eb;">
                                    <?= e($site['assigned_guards']) ?> Guards
                                </span>
                            </td>
                            <td><?= e($site['active_shifts']) ?> Shifts</td>
                            <td>
                                <span class="status-pill active">
                                    <span class="status-dot"></span> Active
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Coverage Table Pagination -->
    <?php if (!empty($coverage)): ?>
        <div class="table-footer-pagination" id="covPaginationFooter">
            <div class="pagination-info" id="covPaginationInfo">
                Showing 1 to <?= min(10, count($coverage)) ?> of <?= count($coverage) ?> records
            </div>
            <div class="pagination-controls" id="covPaginationControls"></div>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Field Telemetry & Attendance Logs -->
<div class="card" style="padding: 1.5rem; overflow: hidden;">
    <div style="margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a;">Recent Attendance &amp; Shift Audit Logs</h3>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Guard Name</th>
                    <th>Badge / Code</th>
                    <th>Site</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>GPS Coords</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="logsTableBody">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 2rem;">No attendance records recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="log-row">
                            <td style="font-weight: 600;"><?= e($log['guard_name'] ?? 'Guard') ?></td>
                            <td><code><?= e($log['employee_code'] ?? 'GRD') ?></code></td>
                            <td><?= e($log['site_name'] ?? 'Unassigned Post') ?></td>
                            <td><?= e($log['check_in_at'] ? format_datetime($log['check_in_at']) : '—') ?></td>
                            <td><?= e($log['check_out_at'] ? format_datetime($log['check_out_at']) : '—') ?></td>
                            <td>
                                <?php if (!empty($log['check_in_latitude']) && !empty($log['check_in_longitude'])): ?>
                                    <span style="font-size: 0.75rem; color: #64748b;">
                                        <?= round((float)$log['check_in_latitude'], 4) ?>, <?= round((float)$log['check_in_longitude'], 4) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$log['status'] === 0): ?>
                                    <span class="status-pill active">
                                        <span class="status-dot"></span> On Duty
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: #64748b;">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Logs Table Pagination -->
    <?php if (!empty($logs)): ?>
        <div class="table-footer-pagination" id="logsPaginationFooter">
            <div class="pagination-info" id="logsPaginationInfo">
                Showing 1 to <?= min(10, count($logs)) ?> of <?= count($logs) ?> records
            </div>
            <div class="pagination-controls" id="logsPaginationControls"></div>
        </div>
    <?php endif; ?>
</div>

<script>
function handlePresetChange(preset) {
    const fromGroup = document.getElementById('fromDateGroup');
    const toGroup = document.getElementById('toDateGroup');
    const fromLabel = document.getElementById('fromDateLabel');
    const fromPicker = document.getElementById('fromDatePicker');
    const toPicker = document.getElementById('toDatePicker');

    const todayStr = '<?= date('Y-m-d') ?>';
    const yesterdayStr = '<?= date('Y-m-d', strtotime('-1 day')) ?>';

    if (preset === 'today') {
        fromGroup.style.display = 'none';
        toGroup.style.display = 'none';
        fromPicker.value = todayStr;
        toPicker.value = todayStr;
    } else if (preset === 'yesterday') {
        fromGroup.style.display = 'none';
        toGroup.style.display = 'none';
        fromPicker.value = yesterdayStr;
        toPicker.value = yesterdayStr;
    } else if (preset === 'specific') {
        fromGroup.style.display = 'inline-flex';
        toGroup.style.display = 'none';
        fromLabel.textContent = 'Date:';
        if (!fromPicker.value) fromPicker.value = todayStr;
    } else if (preset === 'custom') {
        fromGroup.style.display = 'inline-flex';
        toGroup.style.display = 'inline-flex';
        fromLabel.textContent = 'From:';
        if (!fromPicker.value) fromPicker.value = todayStr;
        if (!toPicker.value) toPicker.value = todayStr;
    } else if (preset === 'all') {
        fromGroup.style.display = 'none';
        toGroup.style.display = 'none';
    }
}

// Client-Side Pagination for Tables in Reports
function initClientPagination(rowClass, infoId, controlsId, pageSize = 10) {
    const rows = Array.from(document.querySelectorAll('.' + rowClass));
    if (rows.length === 0) return;

    let currentPage = 1;
    const total = rows.length;
    const totalPages = Math.ceil(total / pageSize) || 1;
    const infoElem = document.getElementById(infoId);
    const controlsElem = document.getElementById(controlsId);

    function render() {
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);

        rows.forEach((r, idx) => {
            r.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        if (infoElem) {
            infoElem.textContent = `Showing ${total > 0 ? start + 1 : 0} to ${end} of ${total} records`;
        }

        if (controlsElem) {
            let btnsHtml = '';

            // Prev Button
            btnsHtml += `
                <button type="button" class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="goPage_${rowClass}(${currentPage - 1})" title="Previous Page">
                    &lsaquo;
                </button>
            `;

            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }

            for (let p = startPage; p <= endPage; p++) {
                btnsHtml += `
                    <button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" onclick="goPage_${rowClass}(${p})">
                        ${p}
                    </button>
                `;
            }

            // Next Button
            btnsHtml += `
                <button type="button" class="page-btn ${currentPage === totalPages ? 'disabled' : ''} onclick="goPage_${rowClass}(${currentPage + 1})" title="Next Page">
                    &rsaquo;
                </button>
            `;

            controlsElem.innerHTML = btnsHtml;
        }
    }

    window['goPage_' + rowClass] = function(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        render();
    };

    render();
}

document.addEventListener('DOMContentLoaded', function() {
    initClientPagination('cov-row', 'covPaginationInfo', 'covPaginationControls', 10);
    initClientPagination('log-row', 'logsPaginationInfo', 'logsPaginationControls', 10);
});
</script>
</div>
