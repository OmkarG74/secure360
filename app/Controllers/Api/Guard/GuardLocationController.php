<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Models\GuardLiveLocation;
use App\Models\Notification;
use App\Models\Selfie;

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
            $db = \App\Core\Database::getConnection();
            $attStmt = $db->prepare(
                "SELECT id, assignment_id FROM attendance 
                 WHERE guard_id = :guard_id AND status = 0 AND check_out_at IS NULL 
                 ORDER BY check_in_at DESC LIMIT 1"
            );
            $attStmt->execute(['guard_id' => (int)$guard['guard_id']]);
            $open = $attStmt->fetch(\PDO::FETCH_ASSOC);
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

        // If an attendance_id was linked, update the attendance row's selfie_id
        if ($attendanceId && $id) {
            $db = \App\Core\Database::getConnection();
            $upStmt = $db->prepare("UPDATE attendance SET selfie_id = :selfie_id WHERE id = :att_id AND guard_id = :guard_id");
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
