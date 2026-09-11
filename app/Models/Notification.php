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
        return $stmt->fetchAll();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id"
        );
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
