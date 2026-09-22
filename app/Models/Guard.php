<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Guard Model
 * Table: guards (in secure360_v2)
 * Linked to users table via user_id
 */
class Guard extends Model
{
    protected string $table = 'guards';

    /**
     * Retrieve all active guards belonging to an organization
     */
    public function allByTenant(int $organisationId, string $orderBy = 'u.full_name ASC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT g.id as guard_id, g.user_id, g.photo_url, g.status as guard_status,
                    u.full_name, u.email, u.phone, u.employee_code, u.status as user_status,
                    u.last_login_at
             FROM {$this->table} g
             JOIN users u ON g.user_id = u.id
             WHERE u.organization_id = :org_id 
               AND g.deleted_at IS NULL 
               AND u.deleted_at IS NULL
             ORDER BY {$orderBy}"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Count total active guards for an organization
     * (consuming a licensed subscription slot)
     */
    public function countActiveGuards(int $organisationId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} g
             JOIN users u ON g.user_id = u.id
             WHERE u.organization_id = :org_id
               AND g.status = 0
               AND u.status = 0
               AND g.deleted_at IS NULL
               AND u.deleted_at IS NULL"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find single guard with complete user profile
     */
    public function findByGuardId(int $guardId, int $organisationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT g.id as guard_id, g.user_id, g.photo_url, g.status as guard_status,
                    u.full_name, u.email, u.phone, u.employee_code, u.status as user_status,
                    u.organization_id, u.last_login_at
             FROM {$this->table} g
             JOIN users u ON g.user_id = u.id
             WHERE g.id = :guard_id 
               AND u.organization_id = :org_id
               AND g.deleted_at IS NULL 
               AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['guard_id' => $guardId, 'org_id' => $organisationId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find guard by user_id
     */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT g.id as guard_id, g.user_id, g.photo_url, g.status as guard_status,
                    u.full_name, u.email, u.phone, u.employee_code, u.organization_id
             FROM {$this->table} g
             JOIN users u ON g.user_id = u.id
             WHERE g.user_id = :user_id 
               AND g.deleted_at IS NULL 
               AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Generate the next sequential employee code for guards.
     * Codes are unique per organisation (composite unique key on organization_id + employee_code).
     * Uses MAX of the numeric suffix so gaps from deleted records never cause duplicates.
     */
    public function getNextEmployeeCode(int $organisationId): string
    {
        $stmt = $this->db->prepare(
            "SELECT employee_code FROM users 
             WHERE organization_id = :org_id AND role_id = 3
               AND employee_code LIKE 'GRD-%'
               AND employee_code REGEXP '^GRD-[0-9]+$'"
        );
        $stmt->execute(['org_id' => $organisationId]);
        $codes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $maxNum = 100;
        foreach ($codes as $code) {
            if (preg_match('/^GRD-(\d+)$/', $code, $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }

        return 'GRD-' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Retrieve all guards with their active site assignment
     */
    public function getGuardsWithDetails(int $organisationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT g.id as guard_id, g.user_id, g.photo_url, g.status as guard_status,
                    u.full_name, u.email, u.phone, u.employee_code, u.status as user_status,
                    u.last_login_at,
                    s.site_name, s.site_code,
                    cs.shift_name, cs.start_time, cs.end_time
             FROM {$this->table} g
             JOIN users u ON g.user_id = u.id
             LEFT JOIN contract_guard_assignments cga ON cga.guard_id = g.id AND cga.status = 0
             LEFT JOIN sites s ON cga.site_id = s.id
             LEFT JOIN contract_shifts cs ON cga.contract_shift_id = cs.id
             WHERE u.organization_id = :org_id 
               AND g.deleted_at IS NULL 
               AND u.deleted_at IS NULL
             ORDER BY u.full_name ASC"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }
}
