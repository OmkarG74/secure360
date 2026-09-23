<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\FirebaseNotificationService;
use PDO;

/**
 * Notification Model
 * Table: notifications (in secure360_v2)
 */
class Notification extends Model
{
    protected string $table = 'notifications';

    public function __construct()
    {
        parent::__construct();
        $this->ensureColumnsExist();
    }

    /**
     * Resilient column check ensuring migration columns exist
     */
    private function ensureColumnsExist(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'priority'");
            if (!$stmt->fetch()) {
                if (!$this->db->inTransaction()) {
                    $this->db->exec(
                        "ALTER TABLE `{$this->table}`
                          ADD COLUMN IF NOT EXISTS `sender_user_id` bigint unsigned DEFAULT NULL AFTER `user_id`,
                          ADD COLUMN IF NOT EXISTS `entity_type` varchar(50) DEFAULT NULL AFTER `type`,
                          ADD COLUMN IF NOT EXISTS `entity_id` varchar(50) DEFAULT NULL AFTER `entity_type`,
                          ADD COLUMN IF NOT EXISTS `priority` varchar(20) NOT NULL DEFAULT 'normal' AFTER `entity_id`,
                          ADD COLUMN IF NOT EXISTS `delivery_status` varchar(20) NOT NULL DEFAULT 'sent' AFTER `is_read`"
                    );
                }
            }
            $checked = true;
        } catch (\Throwable) {
            // Ignore if constrained or already executed
        }
    }

    /**
     * Retrieve notifications for a specific user (e.g. Guard mobile)
     */
    public function findByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, organization_id, user_id, sender_user_id, type, entity_type, entity_id, priority,
                    title, message, data_json, is_read, delivery_status, read_at, created_at, updated_at
             FROM {$this->table}
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count unread notifications for a user
     */
    public function getUnreadCountForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = :user_id AND is_read = 0"
        );
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark single notification as read for a user
     */
    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id"
        );
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    /**
     * Mark all notifications as read for a specific user
     */
    public function markAllAsReadForUser(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_read = 1, read_at = NOW()
             WHERE user_id = :user_id AND is_read = 0"
        );
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Popover notifications for admin header
     */
    public function findForAdmin(int $userId, int $orgId, int $limit = 15): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE organization_id = :org_id AND (user_id = :user_id OR user_id IS NULL OR user_id = 0)
             ORDER BY created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCountForAdmin(int $userId, int $orgId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE organization_id = :org_id AND (user_id = :user_id OR user_id IS NULL OR user_id = 0) AND is_read = 0"
        );
        $stmt->execute(['org_id' => $orgId, 'user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsReadForAdmin(int $id, int $userId, int $orgId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_read = 1, read_at = NOW()
             WHERE id = :id AND organization_id = :org_id AND (user_id = :user_id OR user_id IS NULL OR user_id = 0)"
        );
        return $stmt->execute(['id' => $id, 'org_id' => $orgId, 'user_id' => $userId]);
    }

    public function markAllAsReadForAdmin(int $userId, int $orgId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_read = 1, read_at = NOW()
             WHERE organization_id = :org_id AND (user_id = :user_id OR user_id IS NULL OR user_id = 0) AND is_read = 0"
        );
        return $stmt->execute(['org_id' => $orgId, 'user_id' => $userId]);
    }

    /**
     * Admin Notification Management History with filters & pagination
     */
    public function findForAdminHistory(int $orgId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = ["n.organization_id = :org_id"];
        $params = ['org_id' => $orgId];

        if (!empty($filters['type'])) {
            $where[] = "n.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['priority'])) {
            $where[] = "n.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['delivery_status'])) {
            $where[] = "n.delivery_status = :delivery_status";
            $params['delivery_status'] = $filters['delivery_status'];
        }

        if (!empty($filters['recipient_id'])) {
            $where[] = "n.user_id = :recipient_id";
            $params['recipient_id'] = (int)$filters['recipient_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(n.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(n.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(n.title LIKE :search OR n.message LIKE :search OR u.full_name LIKE :search OR u.employee_code LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT n.*,
                       u.full_name as recipient_name,
                       u.employee_code as recipient_code,
                       u.email as recipient_email,
                       su.full_name as sender_name
                FROM {$this->table} n
                LEFT JOIN users u ON n.user_id = u.id
                LEFT JOIN users su ON n.sender_user_id = su.id
                WHERE {$whereClause}
                ORDER BY n.created_at DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total notifications matching admin history filters
     */
    public function countForAdminHistory(int $orgId, array $filters = []): int
    {
        $where = ["n.organization_id = :org_id"];
        $params = ['org_id' => $orgId];

        if (!empty($filters['type'])) {
            $where[] = "n.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['priority'])) {
            $where[] = "n.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['delivery_status'])) {
            $where[] = "n.delivery_status = :delivery_status";
            $params['delivery_status'] = $filters['delivery_status'];
        }

        if (!empty($filters['recipient_id'])) {
            $where[] = "n.user_id = :recipient_id";
            $params['recipient_id'] = (int)$filters['recipient_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(n.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(n.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(n.title LIKE :search OR n.message LIKE :search OR u.full_name LIKE :search OR u.employee_code LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$this->table} n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE {$whereClause}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Dual-delivery notification creator:
     * 1. Inserts persistent notification into MySQL `notifications` table.
     * 2. Dispatches real-time Firebase FCM push notification to target user devices.
     * Guaranteed never to fail the business operation if FCM is temporarily unavailable.
     */
    public function createNotificationWithPush(
        int $organizationId,
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        array $options = []
    ): int {
        $json = $data !== null ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $priority = $options['priority'] ?? 'normal';
        $senderUserId = $options['sender_user_id'] ?? null;
        $entityType = $options['entity_type'] ?? null;
        $entityId = $options['entity_id'] ?? ($data['entity_id'] ?? null);

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (organization_id, user_id, sender_user_id, type, entity_type, entity_id, priority, title, message, data_json, is_read, delivery_status, created_at)
             VALUES (:org_id, :user_id, :sender_user_id, :type, :entity_type, :entity_id, :priority, :title, :message, :data_json, 0, 'pending', NOW())"
        );
        $stmt->execute([
            'org_id' => $organizationId,
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

        $notifId = (int)$this->db->lastInsertId();

        // Attempt FCM Push Notification
        $deliveryStatus = 'sent';
        try {
            $fcmService = new FirebaseNotificationService();
            $pushData = array_merge($data ?? [], [
                'type' => $type,
                'notification_id' => (string)$notifId,
                'screen' => $this->resolveScreenFromType($type, $data),
                'entity_id' => (string)($entityId ?? ''),
                'priority' => (string)$priority,
            ]);

            $res = $fcmService->sendToUser($userId, $title, $message, $pushData);
            if ($res['total_devices'] > 0 && $res['sent_count'] === 0) {
                $deliveryStatus = 'failed';
            }
        } catch (\Throwable $e) {
            // Never break business flow if push transport fails
            error_log('[Notification] FCM push delivery skipped/failed: ' . $e->getMessage());
            $deliveryStatus = 'failed';
        }

        // Update delivery status
        try {
            $upStmt = $this->db->prepare("UPDATE {$this->table} SET delivery_status = :status WHERE id = :id");
            $upStmt->execute(['status' => $deliveryStatus, 'id' => $notifId]);
        } catch (\Throwable) {
            // Non-critical
        }

        return $notifId;
    }

    /**
     * Resolve Flutter destination screen from notification type
     */
    public function resolveScreenFromType(string $type, ?array $data = null): string
    {
        if (!empty($data['screen'])) {
            return (string)$data['screen'];
        }

        $type = strtolower($type);
        if (str_contains($type, 'contract') || str_contains($type, 'duty') || str_contains($type, 'assignment')) {
            return 'assignment_details';
        }
        if (str_contains($type, 'shift')) {
            return 'shift_details';
        }
        if (str_contains($type, 'attendance') || str_contains($type, 'checkin') || str_contains($type, 'checkout')) {
            return 'attendance';
        }
        if (str_contains($type, 'departure')) {
            return 'guard/location';
        }
        if (str_contains($type, 'wake_up') || str_contains($type, 'wakeup')) {
            return 'wake_up';
        }
        if (str_contains($type, 'sos') || str_contains($type, 'emergency') || str_contains($type, 'alert')) {
            return 'notifications';
        }
        return 'notifications';
    }

    /**
     * Determine whether a notification has been acknowledged.
     * Checks data_json (acknowledged flag / acknowledged_at) and read status.
     */
    public static function isAcknowledged(int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        $db = \App\Core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT type, data_json, is_read, read_at FROM notifications WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        return self::checkRowAcknowledged($row);
    }

    /**
     * Static helper to check acknowledgement status of a database row or by ID.
     * Strictly verifies persisted data_json acknowledgement; does NOT conflate with is_read.
     */
    public static function checkRowAcknowledged(array $row): bool
    {
        $data = [];
        if (!empty($row['data_json'])) {
            $parsed = is_string($row['data_json']) ? json_decode($row['data_json'], true) : $row['data_json'];
            if (is_string($parsed)) {
                $parsed = json_decode($parsed, true);
            }
            if (is_array($parsed)) {
                $data = $parsed;
            }
        }

        if (!empty($data['acknowledged']) && ($data['acknowledged'] === true || $data['acknowledged'] === 1 || $data['acknowledged'] === 'true') && !empty($data['acknowledged_at'])) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve authoritative acknowledgement details
     * @return array{acknowledged: bool, acknowledged_at: ?string, acknowledged_by_guard_id: ?int}
     */
    public function getAcknowledgementStatus(int $notificationId): array
    {
        if ($notificationId <= 0) {
            return [
                'acknowledged' => false,
                'acknowledged_at' => null,
                'acknowledged_by_guard_id' => null,
            ];
        }

        $stmt = $this->db->prepare(
            "SELECT type, data_json, is_read, read_at FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'acknowledged' => false,
                'acknowledged_at' => null,
                'acknowledged_by_guard_id' => null,
            ];
        }

        $data = [];
        if (!empty($row['data_json'])) {
            $parsed = is_string($row['data_json']) ? json_decode($row['data_json'], true) : $row['data_json'];
            if (is_string($parsed)) {
                $parsed = json_decode($parsed, true);
            }
            if (is_array($parsed)) {
                $data = $parsed;
            }
        }

        $isAck = self::checkRowAcknowledged($row);
        $ackAt = $isAck ? ($data['acknowledged_at'] ?? null) : null;
        $guardId = isset($data['acknowledged_by_guard_id']) ? (int)$data['acknowledged_by_guard_id'] : null;

        return [
            'acknowledged' => $isAck,
            'acknowledged_at' => $ackAt,
            'acknowledged_by_guard_id' => $guardId,
        ];
    }
}
