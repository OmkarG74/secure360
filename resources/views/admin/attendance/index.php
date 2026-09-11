<?php
/**
 * Guard Attendance & Duty Logs View
 * Live data from attendance table joined with guards, users, and sites
 */
$records = $records ?? [];
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #0f172a;">Guard Attendance &amp; Duty Telemetry</h1>
        <p style="font-size: 0.875rem; color: #64748b;">Live feed submitted via Flutter Mobile REST APIs into <code>attendance</code> table.</p>
    </div>
    <span style="font-size: 0.8125rem; color: #2563eb; background: #eff6ff; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 600;">
        <?= count($records) ?> Attendance Logs
    </span>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <div style="border: 1px dashed #cbd5e1; border-radius: var(--radius-sm); padding: 3rem; text-align: center; color: #64748b;">
            <p style="font-size: 0.9375rem; font-weight: 500;">No attendance records found in the database yet.</p>
            <p style="font-size: 0.8125rem; margin-top: 0.5rem; color: #94a3b8;">
                When guards check in through the Flutter Mobile App, their live logs, GPS coordinates, and selfies will populate here.
            </p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569;">
                        <th style="padding: 0.75rem 1rem;">Guard Name</th>
                        <th style="padding: 0.75rem 1rem;">Site</th>
                        <th style="padding: 0.75rem 1rem;">Check-in Time</th>
                        <th style="padding: 0.75rem 1rem;">Check-out Time</th>
                        <th style="padding: 0.75rem 1rem;">GPS Location</th>
                        <th style="padding: 0.75rem 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1rem; font-weight: 600; color: #0f172a;">
                                <?= e($r['guard_name']) ?><br>
                                <span style="font-size: 0.75rem; color: #94a3b8;"><?= e($r['guard_badge'] ?? '') ?></span>
                            </td>
                            <td style="padding: 1rem; font-weight: 500; color: #0f172a;">
                                <?= e($r['site_name'] ?? 'Unassigned Site') ?>
                            </td>
                            <td style="padding: 1rem; color: #10b981; font-weight: 500;">
                                <?= e($r['check_in_at']) ?>
                            </td>
                            <td style="padding: 1rem; color: #64748b;">
                                <?= e($r['check_out_at'] ?? 'Currently on duty') ?>
                            </td>
                            <td style="padding: 1rem; font-size: 0.8125rem; color: #475569;">
                                <?php if (!empty($r['check_in_latitude'])): ?>
                                    Lat: <?= e($r['check_in_latitude']) ?><br>Lng: <?= e($r['check_in_longitude']) ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">No GPS</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem;">
                                <span style="display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: <?= (int)$r['status'] === 0 ? '#ecfdf5; color: #10b981;' : ((int)$r['status'] === 1 ? '#eff6ff; color: #2563eb;' : '#fef2f2; color: #ef4444;') ?>">
                                    <?= (int)$r['status'] === 0 ? 'On Duty' : ((int)$r['status'] === 1 ? 'Completed' : 'Cancelled') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
