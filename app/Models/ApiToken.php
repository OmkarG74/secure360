<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * ApiToken Model
 * Table: api_tokens (in secure360_v2)
 * Manages Bearer tokens for Flutter Mobile Application
 */
class ApiToken extends Model
{
    protected string $table = 'api_tokens';

    /**
     * Create a new API token for a user
     *
     * @param int $userId
     * @param string $plainToken
     * @param string $deviceName
     * @param string $deviceType
     * @param int $expiryDays
     * @return int Token ID
     */
    public function createToken(
        int $userId,
        string $plainToken,
        string $deviceName = 'Mobile App',
        string $deviceType = 'android',
        int $expiryDays = 30
    ): int {
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryDays * 86400));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, token_hash, device_name, device_type, expires_at, created_at)
             VALUES (:user_id, :token_hash, :device_name, :device_type, :expires_at, NOW())"
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'device_name' => $deviceName,
            'device_type' => $deviceType,
            'expires_at' => $expiresAt,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Find a valid, unexpired, non-revoked token
     */
    public function findValidToken(string $plainToken): ?array
    {
        $tokenHash = hash('sha256', $plainToken);

        $stmt = $this->db->prepare(
            "SELECT t.*, u.organization_id, u.role_id, u.full_name, u.email, u.status as user_status, r.role_code
             FROM {$this->table} t
             JOIN users u ON t.user_id = u.id
             JOIN roles r ON u.role_id = r.id
             WHERE t.token_hash = :token_hash 
               AND t.revoked_at IS NULL 
               AND t.expires_at > NOW() 
               AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $result = $stmt->fetch();

        if ($result) {
            // Update last used timestamp
            $updateStmt = $this->db->prepare("UPDATE {$this->table} SET last_used_at = NOW() WHERE id = :id");
            $updateStmt->execute(['id' => $result['id']]);
            return $result;
        }

        return null;
    }

    /**
     * Revoke a token
     */
    public function revokeToken(string $plainToken): bool
    {
        $tokenHash = hash('sha256', $plainToken);
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET revoked_at = NOW() WHERE token_hash = :token_hash"
        );
        return $stmt->execute(['token_hash' => $tokenHash]);
    }
}
