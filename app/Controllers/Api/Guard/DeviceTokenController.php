<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\UserDeviceToken;

/**
 * Mobile Guard Device Token Controller
 * Handles FCM device registration & de-registration for push notifications
 */
class DeviceTokenController extends Controller
{
    private UserDeviceToken $deviceTokenModel;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        parent::__construct($request, $response);
        $this->deviceTokenModel = new UserDeviceToken();
    }

    /**
     * Register or update an FCM device token for the authenticated guard
     * POST /api/v1/guard/device-token
     */
    public function register(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json([
                'success' => false,
                'message' => 'Unauthorized: Guard not authenticated',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        $body = $this->request->getBody();
        $fcmToken = trim((string)($body['fcm_token'] ?? ''));
        $deviceType = strtolower(trim((string)($body['device_type'] ?? 'android')));
        $deviceName = isset($body['device_name']) ? trim((string)$body['device_name']) : null;

        if ($fcmToken === '') {
            $this->json([
                'success' => false,
                'message' => 'Validation error: fcm_token is required',
                'data' => null,
                'errors' => ['fcm_token' => 'fcm_token cannot be empty'],
                'status_code' => 422,
            ], 422);
            return;
        }

        if (!in_array($deviceType, ['android', 'ios', 'web'], true)) {
            $deviceType = 'android';
        }

        $userId = (int)($guard['user_id'] ?? 0);
        if ($userId <= 0 && isset($guard['id']) && !isset($guard['token_hash'])) {
            $userId = (int)$guard['id'];
        }
        if ($userId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid user identity',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        $tokenId = $this->deviceTokenModel->registerToken(
            $userId,
            $fcmToken,
            $deviceType,
            $deviceName
        );

        $this->json([
            'success' => true,
            'message' => 'FCM device token registered successfully',
            'data' => [
                'token_id' => $tokenId,
                'user_id' => $userId,
                'device_type' => $deviceType,
            ],
            'status_code' => 200,
        ], 200);
    }

    /**
     * Deactivate an FCM device token (e.g. on logout)
     * POST /api/v1/guard/device-token/remove
     */
    public function remove(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json([
                'success' => false,
                'message' => 'Unauthorized: Guard not authenticated',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        $body = $this->request->getBody();
        $fcmToken = trim((string)($body['fcm_token'] ?? ''));

        if ($fcmToken !== '') {
            $userId = (int)($guard['user_id'] ?? 0);
            if ($userId <= 0 && isset($guard['id']) && !isset($guard['token_hash'])) {
                $userId = (int)$guard['id'];
            }
            $this->deviceTokenModel->deactivateToken($fcmToken, $userId);
        }

        $this->json([
            'success' => true,
            'message' => 'Device token deactivated successfully',
            'data' => null,
            'status_code' => 200,
        ], 200);
    }

    /**
     * DEVELOPMENT-ONLY: Send a test FCM push notification to the authenticated guard's registered device(s)
     * POST /api/v1/guard/device-token/test
     * GET  /api/v1/guard/device-token/test
     */
    public function testNotification(): void
    {
        // 1. Strictly restrict to development environment
        $appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: 'development')));
        if ($appEnv === 'production') {
            $this->json([
                'success' => false,
                'message' => 'Forbidden: This test endpoint is disabled in production environment',
                'data' => null,
                'status_code' => 403,
            ], 403);
            return;
        }

        // 2. Validate authenticated guard identity from Bearer token
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json([
                'success' => false,
                'message' => 'Unauthorized: Guard not authenticated',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        $userId = (int)($guard['user_id'] ?? $guard['id'] ?? 0);
        if ($userId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid guard identity',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        // 3. Fetch authenticated guard's registered active device tokens
        // Strictly uses tokens stored in database for the authenticated user,
        // ignoring any arbitrary tokens from request body for security.
        $activeTokens = $this->deviceTokenModel->getActiveTokensByUser($userId);
        if (empty($activeTokens)) {
            $this->json([
                'success' => false,
                'message' => 'No active device tokens found for this guard. Please log in on the Flutter mobile app first to register your device.',
                'data' => [
                    'user_id' => $userId,
                    'guard_id' => $guard['id'] ?? null,
                    'active_devices' => 0,
                ],
                'status_code' => 404,
            ], 404);
            return;
        }

        // 4. Notification payload specifications
        $title = 'Secure360 Test Notification';
        $body = 'FCM push notification is working successfully.';
        $data = [
            'type' => 'test',
            'screen' => 'home',
            'entity_id' => '0',
        ];

        // 5. Dispatch test notification using FirebaseNotificationService
        try {
            $fcmService = new \App\Services\FirebaseNotificationService();
            $result = $fcmService->sendToUser($userId, $title, $body, $data);

            $isSuccess = ($result['sent_count'] > 0);
            $statusCode = $isSuccess ? 200 : 502;

            $this->json([
                'success' => $isSuccess,
                'message' => $isSuccess
                    ? 'Test FCM push notification sent successfully'
                    : 'Failed to deliver FCM push notification to registered device(s)',
                'data' => [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                    ],
                    'guard' => [
                        'user_id' => $userId,
                        'guard_id' => $guard['id'] ?? null,
                        'name' => $guard['name'] ?? $guard['full_name'] ?? null,
                    ],
                    'total_devices' => $result['total_devices'],
                    'sent_count' => $result['sent_count'],
                    'failed_count' => $result['failed_count'],
                ],
                'status_code' => $statusCode,
            ], $statusCode);
        } catch (\Throwable $e) {
            // Log only safe error information without exposing credentials
            error_log('[FCM Dev Test] Safe error summary: ' . $e->getMessage());

            $this->json([
                'success' => false,
                'message' => 'FCM service error during test dispatch',
                'data' => null,
                'errors' => [
                    'service' => 'Push notification dispatch failed. Check server logs for details.',
                ],
                'status_code' => 500,
            ], 500);
        }
    }
}
