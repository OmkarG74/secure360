<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Models\Notification;

/**
 * Mobile Guard Notification REST API Controller
 * Securely scoped to authenticated guard user_id (prevents IDOR attacks)
 */
class GuardNotificationController extends Controller
{
    private Notification $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new Notification();
    }

    /**
     * List authenticated guard notifications
     * GET /api/v1/guard/notifications
     */
    public function index(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $userId = (int)$guard['user_id'];
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

        $notifications = $this->notificationModel->findByUser($userId, $limit, $offset);
        $unreadCount = $this->notificationModel->getUnreadCountForUser($userId);

        $this->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => [
                'unread_count' => $unreadCount,
                'notifications' => $notifications,
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * Mark a single notification as read
     * POST /api/v1/guard/notifications/{id}/read
     */
    public function markRead(string|int $id): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $userId = (int)$guard['user_id'];
        $notifId = (int)$id;

        $success = $this->notificationModel->markAsRead($notifId, $userId);
        $unreadCount = $this->notificationModel->getUnreadCountForUser($userId);

        $this->json([
            'success' => $success,
            'message' => $success ? 'Notification marked as read' : 'Notification not found',
            'data' => [
                'notification_id' => $notifId,
                'unread_count' => $unreadCount,
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * Mark all notifications as read for current guard
     * POST /api/v1/guard/notifications/read-all
     */
    public function markAllRead(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $userId = (int)$guard['user_id'];
        $this->notificationModel->markAllAsReadForUser($userId);

        $this->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'unread_count' => 0,
            ],
            'status_code' => 200,
        ]);
    }
}
