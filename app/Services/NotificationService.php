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

        // Ensure data_json in database contains notification_id and initial acknowledgement flag
        $data['notification_id'] = (string)$notificationId;
        if ($type === 'wake_up_call' || str_contains($type, 'wake_up') || str_contains($type, 'wakeup')) {
            $data['acknowledged'] = false;
        }
        $updatedJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        try {
            $updDataStmt = $db->prepare("UPDATE notifications SET data_json = :data_json WHERE id = :id");
            $updDataStmt->execute(['data_json' => $updatedJson, 'id' => $notificationId]);
        } catch (\Throwable) {}

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

    /**
     * Send notification to all active administrators of an organization
     *
     * @param int $organizationId Target organization ID
     * @param string $title Headline
     * @param string $message Detailed message
     * @param string $type e.g. attendance_checkin, attendance_checkout, post_departure
     * @param array $data Additional metadata payload
     * @param array $options Configuration options (priority, entity_type, entity_id, sender_user_id)
     * @return array
     */
    public static function sendToAdmins(
        int $organizationId,
        string $title,
        string $message,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $db = Database::getConnection();

        // 1. Fetch all active Admin user IDs belonging to this organization
        $stmt = $db->prepare(
            "SELECT u.id 
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.organization_id = :org_id 
               AND r.role_code = 'admin'
               AND u.status = 0 
               AND u.deleted_at IS NULL"
        );
        $stmt->execute(['org_id' => $organizationId]);
        $adminUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $options['organization_id'] = $organizationId;

        // If specific admin accounts exist, create individual notifications for each
        if (!empty($adminUserIds)) {
            return self::sendToUsers($adminUserIds, $title, $message, $type, $data, $options);
        }

        // Fallback: If no dedicated admin user account is present in this org yet,
        // create an organization-level notification record (user_id = null)
        // so that any admin signing in will see it in the topbar bell.
        $priority = strtolower((string)($options['priority'] ?? 'normal'));
        if (!in_array($priority, ['low', 'normal', 'high', 'critical'], true)) {
            $priority = 'normal';
        }
        $entityType = isset($options['entity_type']) ? (string)$options['entity_type'] : null;
        $entityId = isset($options['entity_id'])
            ? (string)$options['entity_id']
            : (isset($data['entity_id']) ? (string)$data['entity_id'] : null);
        $senderUserId = isset($options['sender_user_id']) ? (int)$options['sender_user_id'] : null;

        $json = !empty($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $insStmt = $db->prepare(
            "INSERT INTO notifications 
                (organization_id, user_id, sender_user_id, type, entity_type, entity_id, priority, title, message, data_json, is_read, delivery_status, created_at)
             VALUES 
                (:org_id, NULL, :sender_user_id, :type, :entity_type, :entity_id, :priority, :title, :message, :data_json, 0, 'sent', NOW())"
        );
        $insStmt->execute([
            'org_id' => $organizationId,
            'sender_user_id' => $senderUserId,
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'data_json' => $json,
        ]);

        $notifId = (int)$db->lastInsertId();
        return [
            'total_users' => 1,
            'successful_dispatches' => 1,
            'results' => [
                'org_' . $organizationId => [
                    'success' => true,
                    'notification_id' => $notifId,
                    'fcm_status' => ['total_devices' => 0, 'sent_count' => 0, 'failed_count' => 0, 'results' => []],
                ]
            ],
        ];
    }

    /**
     * Dispatch an immediate, high-priority Wake-Up Call to a specific Guard
     *
     * @param int $guardId Target guard ID
     * @param int $adminUserId Admin initiator user ID
     * @param int $organizationId Scoped organization ID
     * @return array{success: bool, notification_id: int, guard_name: string, total_devices: int, sent_count: int, message: string}
     */
    public static function sendWakeUpCall(int $guardId, int $adminUserId, int $organizationId): array
    {
        $db = Database::getConnection();

        // 1. Validate guard belongs to this organization and is active
        $stmt = $db->prepare(
            "SELECT g.id as guard_id, g.user_id, u.organization_id, u.full_name, u.employee_code
             FROM guards g
             JOIN users u ON g.user_id = u.id
             WHERE g.id = :guard_id 
               AND u.organization_id = :org_id 
               AND g.status = 0 
               AND g.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['guard_id' => $guardId, 'org_id' => $organizationId]);
        $guard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$guard) {
            return [
                'success' => false,
                'notification_id' => 0,
                'guard_name' => '',
                'total_devices' => 0,
                'sent_count' => 0,
                'message' => "Guard #{$guardId} was not found or is not active in this organization.",
            ];
        }

        $guardUserId = (int)$guard['user_id'];
        $guardName = (string)$guard['full_name'];
        $sentAt = date('Y-m-d H:i:s');
        $title = 'Wake-Up Call';
        $message = 'Your supervisor has sent you a wake-up call.';

        $options = [
            'priority' => 'critical',
            'entity_type' => 'guard',
            'entity_id' => (string)$guardId,
            'sender_user_id' => $adminUserId,
            'organization_id' => $organizationId,
        ];

        $data = [
            'type' => 'wake_up',
            'guard_id' => (string)$guardId,
            'guard_name' => $guardName,
            'screen' => 'wake_up',
            'entity_id' => (string)$guardId,
            'sent_at' => $sentAt,
        ];

        // sendToUser inserts DB notification and sends FCM to all active tokens
        $res = self::sendToUser($guardUserId, $title, $message, 'wake_up', $data, $options);
        $notifId = $res['notification_id'] ?? 0;
        $fcm = $res['fcm_status'] ?? [];
        $totalDevices = (int)($fcm['total_devices'] ?? 0);
        $sentCount = (int)($fcm['sent_count'] ?? 0);

        if ($totalDevices === 0) {
            $statusMessage = 'Wake-up notification recorded, but the Guard has no active registered device.';
        } elseif ($sentCount > 0) {
            $statusMessage = "Wake-up call dispatched successfully to {$sentCount} device(s) for {$guardName}.";
        } else {
            $statusMessage = 'Wake-up notification recorded, but device delivery failed.';
        }

        return [
            'success' => true,
            'notification_id' => $notifId,
            'guard_name' => $guardName,
            'total_devices' => $totalDevices,
            'sent_count' => $sentCount,
            'message' => $statusMessage,
        ];
    }
}
