<?php
/**
 * Create Guard Alert View
 * Strictly aligned with Secure360 Admin UI/UX Design System
 */
$guards = $guards ?? [];
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/admin/notifications/manage') ?>" class="form-back-nav">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Alerts &amp; Notices</span>
    </a>

    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title" style="margin: 0;">Create Guard Alert</h1>
            <p class="page-header-desc" style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.875rem;">
                Broadcast real-time notices, critical security alerts, or shift updates directly to guard mobile devices.
            </p>
        </div>
    </div>

    <form method="POST" action="<?= url('/admin/notifications/create') ?>">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.75rem; align-items: start;">
            
            <!-- Main Column: Alert Content & Targeting -->
            <div class="card" style="padding: 1.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div>
                        <h2 style="font-size: 1rem; font-weight: 600; color: #0f172a; margin: 0;">Alert Information</h2>
                        <p style="font-size: 0.8125rem; color: #64748b; margin: 0;">Specify headline, message body, and recipient guard targeting.</p>
                    </div>
                </div>

                <!-- Alert Title -->
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">
                        Alert Title <span class="required-star">*</span>
                    </label>
                    <input type="text" name="title" required class="form-control" placeholder="e.g. Mandatory Perimeter Check / Shift Reminder">
                </div>

                <!-- Alert Message -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">
                        Message Body <span class="required-star">*</span>
                    </label>
                    <textarea name="message" rows="5" required class="form-control" placeholder="Provide clear instructions or operational notices for guards on duty..."></textarea>
                </div>

                <!-- Recipient Targeting Radio Selection -->
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">
                        Send To <span class="required-star">*</span>
                    </label>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: #334155;">
                            <input type="radio" name="send_to" value="all" checked onchange="toggleRecipientType(this.value)" style="accent-color: #2563eb;">
                            <span>All Active Guards</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: #334155;">
                            <input type="radio" name="send_to" value="specific" onchange="toggleRecipientType(this.value)" style="accent-color: #2563eb;">
                            <span>Specific Guard</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: #334155;">
                            <input type="radio" name="send_to" value="multiple" onchange="toggleRecipientType(this.value)" style="accent-color: #2563eb;">
                            <span>Multiple Guards</span>
                        </label>
                    </div>

                    <!-- Specific Guard Dropdown -->
                    <div id="specificGuardContainer" style="display: none; margin-top: 0.75rem;">
                        <label class="form-label" style="margin-bottom: 0.35rem;">
                            Select Recipient Guard:
                        </label>
                        <select name="guard_id" class="form-select" style="height: 44px;">
                            <option value="">-- Choose a Guard --</option>
                            <?php foreach ($guards as $g): ?>
                                <option value="<?= (int)$g['guard_id'] ?>">
                                    <?= e($g['full_name']) ?> (<?= e($g['employee_code'] ?? 'GRD-' . $g['guard_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Multiple Guards Selection List -->
                    <div id="multipleGuardsContainer" style="display: none; margin-top: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; max-height: 200px; overflow-y: auto; padding: 0.75rem; background: #f8fafc;">
                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem; font-weight: 600;">Check target guards:</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem;">
                            <?php foreach ($guards as $g): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: #1e293b; background: #fff; padding: 0.4rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
                                    <input type="checkbox" name="guard_ids[]" value="<?= (int)$g['guard_id'] ?>" style="accent-color: #2563eb;">
                                    <span><?= e($g['full_name']) ?> <small style="color: #64748b;">(<?= e($g['employee_code']) ?>)</small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Settings & Dispatch Controls -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="card" style="padding: 1.5rem;">
                    <h3 style="font-size: 0.9375rem; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 1.25rem;">Parameters &amp; Priority</h3>

                    <!-- Alert Type -->
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">
                            Alert Type
                        </label>
                        <select name="alert_type" class="form-select" style="height: 44px;">
                            <option value="general_alert">General Alert</option>
                            <option value="emergency_alert">Emergency Alert (Critical)</option>
                            <option value="attendance_alert">Attendance Alert</option>
                            <option value="shift_alert">Shift Alert</option>
                            <option value="contract_alert">Contract Alert</option>
                            <option value="system_alert">System Alert</option>
                        </select>
                    </div>

                    <!-- Priority Level -->
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">
                            Priority Level
                        </label>
                        <select name="priority" class="form-select" style="height: 44px;">
                            <option value="normal" selected>Normal</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <!-- Dispatch Schedule -->
                    <div style="margin-bottom: 1.5rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                        <label class="form-label" style="margin-bottom: 0.5rem;">
                            Schedule Dispatch
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #1e293b; cursor: pointer;">
                            <input type="radio" name="schedule" value="now" checked style="accent-color: #2563eb;">
                            <span>Send Immediately</span>
                        </label>
                    </div>

                    <!-- Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 44px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 600;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            <span>Send Alert</span>
                        </button>
                        <a href="<?= url('/admin/notifications/manage') ?>" class="btn btn-outline" style="width: 100%; height: 44px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.875rem;">
                            Cancel
                        </a>
                    </div>
                </div>

                <!-- Info Box -->
                <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 10px; padding: 1rem;">
                    <div style="display: flex; gap: 0.6rem; align-items: flex-start;">
                        <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                        <div style="font-size: 0.75rem; color: #1e40af; line-height: 1.45;">
                            Alerts are securely delivered to target guard devices via Google Firebase Cloud Messaging and stored in the central audit database.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
function toggleRecipientType(type) {
    const specificBox = document.getElementById('specificGuardContainer');
    const multipleBox = document.getElementById('multipleGuardsContainer');

    if (type === 'specific') {
        specificBox.style.display = 'block';
        multipleBox.style.display = 'none';
    } else if (type === 'multiple') {
        specificBox.style.display = 'none';
        multipleBox.style.display = 'block';
    } else {
        specificBox.style.display = 'none';
        multipleBox.style.display = 'none';
    }
}
</script>
