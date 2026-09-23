<?php

declare(strict_types=1);

namespace App\Controllers\Api\Admin;

use App\Core\Controller;
use App\Models\Notification;
use App\Services\NotificationService;

/**
 * Admin Notification REST API Controller
 */
class AdminNotificationApiController extends Controller
{
    private Notification $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new Notification();
    }

    /**
     * List notifications for the authenticated admin's organization
     * GET /api/v1/admin/notifications
     */
    public function index(): void
    {
        $user = $GLOBALS['AUTH_USER'] ?? null;
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $orgId = (int)($user['organization_id'] ?? 1);
        $filters = [
            'type' => $_GET['type'] ?? null,
            'priority' => $_GET['priority'] ?? null,
            'delivery_status' => $_GET['delivery_status'] ?? null,
            'search' => $_GET['search'] ?? null,
        ];
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

        $notifications = $this->notificationModel->findForAdminHistory($orgId, $filters, $limit, $offset);
        $total = $this->notificationModel->countForAdminHistory($orgId, $filters);

        $this->json([
            'success' => true,
            'message' => 'Admin notifications retrieved successfully',
            'data' => [
                'total' => $total,
                'notifications' => $notifications,
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * Dispatch alert to guards via API
     * POST /api/v1/admin/notifications/send
     */
    public function send(): void
    {
        $user = $GLOBALS['AUTH_USER'] ?? null;
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $body = $this->request->getBody();
        $title = trim((string)($body['title'] ?? ''));
        $message = trim((string)($body['message'] ?? ''));
        $type = trim((string)($body['type'] ?? 'admin_alert'));
        $priority = trim((string)($body['priority'] ?? 'normal'));
        $target = trim((string)($body['target'] ?? 'all')); // 'specific', 'multiple', 'all'
        $guardIds = isset($body['guard_ids']) && is_array($body['guard_ids']) ? array_map('intval', $body['guard_ids']) : [];
        $guardId = isset($body['guard_id']) ? (int)$body['guard_id'] : 0;

        if ($title === '' || $message === '') {
            $this->json([
                'success' => false,
                'message' => 'Title and message are required fields',
                'status_code' => 422,
            ], 422);
            return;
        }

        $orgId = (int)($user['organization_id'] ?? 1);
        $options = [
            'priority' => $priority,
            'sender_user_id' => (int)$user['id'],
            'organization_id' => $orgId,
            'entity_type' => 'alert',
        ];

        $data = [
            'screen' => 'notifications',
            'type' => $type,
            'priority' => $priority,
        ];

        if ($target === 'specific' && $guardId > 0) {
            $res = NotificationService::sendToGuard($guardId, $title, $message, $type, $data, $options);
            $this->json([
                'success' => $res['success'],
                'message' => $res['success'] ? 'Alert dispatched to guard' : ($res['error'] ?? 'Failed to dispatch alert'),
                'data' => $res,
                'status_code' => $res['success'] ? 200 : 400,
            ], $res['success'] ? 200 : 400);
            return;
        }

        if ($target === 'multiple' && !empty($guardIds)) {
            $res = NotificationService::sendToGuards($guardIds, $title, $message, $type, $data, $options);
            $this->json([
                'success' => true,
                'message' => "Alert dispatched to {$res['successful_dispatches']} guards",
                'data' => $res,
                'status_code' => 200,
            ]);
            return;
        }

        // Default: All guards in organisation
        $res = NotificationService::sendToRole('guard', $orgId, $title, $message, $type, $data, $options);
        $this->json([
            'success' => true,
            'message' => "Alert dispatched to all active guards",
            'data' => $res,
            'status_code' => 200,
        ]);
    }

    /**
     * Dispatch an immediate Wake-Up Call to a specific Guard
     * POST /api/v1/admin/notifications/wake-up
     */
    public function wakeUp(): void
    {
        $user = $GLOBALS['AUTH_USER'] ?? null;
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $body = $this->request->getBody();
        $guardId = isset($body['guard_id']) ? (int)$body['guard_id'] : 0;

        if ($guardId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'A valid guard_id is required',
                'status_code' => 422,
            ], 422);
            return;
        }

        $orgId = (int)($user['organization_id'] ?? 1);
        $adminUserId = (int)$user['id'];

        $res = NotificationService::sendWakeUpCall($guardId, $adminUserId, $orgId);

        if (!$res['success']) {
            $this->json([
                'success' => false,
                'message' => $res['message'],
                'data' => null,
                'status_code' => 404,
            ], 404);
            return;
        }

        $this->json([
            'success' => true,
            'message' => $res['message'],
            'data' => $res,
            'status_code' => 200,
        ], 200);
    }
}
