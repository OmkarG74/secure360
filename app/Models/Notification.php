<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Notification Model
 * Table: notifications (in secure360_v2)
 */
class Notification extends Model
{
    protected string $table = 'notifications';

    public function findByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :lim"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findForAdmin(int $userId, int $orgId, int $limit = 15): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE organization_id = :org_id AND (user_id = :user_id OR user_id IS NULL OR user_id = 0)
             ORDER BY created_at DESC 
             LIMIT :lim"
        );
        $stmt->bindValue(':org_id', $orgId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id"
        );
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
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
}
