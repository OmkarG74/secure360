<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * UserDeviceToken Model
 * Manages Firebase Cloud Messaging (FCM) device registration tokens
 * Table: user_device_tokens (in secure360_v2)
 */
class UserDeviceToken extends Model
{
    protected string $table = 'user_device_tokens';

    public function __construct()
    {
        parent::__construct();
        $this->ensureTableExists();
    }

    /**
     * Auto-create table if missing (safe migration fallback)
     */
    private function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            // Read-only check: does NOT trigger MySQL implicit commit
            $this->db->query("SELECT 1 FROM `{$this->table}` LIMIT 0");
            $checked = true;
            return;
        } catch (\Throwable) {
            // Table does not exist yet
        }

        // Never run DDL (CREATE TABLE) inside an active transaction
        // because MySQL triggers an implicit commit upon executing DDL.
        if ($this->db->inTransaction()) {
            return;
        }

        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `user_id` bigint unsigned NOT NULL,
                  `fcm_token` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `device_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
                  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `is_active` tinyint(1) NOT NULL DEFAULT '1',
                  `last_used_at` datetime DEFAULT NULL,
                  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_user_device_tokens_user` (`user_id`, `is_active`),
                  KEY `idx_user_device_tokens_fcm` (`fcm_token`(191)),
                  CONSTRAINT `fk_user_device_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $checked = true;
        } catch (\Throwable) {
            // Ignore if already created or permission constrained
        }
    }

    /**
     * Register or update an FCM device token for a user.
     * Prevents duplicate active tokens and cleanly reassigns tokens if device owner changes.
     */
    public function registerToken(
        int $userId,
        string $fcmToken,
        string $deviceType = 'android',
        ?string $deviceName = null
    ): int {
        $fcmToken = trim($fcmToken);
        if ($fcmToken === '') {
            return 0;
        }

        // Check if token already exists
        $stmt = $this->db->prepare(
            "SELECT id, user_id FROM {$this->table} WHERE fcm_token = :token LIMIT 1"
        );
        $stmt->execute(['token' => $fcmToken]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $id = (int)$existing['id'];
            // Update token ownership and reactivate
            $upStmt = $this->db->prepare(
                "UPDATE {$this->table}
                 SET user_id = :user_id,
                     device_type = :device_type,
                     device_name = :device_name,
                     is_active = 1,
                     last_used_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $upStmt->execute([
                'user_id' => $userId,
                'device_type' => $deviceType,
                'device_name' => $deviceName,
                'id' => $id,
            ]);
            return $id;
        }

        // Insert new token
        $insStmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, fcm_token, device_type, device_name, is_active, last_used_at, created_at)
             VALUES (:user_id, :fcm_token, :device_type, :device_name, 1, NOW(), NOW())"
        );
        $insStmt->execute([
            'user_id' => $userId,
            'fcm_token' => $fcmToken,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Retrieve active FCM token strings for a specific user.
     *
     * @return string[]
     */
    public function getActiveTokensByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT fcm_token FROM {$this->table}
             WHERE user_id = :user_id AND is_active = 1
             ORDER BY last_used_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retrieve active FCM token strings for multiple user IDs.
     *
     * @param int[] $userIds
     * @return string[]
     */
    public function getActiveTokensByUsers(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds), fn($id) => $id > 0));
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT fcm_token FROM {$this->table}
             WHERE user_id IN ({$placeholders}) AND is_active = 1"
        );
        $stmt->execute($userIds);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Find token record by token string
     */
    public function findByToken(string $fcmToken): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE fcm_token = :token LIMIT 1"
        );
        $stmt->execute(['token' => trim($fcmToken)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Deactivate a specific token (e.g., on logout or unregistered response)
     */
    public function deactivateToken(string $fcmToken, ?int $userId = null): bool
    {
        $sql = "UPDATE {$this->table} SET is_active = 0, updated_at = NOW() WHERE fcm_token = :token";
        $params = ['token' => trim($fcmToken)];

        if ($userId !== null && $userId > 0) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Deactivate multiple invalid tokens in bulk
     *
     * @param string[] $invalidTokens
     */
    public function deactivateInvalidTokens(array $invalidTokens): void
    {
        $tokens = array_values(array_filter(array_map('trim', $invalidTokens)));
        if (empty($tokens)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_active = 0, updated_at = NOW() WHERE fcm_token IN ({$placeholders})"
        );
        $stmt->execute($tokens);
    }

    /**
     * Update the last_used_at timestamp for a token
     */
    public function updateLastUsed(string $fcmToken): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET last_used_at = NOW() WHERE fcm_token = :token"
        );
        return $stmt->execute(['token' => trim($fcmToken)]);
    }
}
