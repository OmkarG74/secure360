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
        $accuracy = isset($body['accuracy_meters']) ? (float)$body['accuracy_meters'] : null;
        $address = (string)($body['address'] ?? '');
        $assignmentId = isset($body['assignment_id']) ? (int)$body['assignment_id'] : null;
        $attendanceId = isset($body['attendance_id']) ? (int)$body['attendance_id'] : null;

        if ($latitude === null || $longitude === null) {
            $this->json(['success' => false, 'message' => 'Latitude and Longitude are required', 'status_code' => 422], 422);
            return;
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

        if (empty($imagePath)) {
            $this->json(['success' => false, 'message' => 'image_path is required', 'status_code' => 422], 422);
            return;
        }

        $selfieModel = new Selfie();
        $id = $selfieModel->recordSelfie(
            (int)$guard['organization_id'],
            (int)$guard['guard_id'],
            $imagePath,
            $attendanceId
        );

        $this->json([
            'success' => true,
            'message' => 'Selfie verification recorded',
            'data' => [
                'selfie_id' => $id,
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
