<?php
/**
 * Secure360 Admin - Attendance Record Details
 */

$record = $record ?? [];
$checkinSelfie = $checkinSelfie ?? null;
$checkoutSelfie = $checkoutSelfie ?? null;
$durationFormatted = $durationFormatted ?? 'Not Available';
$isDurationActive = $isDurationActive ?? false;

// Guard Details
$guardName = $record['guard_name'] ?? 'Guard';
$guardBadge = $record['guard_badge'] ?? 'Not Available';
$guardPhone = !empty($record['guard_phone']) ? $record['guard_phone'] : 'Not Available';
$guardEmail = !empty($record['guard_email']) ? $record['guard_email'] : 'Not Available';
$guardStatusInt = isset($record['guard_status']) ? (int)$record['guard_status'] : 0;
$guardStatusLabel = $guardStatusInt === 0 ? 'Active' : 'Inactive';

// Site & Duty Context
$siteName = $record['site_name'] ?? 'Unassigned Site';
$siteCode = $record['site_code'] ?? '—';
$customerName = $record['customer_name'] ?? 'Not Available';
$clientCode = $record['client_code'] ?? '—';
$contractCode = $record['contract_code'] ?? 'Not Available';
$contractNotes = $record['contract_notes'] ?? '';
$zoneGate = !empty($record['zone_gate']) ? $record['zone_gate'] : 'Not Available';
$siteAddress = !empty($record['site_address']) ? $record['site_address'] : 'Not Available';
$shiftName = $record['shift_name'] ?? 'Standard Shift';
$shiftStart = !empty($record['shift_start']) ? format_time($record['shift_start']) : 'Not Available';
$shiftEnd = !empty($record['shift_end']) ? format_time($record['shift_end']) : 'Not Available';
$shiftTiming = ($shiftStart !== 'Not Available' && $shiftEnd !== 'Not Available') ? " ({$shiftStart} - {$shiftEnd})" : '';
$shiftDisplay = e($shiftName) . $shiftTiming;
$dutyDate = !empty($record['check_in_at']) ? format_date($record['check_in_at']) : 'Not Available';

// Attendance Status
$attStatusInt = (int)($record['status'] ?? 0);
$attStatusLabel = $attStatusInt === 0 ? 'On Duty' : ($attStatusInt === 1 ? 'Completed' : 'Cancelled');
$attStatusClass = $attStatusInt === 0 ? 'badge-duty' : ($attStatusInt === 1 ? 'badge-completed' : 'badge-cancelled');

// Timestamps
$checkInFormatted = !empty($record['check_in_at']) ? format_datetime($record['check_in_at']) : 'Not Available';
$checkOutFormatted = !empty($record['check_out_at']) ? format_datetime($record['check_out_at']) : ($attStatusInt === 0 ? 'Currently on duty' : 'Not Available');

// GPS coordinates
$checkinGps = ($record['check_in_latitude'] !== null && $record['check_in_longitude'] !== null)
    ? number_format((float)$record['check_in_latitude'], 5) . ', ' . number_format((float)$record['check_in_longitude'], 5)
    : 'Not Available';

$checkoutGps = ($record['check_out_latitude'] !== null && $record['check_out_longitude'] !== null)
    ? number_format((float)$record['check_out_latitude'], 5) . ', ' . number_format((float)$record['check_out_longitude'], 5)
    : 'Not Available';

// Selfie capture timestamps
$checkinCapturedTime = !empty($checkinSelfie['captured_at']) 
    ? format_datetime($checkinSelfie['captured_at']) 
    : (!empty($record['check_in_at']) ? format_datetime($record['check_in_at']) : 'Not Available');

$checkoutCapturedTime = !empty($checkoutSelfie['captured_at']) 
    ? format_datetime($checkoutSelfie['captured_at']) 
    : (!empty($record['check_out_at']) ? format_datetime($record['check_out_at']) : 'Not Available');
?>

<style>
/* ==========================================================================
   Secure360 Attendance Details Redesign - Layout & Styling
   ========================================================================== */
.att-details-wrapper {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    width: 100%;
    padding-bottom: 1rem;
}

/* 1. Header & Navigation */
.nav-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #64748b;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s ease;
    width: fit-content;
}
.nav-back-link:hover {
    color: #0f172a;
}
.nav-back-link svg {
    width: 16px;
    height: 16px;
}

.details-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding-bottom: 0.25rem;
}

.details-heading-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.details-page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin: 0;
    line-height: 1.25;
}

.details-meta-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.8125rem;
    color: #64748b;
}

.meta-record-tag {
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #1e293b;
    background: #f1f5f9;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.meta-separator {
    color: #cbd5e1;
}

.meta-text-strong {
    font-weight: 600;
    color: #1e293b;
}

/* Status Pill Badges */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.badge-duty {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.badge-completed {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}
.badge-cancelled {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.badge-active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.badge-inactive {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

/* 2. Top Information Cards Grid (3 Columns on Desktop) */
.top-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    align-items: stretch;
}

@media (max-width: 1024px) {
    .top-info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .top-info-grid {
        grid-template-columns: 1fr;
    }
}

.info-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.info-card-header {
    padding: 0.875rem 1.125rem;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 48px;
}

.info-card-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.info-card-icon {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.info-card-body {
    padding: 1rem 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    flex: 1;
}

.field-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.8125rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid #f8fafc;
}
.field-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.field-label {
    color: #64748b;
    font-weight: 500;
    flex-shrink: 0;
}

.field-value {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
    word-break: break-word;
    max-width: 62%;
}

.field-mono {
    font-family: ui-monospace, monospace;
    font-size: 0.75rem;
}

/* 3. Selfie Records Section */
.selfie-records-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.selfie-records-header {
    padding: 0.875rem 1.125rem;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.selfie-records-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.selfie-cards-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 1.125rem;
}

@media (max-width: 768px) {
    .selfie-cards-grid {
        grid-template-columns: 1fr;
    }
}

.selfie-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.875rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.selfie-box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid #e2e8f0;
}

.selfie-box-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.selfie-tag {
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    background: #e2e8f0;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
}

/* Image Container */
.selfie-media-container {
    width: 100%;
    height: 220px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.selfie-media-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    cursor: pointer;
    transition: transform 0.15s ease;
}
.selfie-media-img:hover {
    transform: scale(1.02);
}

.selfie-zoom-hint {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(15, 23, 42, 0.75);
    color: #ffffff;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    pointer-events: none;
    backdrop-filter: blur(2px);
}

.selfie-empty-view {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    text-align: center;
    color: #94a3b8;
}

.selfie-empty-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1px dashed #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    color: #94a3b8;
}

.selfie-empty-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #64748b;
}

.selfie-empty-sub {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.2rem;
}

.selfie-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8125rem;
    padding-top: 0.15rem;
}

.selfie-footer-label {
    color: #64748b;
    font-weight: 500;
}

.selfie-footer-val {
    color: #0f172a;
    font-weight: 600;
}

/* Lightbox Modal */
.lightbox-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.lightbox-modal.active {
    display: flex;
}

.lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.lightbox-img {
    max-width: 85vw;
    max-height: 80vh;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
    border: 2px solid #ffffff;
    object-fit: contain;
}

.lightbox-caption {
    color: #f8fafc;
    font-size: 0.875rem;
    font-weight: 600;
    margin-top: 0.75rem;
    text-align: center;
}

.lightbox-close-btn {
    position: absolute;
    top: -38px;
    right: 0;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: #ffffff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}

.lightbox-close-btn:hover {
    background: rgba(255, 255, 255, 0.35);
}
</style>

<div class="att-details-wrapper">
    <!-- Navigation Back Link -->
    <div>
        <a href="<?= url('/admin/attendance') ?>" class="nav-back-link">
            <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            <span>Back to Attendance</span>
        </a>
    </div>

    <!-- Clean Horizontal Header -->
    <div class="details-page-header">
        <div class="details-heading-group">
            <h1 class="details-page-title">Attendance Details</h1>
            <div class="details-meta-line">
                <span class="meta-record-tag">Record #<?= (int)$record['id'] ?></span>
                <span class="meta-separator">•</span>
                <span class="meta-text-strong"><?= e($guardName) ?> (<?= e($guardBadge) ?>)</span>
                <span class="meta-separator">•</span>
                <span>Duty Date: <strong class="meta-text-strong"><?= e($dutyDate) ?></strong></span>
            </div>
        </div>

        <div>
            <span class="status-pill <?= $attStatusClass ?>">
                <span class="status-dot"></span>
                <?= e($attStatusLabel) ?>
            </span>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <!-- 3-Column Top Information Grid -->
    <div class="top-info-grid">
        <!-- 1. Guard Details Card -->
        <div class="info-card">
            <div class="info-card-header">
                <h2 class="info-card-title">
                    <span class="info-card-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </span>
                    Guard Details
                </h2>
                <span class="status-pill <?= $guardStatusInt === 0 ? 'badge-active' : 'badge-inactive' ?>">
                    <?= e($guardStatusLabel) ?>
                </span>
            </div>
            <div class="info-card-body">
                <div class="field-row">
                    <span class="field-label">Guard Name</span>
                    <span class="field-value"><?= e($guardName) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Badge / Employee ID</span>
                    <span class="field-value field-mono"><?= e($guardBadge) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Phone</span>
                    <span class="field-value"><?= e($guardPhone) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Email</span>
                    <span class="field-value"><?= e($guardEmail) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Guard Status</span>
                    <span class="field-value"><?= e($guardStatusLabel) ?></span>
                </div>
            </div>
        </div>

        <!-- 2. Site & Duty Details Card -->
        <div class="info-card">
            <div class="info-card-header">
                <h2 class="info-card-title">
                    <span class="info-card-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </span>
                    Site &amp; Duty Details
                </h2>
            </div>
            <div class="info-card-body">
                <div class="field-row">
                    <span class="field-label">Site Name</span>
                    <span class="field-value"><?= e($siteName) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Client</span>
                    <span class="field-value"><?= e($customerName) ?> <?= $clientCode !== '—' ? '(' . e($clientCode) . ')' : '' ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Contract</span>
                    <span class="field-value"><?= e($contractCode) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Zone / Gate</span>
                    <span class="field-value"><?= e($zoneGate) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Site Address</span>
                    <span class="field-value"><?= e($siteAddress) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Shift</span>
                    <span class="field-value"><?= $shiftDisplay ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Duty Date</span>
                    <span class="field-value"><?= e($dutyDate) ?></span>
                </div>
            </div>
        </div>

        <!-- 3. Attendance Information Card -->
        <div class="info-card">
            <div class="info-card-header">
                <h2 class="info-card-title">
                    <span class="info-card-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    Attendance Information
                </h2>
                <span class="status-pill <?= $attStatusClass ?>">
                    <span class="status-dot"></span>
                    <?= e($attStatusLabel) ?>
                </span>
            </div>
            <div class="info-card-body">
                <div class="field-row">
                    <span class="field-label">Check In Time</span>
                    <span class="field-value"><?= e($checkInFormatted) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Check Out Time</span>
                    <span class="field-value"><?= e($checkOutFormatted) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Duty Duration</span>
                    <span class="field-value" style="color: #2563eb; font-weight: 700;"><?= e($durationFormatted) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Status</span>
                    <span class="field-value"><?= e($attStatusLabel) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Check-In GPS</span>
                    <span class="field-value field-mono"><?= e($checkinGps) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Check-Out GPS</span>
                    <span class="field-value field-mono"><?= e($checkoutGps) ?></span>
                </div>
                <?php if (!empty($record['check_in_address'])): ?>
                <div class="field-row">
                    <span class="field-label">Check-In Address</span>
                    <span class="field-value"><?= e($record['check_in_address']) ?></span>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <!-- ====================================================================
         SECTION: SELFIE RECORDS (2 EQUAL-WIDTH COLUMNS)
         ==================================================================== -->
    <div class="selfie-records-card">
        <div class="selfie-records-header">
            <h2 class="selfie-records-title">
                <span class="info-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                Selfie Records
            </h2>
        </div>

        <div class="selfie-cards-grid">
            <!-- 1. Check-In Selfie Card -->
            <div class="selfie-box">
                <div class="selfie-box-header">
                    <span class="selfie-box-title">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        Check-In Selfie
                    </span>
                    <span class="selfie-tag"><?= e($checkinCapturedTime) ?></span>
                </div>

                <div class="selfie-media-container">
                    <?php if (!empty($checkinSelfie['image_path'])): ?>
                        <?php $ciImgUrl = url($checkinSelfie['image_path']); ?>
                        <img src="<?= $ciImgUrl ?>" 
                             alt="Check-in selfie of <?= e($guardName) ?>" 
                             class="selfie-media-img"
                             onclick="openLightbox('<?= $ciImgUrl ?>', 'Check-In Selfie - <?= e($guardName) ?> (Record #<?= (int)$record['id'] ?>)')">
                        <div class="selfie-zoom-hint">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                            Enlarge
                        </div>
                    <?php else: ?>
                        <div class="selfie-empty-view">
                            <div class="selfie-empty-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="selfie-empty-title">No check-in selfie available</div>
                            <div class="selfie-empty-sub">No photograph was recorded during check-in.</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="selfie-footer-row">
                    <span class="selfie-footer-label">Capture Timestamp</span>
                    <span class="selfie-footer-val"><?= e($checkinCapturedTime) ?></span>
                </div>
            </div>

            <!-- 2. Check-Out Selfie Card -->
            <div class="selfie-box">
                <div class="selfie-box-header">
                    <span class="selfie-box-title">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Check-Out Selfie
                    </span>
                    <span class="selfie-tag"><?= e($checkoutCapturedTime) ?></span>
                </div>

                <div class="selfie-media-container">
                    <?php if (!empty($checkoutSelfie['image_path'])): ?>
                        <?php $coImgUrl = url($checkoutSelfie['image_path']); ?>
                        <img src="<?= $coImgUrl ?>" 
                             alt="Check-out selfie of <?= e($guardName) ?>" 
                             class="selfie-media-img"
                             onclick="openLightbox('<?= $coImgUrl ?>', 'Check-Out Selfie - <?= e($guardName) ?> (Record #<?= (int)$record['id'] ?>)')">
                        <div class="selfie-zoom-hint">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                            Enlarge
                        </div>
                    <?php else: ?>
                        <div class="selfie-empty-view">
                            <div class="selfie-empty-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="selfie-empty-title">No check-out selfie available</div>
                            <div class="selfie-empty-sub"><?= $attStatusInt === 0 ? 'Guard is currently on duty.' : 'No check-out photo recorded.' ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="selfie-footer-row">
                    <span class="selfie-footer-label">Capture Timestamp</span>
                    <span class="selfie-footer-val"><?= e($checkoutCapturedTime) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================
     LIGHTBOX MODAL FOR FULL-SIZE SELFIE INSPECTION
     ==================================================================== -->
<div id="selfieLightbox" class="lightbox-modal" onclick="handleLightboxClick(event)">
    <div class="lightbox-content">
        <button type="button" class="lightbox-close-btn" onclick="closeLightbox()" title="Close (Esc)">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img id="lightboxImg" src="" alt="Enlarged Selfie" class="lightbox-img">
        <div id="lightboxCaption" class="lightbox-caption"></div>
    </div>
</div>

<script>
function openLightbox(src, caption) {
    const modal = document.getElementById('selfieLightbox');
    const img = document.getElementById('lightboxImg');
    const cap = document.getElementById('lightboxCaption');

    if (!modal || !img) return;

    img.src = src;
    cap.textContent = caption || '';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const modal = document.getElementById('selfieLightbox');
    if (!modal) return;

    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function handleLightboxClick(e) {
    if (e.target.id === 'selfieLightbox') {
        closeLightbox();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});
</script>
