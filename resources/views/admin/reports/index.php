<?php
/**
 * Admin Operational Reports & Analytics View
 */
$stats = $attendanceStats ?? [];
$coverage = $siteCoverage ?? [];
$logs = $attendanceLogs ?? [];
?>

<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Operational Reports &amp; Analytics</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Telemetry, site coverage, and guard deployment logs from <code>secure360_v2</code></p>
</div>

<!-- Summary Metrics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="card" style="margin-bottom:0; padding: 1.25rem;">
        <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Today's Check-ins</div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
            <?= e($stats['today_checkins'] ?? 0) ?>
        </div>
        <span style="font-size: 0.75rem; color: #10b981; font-weight: 500;">Active duty logs</span>
    </div>

    <div class="card" style="margin-bottom:0; padding: 1.25rem;">
        <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Guards On Duty</div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #16a34a; margin-top: 0.25rem;">
            <?= e($stats['on_duty'] ?? 0) ?>
        </div>
        <span style="font-size: 0.75rem; color: #16a34a; font-weight: 500;">Currently on post</span>
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
        <p style="font-size: 0.8125rem; color: #64748b;">Current guard coverage across all physical client posts</p>
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
                        <tr>
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
</div>

<!-- Recent Field Telemetry & Attendance Logs -->
<div class="card" style="padding: 1.5rem;">
    <div style="margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a;">Recent Attendance &amp; Shift Audit Logs</h3>
        <p style="font-size: 0.8125rem; color: #64748b;">GPS check-ins submitted via the Flutter Guard mobile client</p>
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
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 2rem;">No attendance records recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= e($log['guard_name'] ?? 'Guard') ?></td>
                            <td><code><?= e($log['employee_code'] ?? 'GRD') ?></code></td>
                            <td><?= e($log['site_name'] ?? 'Unassigned Post') ?></td>
                            <td><?= e($log['check_in_at'] ? date('M d, H:i', strtotime($log['check_in_at'])) : '-') ?></td>
                            <td><?= e($log['check_out_at'] ? date('M d, H:i', strtotime($log['check_out_at'])) : '-') ?></td>
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
</div>
