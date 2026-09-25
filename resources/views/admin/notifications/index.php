<?php
/**
 * Admin Alerts & Notices Module View
 * Strictly aligned with Secure360 Admin UI/UX Design System (Guards & Reports reference)
 */

$notifications = $notifications ?? [];
$guards = $guards ?? [];
$filters = $filters ?? [];
$totalRecords = $totalRecords ?? count($notifications);
$currentPage = $currentPage ?? 1;
$pageSize = $pageSize ?? 20;
$queryParams = $queryParams ?? [];
$baseUrl = $baseUrl ?? url('/admin/notifications/manage');

$typeFilter = $filters['type'] ?? '';
$priorityFilter = $filters['priority'] ?? '';
$statusFilter = $filters['delivery_status'] ?? '';
$searchQuery = $filters['search'] ?? '';
?>

<div class="page-container">
    <!-- Page Header (Matches Guards / Reports modules) -->
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-header-title" style="margin: 0;">Alerts &amp; Notices</h1>
        </div>
    </div>

    <!-- Filter & Search Toolbar (Matches Guards toolbar-card style) -->
    <div class="toolbar-card">
        <div class="toolbar-left" style="flex: 1; flex-wrap: wrap;">
            <form method="GET" action="<?= url('/admin/notifications/manage') ?>" class="toolbar-search" style="margin: 0; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <!-- Search Input with Icon -->
                <div class="input-icon-wrapper" style="width: 280px;">
                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search notifications, guards..." value="<?= e($searchQuery) ?>" style="height: 42px; font-size: 0.8125rem;">
                </div>

                <!-- Type Filter -->
                <select name="type" class="form-select" style="width: auto; min-width: 130px; height: 42px; font-size: 0.8125rem;" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="wake_up_call" <?= $typeFilter === 'wake_up_call' ? 'selected' : '' ?>>Wake-Up Call</option>
                    <option value="attendance_checkin" <?= $typeFilter === 'attendance_checkin' ? 'selected' : '' ?>>Guard Check-In</option>
                    <option value="attendance_checkout" <?= $typeFilter === 'attendance_checkout' ? 'selected' : '' ?>>Guard Check-Out</option>
                    <option value="post_departure" <?= $typeFilter === 'post_departure' ? 'selected' : '' ?>>Post Departure</option>
                    <option value="contract_assignment" <?= $typeFilter === 'contract_assignment' ? 'selected' : '' ?>>Contract</option>
                    <option value="shift_changed" <?= $typeFilter === 'shift_changed' ? 'selected' : '' ?>>Shift</option>
                    <option value="attendance_alert" <?= $typeFilter === 'attendance_alert' ? 'selected' : '' ?>>Attendance</option>
                    <option value="admin_alert" <?= in_array($typeFilter, ['admin_alert', 'general_alert', 'emergency_alert']) ? 'selected' : '' ?>>Alert</option>
                    <option value="system_alert" <?= $typeFilter === 'system_alert' ? 'selected' : '' ?>>System</option>
                </select>

                <!-- Priority Filter -->
                <select name="priority" class="form-select" style="width: auto; min-width: 130px; height: 42px; font-size: 0.8125rem;" onchange="this.form.submit()">
                    <option value="">All Priorities</option>
                    <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="critical" <?= $priorityFilter === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>

                <!-- Status Filter -->
                <select name="delivery_status" class="form-select" style="width: auto; min-width: 130px; height: 42px; font-size: 0.8125rem;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>

                <?php if ($searchQuery || $typeFilter || $priorityFilter || $statusFilter): ?>
                    <a href="<?= url('/admin/notifications/manage') ?>" class="btn-search-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Primary Action Buttons -->
        <div class="toolbar-actions" style="display: flex; align-items: center; gap: 0.5rem;">
            <button type="button" onclick="openWakeUpModal()" class="btn btn-outline" style="height: 42px; padding: 0 1.15rem; font-size: 0.8125rem; font-weight: 600; white-space: nowrap; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; color: #dc2626; border-color: #fca5a5; background: #fff5f5;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M4 4l3 3M20 4l-3 3"/></svg>
                <span>Wake Up Guard</span>
            </button>
            <button type="button" onclick="openSendAlertModal()" class="btn btn-primary" style="height: 42px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 600; white-space: nowrap; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                <span>Send Alert</span>
            </button>
        </div>
    </div>

    <!-- Notification Table Card (Matches Guards table-card design) -->
    <div class="table-card">
        <?php if (empty($notifications)): ?>
            <!-- Standard Empty State matching Guards module -->
            <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: #94a3b8;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </div>
                <p style="font-size: 1rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No notifications found.</p>
                <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.5rem;">Broadcast alerts or assign shifts to guards to start generating notifications.</p>
                <div style="display: inline-flex; gap: 0.75rem;">
                    <button type="button" onclick="openWakeUpModal()" class="btn btn-outline" style="color: #dc2626; border-color: #fca5a5; background: #fff5f5; font-size: 0.8125rem; cursor: pointer;">
                        Wake Up Guard
                    </button>
                    <button type="button" onclick="openSendAlertModal()" class="btn btn-outline" style="color: #2563eb; border-color: #2563eb; font-size: 0.8125rem; cursor: pointer;">
                        + Send First Alert
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Notification</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Recipients</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $n): ?>
                            <?php
                            $prio = strtolower((string)($n['priority'] ?? 'normal'));
                            $status = strtolower((string)($n['delivery_status'] ?? 'sent'));
                            $type = (string)($n['type'] ?? 'system_alert');

                            // Check acknowledgement metadata for wake up calls
                            $isWakeUp = ($type === 'wake_up_call' || str_contains($type, 'wake_up') || str_contains($type, 'wakeup'));
                            $isAcknowledged = false;
                            $acknowledgedAt = null;

                            if ($isWakeUp) {
                                $isAcknowledged = \App\Models\Notification::checkRowAcknowledged($n);
                                if ($isAcknowledged) {
                                    $dataJson = [];
                                    if (!empty($n['data_json'])) {
                                        $parsed = is_string($n['data_json']) ? json_decode($n['data_json'], true) : $n['data_json'];
                                        if (is_string($parsed)) $parsed = json_decode($parsed, true);
                                        if (is_array($parsed)) $dataJson = $parsed;
                                    }
                                    $acknowledgedAt = $dataJson['acknowledged_at'] ?? null;
                                }
                            }

                            // Clean type label
                            $typeLabel = 'System';
                            if ($isWakeUp) $typeLabel = 'Wake-Up Call';
                            elseif (str_contains($type, 'checkin')) $typeLabel = 'Check-In';
                            elseif (str_contains($type, 'checkout')) $typeLabel = 'Check-Out';
                            elseif (str_contains($type, 'departure')) $typeLabel = 'Departure';
                            elseif (str_contains($type, 'contract')) $typeLabel = 'Contract';
                            elseif (str_contains($type, 'shift')) $typeLabel = 'Shift';
                            elseif (str_contains($type, 'attendance')) $typeLabel = 'Attendance';
                            elseif (str_contains($type, 'alert')) $typeLabel = 'Alert';

                            // Priority badge styling matching standard pills
                            $prioBg = '#eff6ff'; $prioColor = '#2563eb'; $prioBorder = '#bfdbfe';
                            if ($prio === 'critical' || $isWakeUp) {
                                $prioBg = '#fef2f2'; $prioColor = '#dc2626'; $prioBorder = '#fecaca';
                            } elseif ($prio === 'high') {
                                $prioBg = '#fffbeb'; $prioColor = '#d97706'; $prioBorder = '#fde68a';
                            } elseif ($prio === 'low') {
                                $prioBg = '#f8fafc'; $prioColor = '#64748b'; $prioBorder = '#e2e8f0';
                            }

                            // Payload for modal view
                            $statusDisplay = ucfirst($status);
                            if ($isWakeUp) {
                                $statusDisplay = $isAcknowledged ? 'Acknowledged at ' . format_utc_datetime($acknowledgedAt) : 'Pending Acknowledgement';
                            }

                            $detailPayload = json_encode([
                                'id' => (int)$n['id'],
                                'title' => $n['title'],
                                'message' => $n['message'],
                                'type' => $typeLabel,
                                'priority' => ucfirst($prio),
                                'recipient_name' => $n['recipient_name'] ?? 'Guard #' . $n['user_id'],
                                'recipient_code' => $n['recipient_code'] ?? '',
                                'sender_name' => $n['sender_name'] ?? 'System Automation',
                                'status' => $statusDisplay,
                                'created_at' => format_datetime($n['created_at']),
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            ?>
                            <tr>
                                <!-- Notification Title & Truncated Preview -->
                                <td style="max-width: 320px;">
                                    <div style="font-weight: 600; color: #0f172a; margin-bottom: 0.2rem; line-height: 1.35; display: flex; align-items: center; gap: 0.4rem;">
                                        <?php if ($isWakeUp): ?>
                                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #dc2626; flex-shrink: 0;" title="Urgent Wake-Up"></span>
                                        <?php endif; ?>
                                        <span><?= e($n['title']) ?></span>
                                    </div>
                                    <div style="color: #64748b; font-size: 0.8125rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3;">
                                        <?= e($n['message']) ?>
                                    </div>
                                </td>

                                <!-- Type Badge -->
                                <td>
                                    <?php if ($isWakeUp): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.6rem; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-weight: 700; border-radius: 4px; font-size: 0.75rem;">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                            Wake-Up Call
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 0.25rem 0.6rem; background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; font-weight: 600; border-radius: 4px; font-size: 0.75rem;">
                                            <?= e($typeLabel) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Priority Pill -->
                                <td>
                                    <span style="display: inline-block; padding: 0.2rem 0.6rem; background: <?= $prioBg ?>; color: <?= $prioColor ?>; border: 1px solid <?= $prioBorder ?>; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize;">
                                        <?= e($prio) ?>
                                    </span>
                                </td>

                                <!-- Recipient Two-Line Cell -->
                                <td>
                                    <div style="font-weight: 600; color: #0f172a;"><?= e($n['recipient_name'] ?? 'Guard #' . $n['user_id']) ?></div>
                                    <?php if (!empty($n['recipient_code'])): ?>
                                        <div style="font-size: 0.75rem; color: #64748b; font-family: ui-monospace, monospace;"><?= e($n['recipient_code']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge with Dot -->
                                <td>
                                    <?php if ($isWakeUp): ?>
                                        <?php if ($isAcknowledged): ?>
                                            <span class="badge-status active" title="Acknowledged at <?= format_utc_datetime($acknowledgedAt) ?>">
                                                <span class="badge-status-dot"></span>
                                                Acknowledged (<?= format_utc_time($acknowledgedAt) ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca;" title="Awaiting guard response">
                                                <span class="badge-status-dot" style="background-color: #dc2626;"></span>
                                                Pending Ack
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($status === 'sent'): ?>
                                        <span class="badge-status active">
                                            <span class="badge-status-dot"></span>
                                            Sent
                                        </span>
                                    <?php elseif ($status === 'failed'): ?>
                                        <span class="badge-status inactive">
                                            <span class="badge-status-dot"></span>
                                            Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status" style="background-color: #fffbeb; color: #d97706; border: 1px solid #fde68a;">
                                            <span class="badge-status-dot" style="background-color: #d97706;"></span>
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Created Date (Adheres to IST Global Standard) -->
                                <td style="white-space: nowrap; color: #64748b; font-size: 0.8125rem;">
                                    <?= format_datetime($n['created_at']) ?>
                                </td>

                                <!-- Actions (Matches Guards action-controls) -->
                                <td style="text-align: right;">
                                    <div class="action-controls">
                                        <button type="button" 
                                                class="btn-action-edit" 
                                                data-details='<?= $detailPayload ?>'
                                                onclick="openNotificationDetails(this)">
                                            View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Standard Reusable Pagination Component -->
            <?php App\Core\View::component('components/pagination', [
                'currentPage' => $currentPage,
                'totalRecords' => $totalRecords,
                'pageSize' => $pageSize,
                'queryParams' => $queryParams,
                'baseUrl' => $baseUrl,
            ]); ?>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================================================
     SEND ALERT MODAL (Standard Secure360 Modal Design)
     ========================================================================== -->
<div id="sendAlertModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:999; align-items:center; justify-content:center; padding: 1rem;">
    <div class="modal-container" style="background:#fff; border-radius:12px; width:100%; max-width:540px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow:hidden; max-height: 90vh; display: flex; flex-direction: column;">
        
        <!-- Modal Header -->
        <div class="modal-header" style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0;">Send Guard Alert</h3>
            </div>
            <button type="button" onclick="closeSendAlertModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8; line-height: 1;">&times;</button>
        </div>

        <!-- Modal Body (Form) -->
        <form method="POST" action="<?= url('/admin/notifications/create') ?>" style="margin:0; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:1.15rem;">
            <?= csrf_field() ?>

            <!-- Title -->
            <div class="form-group">
                <label class="form-label">
                    Title <span class="required-star">*</span>
                </label>
                <input type="text" name="title" required class="form-control" placeholder="Alert title or announcement headline">
            </div>

            <!-- Message -->
            <div class="form-group">
                <label class="form-label">
                    Message <span class="required-star">*</span>
                </label>
                <textarea name="message" rows="4" required class="form-control" placeholder="Write operational alert instructions for security guards..."></textarea>
            </div>

            <!-- Type & Priority Row -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="alert_type" class="form-select" style="height:44px;">
                        <option value="general_alert">General Alert</option>
                        <option value="emergency_alert">Emergency Alert</option>
                        <option value="attendance_alert">Attendance Alert</option>
                        <option value="shift_alert">Shift Alert</option>
                        <option value="contract_alert">Contract Alert</option>
                        <option value="system_alert">System Alert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" style="height:44px;">
                        <option value="normal" selected>Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>

            <!-- Recipients Selection -->
            <div class="form-group">
                <label class="form-label">Recipients</label>
                <select name="send_to" id="modalSendToSelect" class="form-select" style="height:44px;" onchange="handleModalRecipientChange(this.value)">
                    <option value="all">All Active Guards</option>
                    <option value="specific">Specific Guard</option>
                    <option value="multiple">Multiple Guards</option>
                </select>
            </div>

            <!-- Specific Guard Selector -->
            <div id="modalSpecificGuardDiv" style="display:none;" class="form-group">
                <label class="form-label">Select Guard</label>
                <select name="guard_id" class="form-select" style="height:44px;">
                    <option value="">-- Choose Guard --</option>
                    <?php foreach ($guards as $g): ?>
                        <option value="<?= (int)$g['guard_id'] ?>">
                            <?= e($g['full_name']) ?> (<?= e($g['employee_code'] ?? 'GRD-' . $g['guard_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Multiple Guards Selector -->
            <div id="modalMultipleGuardsDiv" style="display:none; max-height:160px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem; background:#f8fafc;">
                <div style="font-size:0.75rem; color:#64748b; margin-bottom:0.5rem; font-weight:600;">Choose guards:</div>
                <div style="display:flex; flex-direction:column; gap:0.4rem;">
                    <?php foreach ($guards as $g): ?>
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8125rem; cursor:pointer;">
                            <input type="checkbox" name="guard_ids[]" value="<?= (int)$g['guard_id'] ?>" style="accent-color:#2563eb;">
                            <span><?= e($g['full_name']) ?> <small style="color:#64748b;">(<?= e($g['employee_code']) ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Schedule -->
            <div style="padding-top:0.5rem; border-top:1px solid #f1f5f9; display:flex; align-items:center; gap:1.5rem;">
                <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.8125rem; color:#1e293b; cursor:pointer;">
                    <input type="radio" name="schedule" value="now" checked style="accent-color:#2563eb;">
                    <span>Send Now</span>
                </label>
                <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.8125rem; color:#64748b; cursor:pointer;">
                    <input type="radio" name="schedule" value="schedule" style="accent-color:#2563eb;">
                    <span>Schedule</span>
                </label>
            </div>

            <!-- Form Actions -->
            <div style="display:flex; justify-content:flex-end; gap:0.75rem; padding-top:1rem; border-top:1px solid #e2e8f0; margin-top:0.5rem;">
                <button type="button" onclick="closeSendAlertModal()" class="btn btn-outline" style="height:38px; padding:0 1.15rem; font-size:0.8125rem; cursor:pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="height:38px; padding:0 1.25rem; font-size:0.8125rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:0.4rem;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <span>Send Alert</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     NOTIFICATION DETAILS MODAL (Standard Secure360 Modal Design)
     ========================================================================== -->
<div id="detailsModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:999; align-items:center; justify-content:center; padding:1rem;">
    <div class="modal-container" style="background:#fff; border-radius:12px; width:100%; max-width:520px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); overflow:hidden;">
        
        <div class="modal-header" style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0;" id="modalDetailTitle">Notification Details</h3>
            <button type="button" onclick="closeNotificationDetails()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8; line-height:1;">&times;</button>
        </div>

        <div class="modal-body" style="padding:1.5rem; display:flex; flex-direction:column; gap:1rem; font-size:0.875rem;">
            <div>
                <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.25rem;">Title</div>
                <div id="detailHeadline" style="font-weight:700; color:#0f172a; font-size:1rem;"></div>
            </div>

            <div>
                <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.25rem;">Message</div>
                <div id="detailMessage" style="color:#334155; line-height:1.5; background:#f8fafc; padding:0.875rem; border-radius:8px; border:1px solid #e2e8f0;"></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.25rem;">Type &amp; Priority</div>
                    <div id="detailTypePriority" style="color:#0f172a; font-weight:600;"></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.25rem;">Delivery Status</div>
                    <div id="detailStatus" style="font-weight:600;"></div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.25rem;">Recipient</div>
                    <div id="detailRecipient" style="color:#0f172a;"></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.25rem;">Created Date</div>
                    <div id="detailCreated" style="color:#64748b;"></div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; padding-top:1rem; border-top:1px solid #e2e8f0; margin-top:0.5rem;">
                <button type="button" onclick="closeNotificationDetails()" class="btn btn-outline" style="height:36px; padding:0 1.25rem; font-size:0.8125rem; cursor:pointer;">
                    Close
                </button>
            </div>
        </div>

    </div>
</div>

<!-- ==========================================================================
     WAKE UP GUARD MODAL (Urgent Alert Modal Design)
     ========================================================================== -->
<div id="wakeUpModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:999; align-items:center; justify-content:center; padding: 1rem;">
    <div class="modal-container" style="background:#fff; border-radius:12px; width:100%; max-width:480px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow:hidden;">
        
        <!-- Modal Header -->
        <div class="modal-header" style="padding:1.25rem 1.5rem; border-bottom:1px solid #fee2e2; background:#fff5f5; display:flex; justify-content:space-between; align-items:center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #fee2e2; display: flex; align-items: center; justify-content: center; color: #dc2626;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M4 4l3 3M20 4l-3 3"/></svg>
                </div>
                <div>
                    <h3 style="font-size:1.125rem; font-weight:700; color:#991b1b; margin:0;">Wake Up Guard</h3>
                    <p style="margin:0; font-size:0.75rem; color:#b91c1c;">Dispatch urgent high-priority alarm</p>
                </div>
            </div>
            <button type="button" onclick="closeWakeUpModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8; line-height: 1;">&times;</button>
        </div>

        <!-- Modal Body (Form) -->
        <form method="POST" action="<?= url('/admin/notifications/wake-up') ?>" style="margin:0; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem;">
            <?= csrf_field() ?>

            <!-- Informational Banner -->
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 0.875rem; font-size: 0.8125rem; color: #991b1b; line-height: 1.45;">
                <strong>Urgent Action:</strong> This dispatches a high-priority push alert directly to the guard's mobile phone, triggering a continuous wake-up alarm sound until acknowledged.
            </div>

            <!-- Guard Selection Dropdown -->
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.875rem;">
                    Select Guard on Duty <span class="required-star" style="color: #dc2626;">*</span>
                </label>
                <select name="guard_id" id="wakeUpGuardSelect" required class="form-select" style="height:44px; width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 0.75rem; font-size: 0.875rem;">
                    <option value="">-- Choose Guard --</option>
                    <?php foreach ($guards as $g): ?>
                        <option value="<?= (int)$g['guard_id'] ?>">
                            <?= e($g['full_name']) ?> (<?= e($g['employee_code'] ?? 'GRD-' . $g['guard_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Form Actions -->
            <div style="display:flex; justify-content:flex-end; gap:0.75rem; padding-top:1rem; border-top:1px solid #e2e8f0;">
                <button type="button" onclick="closeWakeUpModal()" class="btn btn-outline" style="height:38px; padding:0 1.15rem; font-size:0.8125rem; cursor:pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn" style="height:38px; padding:0 1.25rem; font-size:0.8125rem; font-weight:600; cursor:pointer; background:#dc2626; color:#fff; border:none; border-radius:6px; display:inline-flex; align-items:center; gap:0.4rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span>Send Wake-Up Call</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSendAlertModal() {
    const modal = document.getElementById('sendAlertModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeSendAlertModal() {
    const modal = document.getElementById('sendAlertModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openWakeUpModal(guardId = null) {
    const modal = document.getElementById('wakeUpModal');
    const select = document.getElementById('wakeUpGuardSelect');
    if (select && guardId) {
        select.value = guardId;
    }
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeWakeUpModal() {
    const modal = document.getElementById('wakeUpModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function handleModalRecipientChange(val) {
    const specificDiv = document.getElementById('modalSpecificGuardDiv');
    const multipleDiv = document.getElementById('modalMultipleGuardsDiv');
    if (val === 'specific') {
        specificDiv.style.display = 'block';
        multipleDiv.style.display = 'none';
    } else if (val === 'multiple') {
        specificDiv.style.display = 'none';
        multipleDiv.style.display = 'block';
    } else {
        specificDiv.style.display = 'none';
        multipleDiv.style.display = 'none';
    }
}

function openNotificationDetails(btn) {
    try {
        const raw = btn.getAttribute('data-details');
        const data = JSON.parse(raw);
        document.getElementById('detailHeadline').innerText = data.title || '—';
        document.getElementById('detailMessage').innerText = data.message || '—';
        document.getElementById('detailTypePriority').innerText = `${data.type} (${data.priority})`;
        document.getElementById('detailRecipient').innerText = `${data.recipient_name} ${data.recipient_code ? `(${data.recipient_code})` : ''}`;
        document.getElementById('detailStatus').innerText = data.status || 'Sent';
        document.getElementById('detailCreated').innerText = data.created_at || '—';

        const modal = document.getElementById('detailsModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    } catch (e) {
        console.error('Failed to parse details payload', e);
    }
}

function closeNotificationDetails() {
    const modal = document.getElementById('detailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modals when clicking on background backdrop
window.addEventListener('click', function(e) {
    const alertModal = document.getElementById('sendAlertModal');
    const detailsModal = document.getElementById('detailsModal');
    const wakeUpModal = document.getElementById('wakeUpModal');
    if (e.target === alertModal) {
        alertModal.style.display = 'none';
    }
    if (e.target === detailsModal) {
        detailsModal.style.display = 'none';
    }
    if (e.target === wakeUpModal) {
        wakeUpModal.style.display = 'none';
    }
});
</script>
