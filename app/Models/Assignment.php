<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Assignment Model
 * Table: contract_guard_assignments (in secure360_v2)
 * Allocates a Guard to a Site, Contract, and Shift
 */
class Assignment extends Model
{
    protected string $table = 'contract_guard_assignments';

    /**
     * Retrieve active assignments for a specific guard
     */
    public function findByGuard(int $guardId, ?int $organisationId = null): array
    {
        $sql = "SELECT a.*, 
                       s.site_name, s.site_code, s.site_address, s.latitude, s.longitude,
                       cs.shift_name, cs.shift_code, cs.start_time, cs.end_time,
                       c.contract_code, cust.name as customer_name
                FROM {$this->table} a
                JOIN sites s ON a.site_id = s.id
                JOIN contract_shifts cs ON a.contract_shift_id = cs.id
                JOIN contracts c ON a.contract_id = c.id
                JOIN customers cust ON c.customer_id = cust.id
                WHERE a.guard_id = :guard_id AND a.deleted_at IS NULL";

        $params = ['guard_id' => $guardId];

        if ($organisationId !== null) {
            $sql .= " AND c.organization_id = :org_id";
            $params['org_id'] = $organisationId;
        }

        $sql .= " ORDER BY a.status ASC, cs.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve all assignments scoped to an organization
     */
    public function allByTenant(int $organisationId, string $orderBy = 'a.created_at DESC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, 
                    u.full_name as guard_name, u.employee_code as guard_badge,
                    s.site_name, s.site_code,
                    cs.shift_name, cs.start_time, cs.end_time,
                    c.contract_code, cust.name as customer_name
             FROM {$this->table} a
             JOIN guards g ON a.guard_id = g.id
             JOIN users u ON g.user_id = u.id
             JOIN sites s ON a.site_id = s.id
             JOIN contract_shifts cs ON a.contract_shift_id = cs.id
             JOIN contracts c ON a.contract_id = c.id
             JOIN customers cust ON c.customer_id = cust.id
             WHERE c.organization_id = :org_id AND a.deleted_at IS NULL
             ORDER BY {$orderBy}"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }
}
