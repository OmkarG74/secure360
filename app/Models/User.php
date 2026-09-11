<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * User Model
 * Table: users (in secure360_v2)
 * Superadmin has organization_id = NULL. Admin and Guard have organization_id.
 */
class User extends Model
{
    protected string $table = 'users';

    /**
     * Find active user by email with role information
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.role_code, r.role_name, o.name as organization_name
             FROM {$this->table} u
             JOIN roles r ON u.role_id = r.id
             LEFT JOIN organizations o ON u.organization_id = o.id
             WHERE u.email = :email AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find user by ID with role information
     */
    public function findWithRole(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.role_code, r.role_name, o.name as organization_name
             FROM {$this->table} u
             JOIN roles r ON u.role_id = r.id
             LEFT JOIN organizations o ON u.organization_id = o.id
             WHERE u.id = :id AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET last_login_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
    }
}
