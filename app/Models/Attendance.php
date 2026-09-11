<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Attendance Model
 * Table: attendance (in secure360_v2)
 * Manages guard check-in/out and duty logs
 */
class Attendance extends Model
{
    protected string $table = 'attendance';

    /**
     * Retrieve attendance records for an organization with guard and site details
     */
    public function allByTenant(int $organisationId, string $orderBy = 'att.check_in_at DESC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT att.*, 
                    u.full_name as guard_name, u.employee_code as guard_badge,
                    s.site_name, s.site_code
             FROM {$this->table} att
             JOIN guards g ON att.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             LEFT JOIN sites s ON att.site_id = s.id
             WHERE att.organization_id = :org_id
             ORDER BY {$orderBy}
             LIMIT 100"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve personal attendance history for an individual guard
     */
    public function getGuardHistory(int $guardId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT att.*, s.site_name, s.site_code 
                FROM {$this->table} att
                LEFT JOIN sites s ON att.site_id = s.id
                WHERE att.guard_id = :guard_id";

        $params = ['guard_id' => $guardId];

        if ($startDate !== null) {
            $sql .= " AND att.check_in_at >= :start_date";
            $params['start_date'] = $startDate . ' 00:00:00';
        }

        if ($endDate !== null) {
            $sql .= " AND att.check_in_at <= :end_date";
            $params['end_date'] = $endDate . ' 23:59:59';
        }

        $sql .= " ORDER BY att.check_in_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get active (open) attendance for a guard
     */
    public function getOpenAttendance(int $guardId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE guard_id = :guard_id AND status = 0 AND check_out_at IS NULL 
             ORDER BY check_in_at DESC 
             LIMIT 1"
        );
        $stmt->execute(['guard_id' => $guardId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
