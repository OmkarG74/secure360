<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Core\Database;
use App\Models\GuardLiveLocation;
use App\Models\Notification;
use App\Models\Selfie;
use App\Services\NotificationService;
use PDO;

/**
 * Guard Telemetry Controller (Live Location, Selfies, Notifications)
 * Consumed by Flutter Mobile App
 */
class GuardLocationController extends Controller
{
    /**
     * Submit real-time GPS location telemetry
     * POST /api/v1/guard/location
     */
    public function submitLocation(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $body = $this->request->getBody();
        $latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
        $longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;
        $accuracy = isset($body['accuracy_meters']) ? (float)$body['accuracy_meters'] : (isset($body['accuracy']) ? (float)$body['accuracy'] : null);
        $address = (string)($body['address'] ?? '');
        $assignmentId = isset($body['assignment_id']) ? (int)$body['assignment_id'] : null;
        $attendanceId = isset($body['attendance_id']) ? (int)$body['attendance_id'] : null;

        if ($latitude === null || $longitude === null) {
            $this->json(['success' => false, 'message' => 'Latitude and Longitude are required', 'status_code' => 422], 422);
            return;
        }

        // Auto-link active duty session if not explicitly passed by client
        if ($attendanceId === null) {
            $db = Database::getConnection();
            $attStmt = $db->prepare(
                "SELECT id, assignment_id FROM attendance 
                 WHERE guard_id = :guard_id AND status = 0 AND check_out_at IS NULL 
                 ORDER BY check_in_at DESC LIMIT 1"
            );
            $attStmt->execute(['guard_id' => (int)$guard['guard_id']]);
            $open = $attStmt->fetch(PDO::FETCH_ASSOC);
            if ($open) {
                $attendanceId = (int)$open['id'];
                if ($assignmentId === null && !empty($open['assignment_id'])) {
                    $assignmentId = (int)$open['assignment_id'];
                }
            }
        }

        $locationModel = new GuardLiveLocation();
        $id = $locationModel->recordLocation(
            (int)$guard['organization_id'],
            (int)$guard['guard_id'],
            $latitude,
            $longitude,
            $accuracy,
            $address,
            $assignmentId,
            $attendanceId
        );

        // -------------------------------------------------------------------------
        // Phase 2: Post Departure / Geofence Detection & Admin Alert
        // -------------------------------------------------------------------------
        try {
            $db = Database::getConnection();
            $guardId = (int)$guard['guard_id'];
            $orgId = (int)$guard['organization_id'];

            // Fetch active open attendance session with assigned site coordinates and current departure state
            $attStmt = $db->prepare(
                "SELECT att.id as attendance_id, att.assignment_id, att.site_id, att.is_outside_post,
                        s.site_name, s.zone_gate, s.latitude as site_lat, s.longitude as site_lng
                 FROM attendance att
                 JOIN sites s ON att.site_id = s.id
                 WHERE att.guard_id = :guard_id 
                   AND att.organization_id = :org_id 
                   AND att.status = 0 
                   AND att.check_out_at IS NULL
                 ORDER BY att.check_in_at DESC 
                 LIMIT 1"
            );
            $attStmt->execute(['guard_id' => $guardId, 'org_id' => $orgId]);
            $activeDuty = $attStmt->fetch(PDO::FETCH_ASSOC);

            if ($activeDuty && $activeDuty['site_lat'] !== null && $activeDuty['site_lng'] !== null) {
                $siteLat = (float)$activeDuty['site_lat'];
                $siteLng = (float)$activeDuty['site_lng'];
                $distanceMeters = geo_distance_meters($latitude, $longitude, $siteLat, $siteLng);
                $allowedRadius = GuardAttendanceController::DEFAULT_GEOFENCE_RADIUS_METERS; // 150m

                $isOutside = ($distanceMeters > $allowedRadius);
                $wasOutside = ((int)($activeDuty['is_outside_post'] ?? 0) === 1);
                $activeAttId = (int)$activeDuty['attendance_id'];

                if (!$wasOutside && $isOutside) {
                    // INSIDE -> OUTSIDE: Guard has departed the assigned post
                    // 1. Update attendance state to OUTSIDE
                    $upStmt = $db->prepare("UPDATE attendance SET is_outside_post = 1, updated_at = NOW() WHERE id = :id");
                    $upStmt->execute(['id' => $activeAttId]);

                    // 2. Prevent duplicate notifications (e.g. rapid parallel pings within 30 seconds)
                    $dupStmt = $db->prepare(
                        "SELECT id FROM notifications 
                         WHERE type = 'post_departure' 
                           AND entity_id = :guard_id 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND) 
                         LIMIT 1"
                    );
                    $dupStmt->execute(['guard_id' => (string)$guardId]);
                    if (!$dupStmt->fetchColumn()) {
                        $guardName = (string)($guard['name'] ?? $guard['full_name'] ?? 'Guard #' . $guardId);
                        $siteName = (string)($activeDuty['site_name'] ?? 'Assigned Site');
                        $postName = !empty($activeDuty['zone_gate']) ? (string)$activeDuty['zone_gate'] : $siteName;
                        $postId = (int)$activeDuty['site_id'];
                        $eventTime = date('Y-m-d H:i:s');

                        $alertTitle = 'Post Departure Alert';
                        $alertMsg = "{$guardName} has moved outside the assigned post at {$postName}.";

                        NotificationService::sendToAdmins(
                            $orgId,
                            $alertTitle,
                            $alertMsg,
                            'post_departure',
                            [
                                'guard_id' => $guardId,
                                'guard_name' => $guardName,
                                'site_id' => $postId,
                                'site_name' => $siteName,
                                'post_id' => $postId,
                                'post_name' => $postName,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'assigned_latitude' => $siteLat,
                                'assigned_longitude' => $siteLng,
                                'distance_from_post' => round($distanceMeters, 1),
                                'event_time' => $eventTime,
                                'type' => 'post_departure',
                                'screen' => 'guard/location',
                                'entity_id' => (string)$guardId,
                            ],
                            [
                                'priority' => 'high',
                                'entity_type' => 'guard',
                                'entity_id' => (string)$guardId,
                                'organization_id' => $orgId,
                            ]
                        );
                    }
                } elseif ($wasOutside && !$isOutside) {
                    // OUTSIDE -> INSIDE: Guard has returned inside the assigned post area
                    // Reset departure state so future departure triggers a fresh alert
                    $upStmt = $db->prepare("UPDATE attendance SET is_outside_post = 0, updated_at = NOW() WHERE id = :id");
                    $upStmt->execute(['id' => $activeAttId]);
                }
                // OUTSIDE -> OUTSIDE: Debounced, no action
                // INSIDE -> INSIDE: Normal, no action
            }
        } catch (\Throwable $e) {
            // Geofence alert failure must NEVER break telemetry logging
            error_log("[LocationController] Post departure alert detection failed: " . $e->getMessage());
        }

        $this->json([
            'success' => true,
            'message' => 'Live location telemetry recorded',
            'data' => [
                'id' => $id,
                'location_id' => $id,
                'recorded_at' => date('Y-m-d H:i:s'),
            ],
            'status_code' => 201,
        ], 201);
    }

    /**
     * Submit verification selfie
     * POST /api/v1/guard/selfie
     */
    public function submitSelfie(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $body = $this->request->getBody();
        $imagePath = (string)($body['image_path'] ?? '');
        $attendanceId = isset($body['attendance_id']) ? (int)$body['attendance_id'] : null;

        // Support direct multipart file uploads (image / photo)
        if (!empty($_FILES['image']['tmp_name']) || !empty($_FILES['photo']['tmp_name'])) {
            $file = !empty($_FILES['image']['tmp_name']) ? $_FILES['image'] : $_FILES['photo'];
            $uploadDir = dirname(__DIR__, 4) . '/public/uploads/selfies/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($file['name'] ?? 'selfie.jpg', PATHINFO_EXTENSION)) ?: 'jpg';
            $fileName = 'selfie_' . (int)$guard['guard_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                $imagePath = 'uploads/selfies/' . $fileName;
            }
        } elseif (!empty($body['image_base64'])) {
            $base64 = $body['image_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64)) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            $decoded = base64_decode($base64);
            if ($decoded !== false) {
                $uploadDir = dirname(__DIR__, 4) . '/public/uploads/selfies/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $fileName = 'selfie_' . (int)$guard['guard_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
                file_put_contents($uploadDir . $fileName, $decoded);
                $imagePath = 'uploads/selfies/' . $fileName;
            }
        }

        if (empty($imagePath)) {
            $this->json(['success' => false, 'message' => 'image_path, image file, or image_base64 is required', 'status_code' => 422], 422);
            return;
        }

        $selfieModel = new Selfie();
        $id = $selfieModel->recordSelfie(
            (int)$guard['organization_id'],
            (int)$guard['guard_id'],
            $imagePath,
            $attendanceId
        );

        // If an attendance_id was linked and attendance row has no selfie_id, record it (never overwrite existing check-in selfie)
        if ($attendanceId && $id) {
            $db = \App\Core\Database::getConnection();
            $upStmt = $db->prepare("UPDATE attendance SET selfie_id = :selfie_id WHERE id = :att_id AND guard_id = :guard_id AND selfie_id IS NULL");
            $upStmt->execute([
                'selfie_id' => $id,
                'att_id' => $attendanceId,
                'guard_id' => (int)$guard['guard_id'],
            ]);
        }

        $this->json([
            'success' => true,
            'message' => 'Selfie verification recorded',
            'data' => [
                'selfie_id' => $id,
                'image_path' => $imagePath,
            ],
            'status_code' => 201,
        ], 201);
    }

    /**
     * Get guard notifications
     * GET /api/v1/guard/notifications
     */
    public function notifications(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $notificationModel = new Notification();
        $notifications = $notificationModel->findByUser((int)$guard['user_id']);

        $this->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => [
                'notifications' => $notifications,
            ],
            'status_code' => 200,
        ]);
    }
}
