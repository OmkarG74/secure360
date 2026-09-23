<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Core\Request;
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
     * Resolve notification ID safely from direct argument, route params, or request
     */
    private function resolveNotificationId(mixed $idOrRequest, array $params = []): int
    {
        if (is_numeric($idOrRequest)) {
            return (int)$idOrRequest;
        }
        if (!empty($params['id']) && is_numeric($params['id'])) {
            return (int)$params['id'];
        }
        if ($idOrRequest instanceof Request) {
            $paramId = $idOrRequest->param('id');
            if (!empty($paramId) && is_numeric($paramId)) {
                return (int)$paramId;
            }
        }
        if (!empty($this->request)) {
            $paramId = $this->request->param('id');
            if (!empty($paramId) && is_numeric($paramId)) {
                return (int)$paramId;
            }
        }
        return 0;
    }

    /**
     * Mark a single notification as read
     * POST /api/v1/guard/notifications/{id}/read
     */
    public function markRead(mixed $id = null, mixed $response = null, array $params = []): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $userId = (int)$guard['user_id'];
        $notifId = $this->resolveNotificationId($id, $params);

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

    /**
     * Acknowledge a notification (specifically wake-up calls)
     * POST /api/v1/guard/notifications/{id}/acknowledge
     */
    public function acknowledge(mixed $id = null, mixed $response = null, array $params = []): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            error_log("[WakeUp] API acknowledge() FAILED: Unauthorized - missing AUTH_GUARD");
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $userId = (int)$guard['user_id'];
        $guardId = (int)$guard['guard_id'];
        $notifId = $this->resolveNotificationId($id, $params);

        error_log("[WakeUp] Acknowledge pressed for ID={$notifId}, GuardUser={$userId}, GuardId={$guardId}");

        $db = \App\Core\Database::getConnection();

        // 1. Fetch notification and verify ownership
        $stmt = $db->prepare(
            "SELECT * FROM notifications 
             WHERE id = :id AND (user_id = :user_id OR entity_id = :guard_id) 
             LIMIT 1"
        );
        $stmt->execute(['id' => $notifId, 'user_id' => $userId, 'guard_id' => (string)$guardId]);
        $notif = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$notif) {
            error_log("[WakeUp] API acknowledge() FAILED: Notification ID={$notifId} not found or access denied for GuardUser={$userId}, GuardId={$guardId}");
            $this->json([
                'success' => false,
                'message' => 'Notification not found or access denied',
                'status_code' => 404,
            ], 404);
            return;
        }

        // Verify notification type is wake_up / wake_up_call
        $type = (string)($notif['type'] ?? '');
        $isWakeUp = in_array($type, ['wake_up', 'wake_up_call'], true) || str_contains($type, 'wake_up') || str_contains($type, 'wakeup');
        if (!$isWakeUp) {
            error_log("[WakeUp] API acknowledge() FAILED: Notification ID={$notifId} type '{$type}' is not a wake-up call");
            $this->json([
                'success' => false,
                'message' => 'Notification is not a wake-up call',
                'status_code' => 400,
            ], 400);
            return;
        }

        // 2. Parse data_json, verify if already acknowledged (idempotent)
        $data = !empty($notif['data_json']) ? json_decode($notif['data_json'], true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $alreadyAcknowledged = Notification::checkRowAcknowledged($notif);
        $ackTime = ($alreadyAcknowledged && !empty($data['acknowledged_at']))
            ? (string)$data['acknowledged_at']
            : gmdate('Y-m-d H:i:s') . ' UTC';

        $data['notification_id'] = (string)$notifId;
        $data['acknowledged'] = true;
        $data['acknowledged_at'] = $ackTime;
        $data['acknowledged_by_guard_id'] = $guardId;
        $newJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 3. Mark read and update metadata
        $upStmt = $db->prepare(
            "UPDATE notifications 
             SET is_read = 1, read_at = COALESCE(read_at, NOW()), data_json = :data_json, updated_at = NOW() 
             WHERE id = :id"
        );
        $upStmt->execute([
            'data_json' => $newJson,
            'id' => $notifId,
        ]);

        // Verification query immediately following update
        $verStmt = $db->prepare("SELECT id, is_read, read_at, data_json FROM notifications WHERE id = :id");
        $verStmt->execute(['id' => $notifId]);
        $vRow = $verStmt->fetch(\PDO::FETCH_ASSOC);
        $isAck = Notification::checkRowAcknowledged($vRow ?: []);
        $vData = !empty($vRow['data_json']) ? json_decode($vRow['data_json'], true) : [];

        error_log("[WakeUp] API acknowledge() SUCCESS: notifId={$notifId}, is_read={$vRow['is_read']}, acknowledged=" . ($isAck ? 'true' : 'false') . ", ack_at=" . ($vData['acknowledged_at'] ?? 'null'));

        $unreadCount = $this->notificationModel->getUnreadCountForUser($userId);

        $this->json([
            'success' => true,
            'message' => $alreadyAcknowledged ? 'Notification already acknowledged' : 'Notification acknowledged successfully',
            'data' => [
                'notification_id' => $notifId,
                'acknowledged' => true,
                'acknowledged_at' => $ackTime,
                'unread_count' => $unreadCount,
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * Get authoritative status of a specific notification
     * GET /api/v1/guard/notifications/{id}/status
     */
    public function status(mixed $id = null, mixed $response = null, array $params = []): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $userId = (int)$guard['user_id'];
        $guardId = (int)$guard['guard_id'];
        $notifId = $this->resolveNotificationId($id, $params);

        $db = \App\Core\Database::getConnection();

        $stmt = $db->prepare(
            "SELECT * FROM notifications 
             WHERE id = :id AND (user_id = :user_id OR entity_id = :guard_id) 
             LIMIT 1"
        );
        $stmt->execute(['id' => $notifId, 'user_id' => $userId, 'guard_id' => (string)$guardId]);
        $notif = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$notif) {
            $this->json([
                'success' => false,
                'message' => 'Notification not found',
                'status_code' => 404,
            ], 404);
            return;
        }

        $ackStatus = $this->notificationModel->getAcknowledgementStatus($notifId);

        $this->json([
            'success' => true,
            'data' => array_merge([
                'notification_id' => $notifId,
                'type' => $notif['type'],
                'is_read' => (int)$notif['is_read'],
            ], $ackStatus),
            'status_code' => 200,
        ]);
    }
}
