<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Notification;
use App\Models\UserDeviceToken;
use PDO;

/**
 * Pure Core PHP Centralized Notification Service for Secure360
 *
 * Coordinates database notification records and real-time FCM push dispatching
 * across all operational modules (Contracts, Shifts, Attendance, Admin Alerts).
 *
 * Invariant: FCM push failure is non-fatal; DB notifications and business
 * transactions are ALWAYS preserved.
 */
class NotificationService
{
    /**
     * Send notification to a specific user
     *
     * @param int $userId Target user ID
     * @param string $title Notification headline
     * @param string $message Detailed message
     * @param string $type Classification type (e.g. contract_assignment, shift_assigned, admin_alert)
     * @param array $data Extra payload/meta (e.g. ['screen' => 'assignment_details', 'contract_id' => 10])
     * @param array $options Additional options:
     *   - 'priority' => 'low'|'normal'|'high'|'critical' (default 'normal')
     *   - 'entity_type' => string|null
     *   - 'entity_id' => string|int|null
     *   - 'sender_user_id' => int|null
     *   - 'organization_id' => int|null
     * @return array{success: bool, notification_id: int, fcm_status: array}
     */
    public static function sendToUser(
        int $userId,
        string $title,
        string $message,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $db = Database::getConnection();

        // 1. Resolve Organization ID if not explicitly provided
        $orgId = isset($options['organization_id']) ? (int)$options['organization_id'] : 0;
        if ($orgId <= 0) {
            $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = :user_id LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $orgId = (int)$stmt->fetchColumn() ?: 1;
        }

        // 2. Prepare metadata attributes
        $priority = strtolower((string)($options['priority'] ?? 'normal'));
        if (!in_array($priority, ['low', 'normal', 'high', 'critical'], true)) {
            $priority = 'normal';
        }

        $senderUserId = isset($options['sender_user_id']) && (int)$options['sender_user_id'] > 0
            ? (int)$options['sender_user_id']
            : null;

        $entityType = isset($options['entity_type']) ? (string)$options['entity_type'] : null;
        $entityId = isset($options['entity_id'])
            ? (string)$options['entity_id']
            : (isset($data['entity_id']) ? (string)$data['entity_id'] : null);

        // 3. Create persistent Database Notification Record
        $notificationModel = new Notification();
        $json = !empty($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $insStmt = $db->prepare(
            "INSERT INTO notifications 
                (organization_id, user_id, sender_user_id, type, entity_type, entity_id, priority, title, message, data_json, is_read, delivery_status, created_at)
             VALUES 
                (:org_id, :user_id, :sender_user_id, :type, :entity_type, :entity_id, :priority, :title, :message, :data_json, 0, 'pending', NOW())"
        );
        $insStmt->execute([
            'org_id' => $orgId,
            'user_id' => $userId,
            'sender_user_id' => $senderUserId,
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'data_json' => $json,
        ]);

        $notificationId = (int)$db->lastInsertId();

        // 4. Dispatch FCM Push to Target Devices
        $fcmStatus = [
            'total_devices' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'results' => [],
        ];

        try {
            $fcmService = new FirebaseNotificationService();
            $resolvedScreen = $data['screen'] ?? $notificationModel->resolveScreenFromType($type, $data);

            $fcmPayload = array_merge($data, [
                'type' => $type,
                'screen' => $resolvedScreen,
                'entity_id' => (string)($entityId ?? ''),
                'notification_id' => (string)$notificationId,
                'priority' => $priority,
            ]);

            $fcmStatus = $fcmService->sendToUser($userId, $title, $message, $fcmPayload);

            // Update delivery status in database
            $deliveryStatus = 'sent';
            if ($fcmStatus['total_devices'] > 0 && $fcmStatus['sent_count'] === 0) {
                $deliveryStatus = 'failed';
            } elseif ($fcmStatus['total_devices'] === 0) {
                // No devices registered; database notification stored
                $deliveryStatus = 'sent';
            }

            $upStmt = $db->prepare("UPDATE notifications SET delivery_status = :status WHERE id = :id");
            $upStmt->execute(['status' => $deliveryStatus, 'id' => $notificationId]);
        } catch (\Throwable $e) {
            // FCM failure is non-fatal; log securely without exposing sensitive tokens
            error_log("[NotificationService] FCM delivery failed for user {$userId}: " . $e->getMessage());

            try {
                $upStmt = $db->prepare("UPDATE notifications SET delivery_status = 'failed' WHERE id = :id");
                $upStmt->execute(['id' => $notificationId]);
            } catch (\Throwable) {
                // Ignore DB status update errors
            }
        }

        return [
            'success' => true,
            'notification_id' => $notificationId,
            'fcm_status' => $fcmStatus,
        ];
    }

    /**
     * Send notification to multiple user IDs
     *
     * @param int[] $userIds
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $data
     * @param array $options
     * @return array{total_users: int, successful_dispatches: int, results: array}
     */
    public static function sendToUsers(
        array $userIds,
        string $title,
        string $message,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0)));
        $results = [];
        $successful = 0;

        foreach ($userIds as $userId) {
            $res = self::sendToUser($userId, $title, $message, $type, $data, $options);
            $results[$userId] = $res;
            if ($res['success']) {
                $successful++;
            }
        }

        return [
            'total_users' => count($userIds),
            'successful_dispatches' => $successful,
            'results' => $results,
        ];
    }

    /**
     * Send notification to a specific Guard by guard_id
     *
     * @param int $guardId Target guard ID (from guards table)
     */
    public static function sendToGuard(
        int $guardId,
        string $title,
        string $message,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT g.id as guard_id, g.user_id, u.organization_id, u.full_name
             FROM guards g
             JOIN users u ON g.user_id = u.id
             WHERE g.id = :guard_id AND g.status = 0 AND g.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['guard_id' => $guardId]);
        $guard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$guard) {
            return [
                'success' => false,
                'notification_id' => 0,
                'error' => "Active guard with ID {$guardId} not found",
                'fcm_status' => [],
            ];
        }

        $userId = (int)$guard['user_id'];
        $options['organization_id'] = (int)$guard['organization_id'];

        return self::sendToUser($userId, $title, $message, $type, $data, $options);
    }

    /**
     * Send notification to multiple guards by guard_ids
     *
     * @param int[] $guardIds
     */
    public static function sendToGuards(
        array $guardIds,
        string $title,
        string $message,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $guardIds = array_values(array_unique(array_filter(array_map('intval', $guardIds), fn($id) => $id > 0)));
        if (empty($guardIds)) {
            return ['total_guards' => 0, 'successful_dispatches' => 0, 'results' => []];
        }

        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($guardIds), '?'));
        $stmt = $db->prepare(
            "SELECT g.id as guard_id, g.user_id, u.organization_id
             FROM guards g
             JOIN users u ON g.user_id = u.id
             WHERE g.id IN ({$placeholders}) AND g.status = 0 AND g.deleted_at IS NULL"
        );
        $stmt->execute($guardIds);
        $guards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $successful = 0;

        foreach ($guards as $guard) {
            $guardOptions = $options;
            $guardOptions['organization_id'] = (int)$guard['organization_id'];
            $res = self::sendToUser((int)$guard['user_id'], $title, $message, $type, $data, $guardOptions);
            $results[(int)$guard['guard_id']] = $res;
            if ($res['success']) {
                $successful++;
            }
        }

        return [
            'total_guards' => count($guardIds),
            'successful_dispatches' => $successful,
            'results' => $results,
        ];
    }

    /**
     * Send notification to all users of a specific role within an organisation
     *
     * @param string $role e.g. 'guard', 'admin'
     * @param int $organizationId
     */
    public static function sendToRole(
        string $role,
        int $organizationId,
        string $title,
        string $message,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $db = Database::getConnection();

        // Resolve role ID
        $roleStmt = $db->prepare("SELECT id FROM roles WHERE role_code = :role LIMIT 1");
        $roleStmt->execute(['role' => strtolower(trim($role))]);
        $roleId = (int)$roleStmt->fetchColumn();

        if ($roleId <= 0) {
            return ['total_users' => 0, 'successful_dispatches' => 0, 'error' => "Invalid role: {$role}"];
        }

        $userStmt = $db->prepare(
            "SELECT id FROM users
             WHERE organization_id = :org_id AND role_id = :role_id AND status = 0 AND deleted_at IS NULL"
        );
        $userStmt->execute(['org_id' => $organizationId, 'role_id' => $roleId]);
        $userIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);

        $options['organization_id'] = $organizationId;
        return self::sendToUsers($userIds, $title, $message, $type, $data, $options);
    }
}
