<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Attendance;
use App\Services\NotificationService;
use PDO;

/**
 * Guard Attendance API Controller
 * Consumed by Flutter Mobile App for check-in, check-out, and duty logs
 */
class GuardAttendanceController extends Controller
{
    /**
     * Application-level default geofence radius in meters (150m)
     * Covers guard post boundary plus normal mobile GPS jitter
     */
    public const DEFAULT_GEOFENCE_RADIUS_METERS = 150.0;

    /**
     * Mark check-in
     * POST /api/v1/guard/attendance/check-in
     */
    public function checkIn(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $orgId = (int)$guard['organization_id'];

        $body = $this->request->getBody();
        $siteId = isset($body['site_id']) ? (int)$body['site_id'] : null;
        $assignmentId = isset($body['assignment_id']) ? (int)$body['assignment_id'] : null;
        $latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
        $longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;
        $address = (string)($body['address'] ?? '');
        $notes = (string)($body['notes'] ?? '');
        $selfieId = isset($body['selfie_id']) ? (int)$body['selfie_id'] : null;

        $db = Database::getConnection();

        // 1. Prevent duplicate active check-ins
        $attendanceModel = new Attendance();
        $openAttendance = $attendanceModel->getOpenAttendance($guardId);
        if ($openAttendance) {
            $this->json([
                'success' => false,
                'message' => 'Guard already has an active open check-in. Check out first before checking in again.',
                'data' => [
                    'active_attendance_id' => $openAttendance['id'],
                    'checked_in_at' => $openAttendance['check_in_at'],
                ],
                'status_code' => 409,
            ], 409);
            return;
        }

        // 2. Fetch authenticated guard's actual assigned contract, shift, and site from database
        $params = ['guard_id' => $guardId, 'org_id' => $orgId];
        $where = "a.guard_id = :guard_id AND c.organization_id = :org_id AND a.deleted_at IS NULL";

        if (!empty($assignmentId)) {
            $where .= " AND a.id = :assignment_id";
            $params['assignment_id'] = $assignmentId;
        } elseif (!empty($siteId)) {
            $where .= " AND a.site_id = :site_id";
            $params['site_id'] = $siteId;
        }

        $assignStmt = $db->prepare(
            "SELECT a.id as assignment_id, a.site_id, a.contract_shift_id, a.status as assignment_status,
                    s.site_name, s.site_address, s.zone_gate, s.latitude as site_lat, s.longitude as site_lng, s.status as site_status, s.deleted_at as site_deleted_at,
                    cs.shift_name, cs.shift_code, cs.start_time, cs.end_time, cs.status as shift_status, cs.deleted_at as shift_deleted_at,
                    c.contract_code, c.status as contract_status, c.start_date, c.end_date, c.deleted_at as contract_deleted_at
             FROM contract_guard_assignments a
             JOIN sites s ON a.site_id = s.id
             JOIN contract_shifts cs ON a.contract_shift_id = cs.id
             JOIN contracts c ON a.contract_id = c.id
             WHERE {$where}
             ORDER BY a.status ASC
             LIMIT 1"
        );
        $assignStmt->execute($params);
        $activeAssign = $assignStmt->fetch(PDO::FETCH_ASSOC);

        if (!$activeAssign) {
            // If the guard provided a specific site_id or assignment_id that doesn't match their assignments,
            // check if they are assigned anywhere else to provide a clear 403 Forbidden vs 422 Unprocessable.
            if (!empty($siteId) || !empty($assignmentId)) {
                $anyAssign = $db->prepare(
                    "SELECT a.site_id, s.site_name 
                     FROM contract_guard_assignments a 
                     JOIN sites s ON a.site_id = s.id 
                     WHERE a.guard_id = :guard_id AND a.deleted_at IS NULL 
                     LIMIT 1"
                );
                $anyAssign->execute(['guard_id' => $guardId]);
                $otherPost = $anyAssign->fetch(PDO::FETCH_ASSOC);
                if ($otherPost) {
                    $this->json([
                        'success' => false,
                        'message' => 'You can only check into your assigned duty post.',
                        'data' => [
                            'assigned_site_id' => (int)$otherPost['site_id'],
                            'assigned_site_name' => $otherPost['site_name'],
                        ],
                        'status_code' => 403,
                    ], 403);
                    return;
                }
            }

            $this->json([
                'success' => false,
                'message' => 'No active duty assignment found for this guard.',
                'data' => null,
                'status_code' => 422,
            ], 422);
            return;
        }

        // 3. Verify assignment status, contract status, and contract date validity
        if ((int)$activeAssign['assignment_status'] !== 0) {
            $this->json([
                'success' => false,
                'message' => 'Your duty assignment to this post is currently inactive.',
                'status_code' => 422,
            ], 422);
            return;
        }

        if ((int)$activeAssign['contract_status'] !== 0 || !empty($activeAssign['contract_deleted_at'])) {
            $this->json([
                'success' => false,
                'message' => 'The service contract for this duty post is inactive or terminated.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // Contract date boundaries
        $today = date('Y-m-d');
        if (!empty($activeAssign['start_date']) && $activeAssign['start_date'] > $today) {
            $this->json([
                'success' => false,
                'message' => 'The service contract for this duty post has not commenced yet.',
                'data' => ['contract_start_date' => $activeAssign['start_date']],
                'status_code' => 422,
            ], 422);
            return;
        }

        if (!empty($activeAssign['end_date']) && $activeAssign['end_date'] < $today) {
            $this->json([
                'success' => false,
                'message' => 'The service contract for this duty post has expired.',
                'data' => ['contract_end_date' => $activeAssign['end_date']],
                'status_code' => 422,
            ], 422);
            return;
        }

        // Shift schedule status
        if ((int)$activeAssign['shift_status'] !== 0 || !empty($activeAssign['shift_deleted_at'])) {
            $this->json([
                'success' => false,
                'message' => 'The assigned shift schedule is currently inactive.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // Site status
        if ((int)$activeAssign['site_status'] !== 0 || !empty($activeAssign['site_deleted_at'])) {
            $this->json([
                'success' => false,
                'message' => 'The assigned duty post is currently inactive.',
                'status_code' => 422,
            ], 422);
            return;
        }

        $resolvedSiteId = (int)$activeAssign['site_id'];
        $resolvedAssignmentId = (int)$activeAssign['assignment_id'];

        // 4. Server-Side Shift Time Validation (supports both day and overnight shifts)
        $nowTime = $db->query("SELECT CURTIME() as db_time")->fetchColumn() ?: date('H:i:s');
        $shiftCheck = validate_shift_window(
            (string)$nowTime,
            (string)$activeAssign['start_time'],
            (string)$activeAssign['end_time']
        );

        if (!$shiftCheck['allowed']) {
            $this->json([
                'success' => false,
                'message' => $shiftCheck['message'],
                'data' => [
                    'shift_name' => $activeAssign['shift_name'],
                    'start_time' => $activeAssign['start_time'],
                    'end_time' => $activeAssign['end_time'],
                    'current_time' => $nowTime,
                    'shift_state' => $shiftCheck['state'],
                ],
                'status_code' => 422,
            ], 422);
            return;
        }

        // 5. Server-Side Assigned Site Geofence Validation
        if ($latitude === null || $longitude === null) {
            $this->json([
                'success' => false,
                'message' => 'Device GPS coordinates (latitude and longitude) are required.',
                'status_code' => 422,
            ], 422);
            return;
        }

        if ($activeAssign['site_lat'] !== null && $activeAssign['site_lng'] !== null) {
            $siteLat = (float)$activeAssign['site_lat'];
            $siteLng = (float)$activeAssign['site_lng'];
            $distanceMeters = geo_distance_meters($latitude, $longitude, $siteLat, $siteLng);

            if ($distanceMeters > self::DEFAULT_GEOFENCE_RADIUS_METERS) {
                $this->json([
                    'success' => false,
                    'message' => 'You are outside your assigned post area. Move closer to check in.',
                    'data' => [
                        'site_name' => $activeAssign['site_name'],
                        'distance_meters' => round($distanceMeters, 1),
                        'allowed_radius_meters' => self::DEFAULT_GEOFENCE_RADIUS_METERS,
                    ],
                    'status_code' => 422,
                ], 422);
                return;
            }
        }

        // 6. Front-Camera Selfie Verification
        if (empty($selfieId)) {
            $this->json([
                'success' => false,
                'message' => 'A front-camera verification selfie is required before checking in.',
                'status_code' => 422,
            ], 422);
            return;
        }

        $selfieStmt = $db->prepare("SELECT id FROM selfies WHERE id = :id AND guard_id = :guard_id AND organization_id = :org_id LIMIT 1");
        $selfieStmt->execute(['id' => $selfieId, 'guard_id' => $guardId, 'org_id' => $orgId]);
        if (!$selfieStmt->fetchColumn()) {
            $this->json([
                'success' => false,
                'message' => 'Verification selfie was not found or is invalid.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // 7. Record check-in
        $stmt = $db->prepare(
            "INSERT INTO attendance 
             (organization_id, guard_id, assignment_id, site_id, check_in_at, 
              check_in_latitude, check_in_longitude, check_in_address, selfie_id, status, notes, created_at)
             VALUES 
             (:org_id, :guard_id, :assignment_id, :site_id, NOW(), 
              :lat, :lng, :addr, :selfie_id, 0, :notes, NOW())"
        );
        $stmt->execute([
            'org_id' => $orgId,
            'guard_id' => $guardId,
            'assignment_id' => $resolvedAssignmentId,
            'site_id' => $resolvedSiteId,
            'lat' => $latitude,
            'lng' => $longitude,
            'addr' => $address ?: $activeAssign['site_name'],
            'selfie_id' => $selfieId,
            'notes' => $notes,
        ]);

        $attendanceId = (int)$db->lastInsertId();

        // Link selfie to attendance record
        $linkSelfie = $db->prepare("UPDATE selfies SET attendance_id = :att_id, verification_status = 'checkin' WHERE id = :selfie_id");
        $linkSelfie->execute(['att_id' => $attendanceId, 'selfie_id' => $selfieId]);

        // 8. Trigger Admin Notification (Phase 2)
        try {
            // Duplicate prevention check: only send one notification per attendance session
            $notifCheck = $db->prepare(
                "SELECT id FROM notifications 
                 WHERE type = 'attendance_checkin' AND entity_id = :att_id LIMIT 1"
            );
            $notifCheck->execute(['att_id' => (string)$attendanceId]);
            if (!$notifCheck->fetchColumn()) {
                $guardName = (string)($guard['name'] ?? $guard['full_name'] ?? 'Guard #' . $guardId);
                $siteName = (string)($activeAssign['site_name'] ?? 'Assigned Site');
                $postName = !empty($activeAssign['zone_gate']) ? (string)$activeAssign['zone_gate'] : 'Main Post';
                $postId = $resolvedSiteId;
                $checkinTime = date('Y-m-d H:i:s');

                $checkinMsg = "{$guardName} checked in at {$postName}, {$siteName}.";

                NotificationService::sendToAdmins(
                    $orgId,
                    'Guard Check-In',
                    $checkinMsg,
                    'attendance_checkin',
                    [
                        'guard_id' => $guardId,
                        'guard_name' => $guardName,
                        'attendance_id' => $attendanceId,
                        'site_id' => $resolvedSiteId,
                        'site_name' => $siteName,
                        'post_id' => $postId,
                        'post_name' => $postName,
                        'event_time' => $checkinTime,
                        'type' => 'attendance_checkin',
                        'screen' => 'attendance/details',
                        'entity_id' => (string)$attendanceId,
                    ],
                    [
                        'priority' => 'normal',
                        'entity_type' => 'attendance',
                        'entity_id' => (string)$attendanceId,
                        'organization_id' => $orgId,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Notification failure must NEVER fail or rollback the attendance transaction
            error_log("[Attendance] Check-in notification dispatch failed: " . $e->getMessage());
        }

        $this->json([
            'success' => true,
            'message' => 'Attendance check-in recorded successfully',
            'data' => [
                'attendance_id' => $attendanceId,
                'guard_id' => $guardId,
                'site_id' => $resolvedSiteId,
                'site_name' => $activeAssign['site_name'],
                'selfie_id' => $selfieId,
                'check_in_at' => date('Y-m-d H:i:s'),
                'status' => 'checked_in',
            ],
            'status_code' => 201,
        ], 201);
    }

    /**
     * Mark check-out
     * POST /api/v1/guard/attendance/check-out
     */
    public function checkOut(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $orgId = (int)$guard['organization_id'];
        $body = $this->request->getBody();

        $db = Database::getConnection();

        // 1. Locate open attendance for this authenticated guard
        $stmt = $db->prepare(
            "SELECT att.*, s.site_name, s.zone_gate, s.latitude as site_lat, s.longitude as site_lng
             FROM attendance att
             LEFT JOIN sites s ON att.site_id = s.id
             WHERE att.guard_id = :guard_id 
               AND att.organization_id = :org_id 
               AND att.status = 0 
               AND att.check_out_at IS NULL
             ORDER BY att.check_in_at DESC
             LIMIT 1"
        );
        $stmt->execute(['guard_id' => $guardId, 'org_id' => $orgId]);
        $openAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$openAttendance) {
            $this->json([
                'success' => false,
                'message' => 'No active open check-in found to check out from.',
                'data' => null,
                'status_code' => 404,
            ], 404);
            return;
        }

        $attendanceId = (int)$openAttendance['id'];

        // If client submitted an explicit attendance_id, verify it belongs to this guard
        if (!empty($body['attendance_id']) && (int)$body['attendance_id'] !== $attendanceId) {
            $this->json([
                'success' => false,
                'message' => 'Cannot check out attendance record belonging to another session or guard.',
                'status_code' => 403,
            ], 403);
            return;
        }

        $latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
        $longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;
        $address = (string)($body['address'] ?? '');
        $notes = (string)($body['notes'] ?? '');
        $checkoutSelfieId = isset($body['selfie_id']) ? (int)$body['selfie_id'] : null;

        // 2. Fresh Checkout Selfie Validation (cannot reuse check-in selfie)
        if (empty($checkoutSelfieId)) {
            $this->json([
                'success' => false,
                'message' => 'A fresh checkout selfie is required. Please capture a new selfie to check out.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // Verify selfie exists and belongs to this guard
        $selfieStmt = $db->prepare("SELECT id, image_path FROM selfies WHERE id = :id AND guard_id = :guard_id AND organization_id = :org_id LIMIT 1");
        $selfieStmt->execute(['id' => $checkoutSelfieId, 'guard_id' => $guardId, 'org_id' => $orgId]);
        $checkoutSelfie = $selfieStmt->fetch(PDO::FETCH_ASSOC);
        if (!$checkoutSelfie) {
            $this->json([
                'success' => false,
                'message' => 'Checkout selfie record not found or invalid.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // Identify check-in selfie:
        // Priority 1: Selfie explicitly marked as 'checkin' in selfies table for this attendance session
        // Priority 2: Original attendance.selfie_id
        $checkinSelfieId = null;
        $checkinImagePath = null;

        $ciStmt = $db->prepare(
            "SELECT id, image_path 
             FROM selfies 
             WHERE attendance_id = :att_id AND verification_status = 'checkin' 
             ORDER BY id ASC 
             LIMIT 1"
        );
        $ciStmt->execute(['att_id' => $attendanceId]);
        $ciRow = $ciStmt->fetch(PDO::FETCH_ASSOC);

        if ($ciRow) {
            $checkinSelfieId = (int)$ciRow['id'];
            $checkinImagePath = (string)$ciRow['image_path'];
            // If attendance.selfie_id was inadvertently overwritten previously, repair it back to true check-in selfie
            if (!empty($openAttendance['selfie_id']) && (int)$openAttendance['selfie_id'] !== $checkinSelfieId) {
                $repairStmt = $db->prepare("UPDATE attendance SET selfie_id = :ci_id WHERE id = :att_id");
                $repairStmt->execute(['ci_id' => $checkinSelfieId, 'att_id' => $attendanceId]);
            }
        } elseif (!empty($openAttendance['selfie_id'])) {
            $checkinSelfieId = (int)$openAttendance['selfie_id'];
            $ciPathStmt = $db->prepare("SELECT image_path FROM selfies WHERE id = :id LIMIT 1");
            $ciPathStmt->execute(['id' => $checkinSelfieId]);
            $checkinImagePath = (string)($ciPathStmt->fetchColumn() ?: '');
        }

        // Prevent reusing check-in selfie:
        // 1. Must not reuse check-in selfie ID
        if (!empty($checkinSelfieId) && $checkinSelfieId === $checkoutSelfieId) {
            $this->json([
                'success' => false,
                'message' => 'The checkout selfie must not reuse the check-in selfie. Please capture a newly taken photo.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // 2. Must not reuse check-in selfie image file/path
        if (!empty($checkinImagePath) && !empty($checkoutSelfie['image_path']) && $checkinImagePath === $checkoutSelfie['image_path']) {
            $this->json([
                'success' => false,
                'message' => 'The checkout selfie must not reuse the check-in selfie. Please capture a newly taken photo.',
                'status_code' => 422,
            ], 422);
            return;
        }

        // 3. Server-Side Checkout Location Validation (must be within assigned site geofence)
        if ($latitude === null || $longitude === null) {
            $this->json([
                'success' => false,
                'message' => 'Current device GPS coordinates are required to verify checkout.',
                'status_code' => 422,
            ], 422);
            return;
        }

        if ($openAttendance['site_lat'] !== null && $openAttendance['site_lng'] !== null) {
            $siteLat = (float)$openAttendance['site_lat'];
            $siteLng = (float)$openAttendance['site_lng'];
            $distanceMeters = geo_distance_meters($latitude, $longitude, $siteLat, $siteLng);

            if ($distanceMeters > self::DEFAULT_GEOFENCE_RADIUS_METERS) {
                $this->json([
                    'success' => false,
                    'message' => 'You are outside your assigned post area. Return to the assigned post before checking out.',
                    'data' => [
                        'site_name' => $openAttendance['site_name'],
                        'distance_meters' => round($distanceMeters, 1),
                        'allowed_radius_meters' => self::DEFAULT_GEOFENCE_RADIUS_METERS,
                    ],
                    'status_code' => 422,
                ], 422);
                return;
            }
        }

        // 4. Update attendance record to completed status
        $updateNotes = $notes !== '' ? "Checkout: {$notes}" : '';
        $stmt = $db->prepare(
            "UPDATE attendance 
             SET check_out_at = NOW(), 
                 check_out_latitude = :lat, 
                 check_out_longitude = :lng, 
                 check_out_address = :addr, 
                 status = 1, 
                 notes = CASE 
                     WHEN :notes_chk <> '' THEN CONCAT(IFNULL(notes, ''), ' | ', :notes_val) 
                     ELSE notes 
                 END,
                 updated_at = NOW()
             WHERE id = :id AND guard_id = :guard_id AND organization_id = :org_id"
        );
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'addr' => $address ?: ($openAttendance['site_name'] ?? ''),
            'notes_chk' => $updateNotes,
            'notes_val' => $updateNotes,
            'id' => $attendanceId,
            'guard_id' => $guardId,
            'org_id' => $orgId,
        ]);

        // 5. Link checkout selfie
        $linkCheckout = $db->prepare(
            "UPDATE selfies 
             SET attendance_id = :att_id, verification_status = 'checkout'
             WHERE id = :selfie_id AND guard_id = :guard_id"
        );
        $linkCheckout->execute([
            'att_id' => $attendanceId,
            'selfie_id' => $checkoutSelfieId,
            'guard_id' => $guardId,
        ]);

        // 6. Trigger Admin Notification (Phase 2)
        try {
            // Duplicate prevention check: only send one checkout notification per attendance session
            $notifCheck = $db->prepare(
                "SELECT id FROM notifications 
                 WHERE type = 'attendance_checkout' AND entity_id = :att_id LIMIT 1"
            );
            $notifCheck->execute(['att_id' => (string)$attendanceId]);
            if (!$notifCheck->fetchColumn()) {
                $guardName = (string)($guard['name'] ?? $guard['full_name'] ?? 'Guard #' . $guardId);
                $siteName = (string)($openAttendance['site_name'] ?? 'Assigned Site');
                $postName = !empty($openAttendance['zone_gate']) ? (string)$openAttendance['zone_gate'] : 'Main Post';
                $postId = !empty($openAttendance['site_id']) ? (int)$openAttendance['site_id'] : null;
                $checkoutTime = date('Y-m-d H:i:s');

                $checkoutMsg = "{$guardName} checked out from {$postName}, {$siteName}.";

                NotificationService::sendToAdmins(
                    $orgId,
                    'Guard Check-Out',
                    $checkoutMsg,
                    'attendance_checkout',
                    [
                        'guard_id' => $guardId,
                        'guard_name' => $guardName,
                        'attendance_id' => $attendanceId,
                        'site_id' => $openAttendance['site_id'] ?? null,
                        'site_name' => $siteName,
                        'post_id' => $postId,
                        'post_name' => $postName,
                        'check_out_time' => $checkoutTime,
                        'event_time' => $checkoutTime,
                        'type' => 'attendance_checkout',
                        'screen' => 'attendance/details',
                        'entity_id' => (string)$attendanceId,
                    ],
                    [
                        'priority' => 'normal',
                        'entity_type' => 'attendance',
                        'entity_id' => (string)$attendanceId,
                        'organization_id' => $orgId,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Notification failure must NEVER fail or rollback the checkout transaction
            error_log("[Attendance] Checkout notification dispatch failed: " . $e->getMessage());
        }

        $this->json([
            'success' => true,
            'message' => 'Attendance check-out recorded successfully',
            'data' => [
                'attendance_id' => $attendanceId,
                'guard_id' => $guardId,
                'site_id' => $openAttendance['site_id'],
                'site_name' => $openAttendance['site_name'],
                'checkout_selfie_id' => $checkoutSelfieId,
                'check_out_at' => date('Y-m-d H:i:s'),
                'status' => 'completed',
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * View attendance history for the authenticated guard
     * GET /api/v1/guard/attendance/history
     */
    public function history(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'data' => null, 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');

        $attendanceModel = new Attendance();
        $records = $attendanceModel->getGuardHistory($guardId, $startDate, $endDate);

        $this->json([
            'success' => true,
            'message' => 'Attendance history retrieved successfully',
            'data' => [
                'records' => $records,
                'history' => $records,
            ],
            'status_code' => 200,
        ]);
    }
}
