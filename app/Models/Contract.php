<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Contract Model
 * Table: contracts (in secure360_v2)
 * Binds Customer, Site, and Organization
 */
class Contract extends Model
{
    protected string $table = 'contracts';

    /**
     * Retrieve all contracts for an organization with customer and site details
     */
    public function allByTenant(int $organisationId, string $orderBy = 'c.created_at DESC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, cust.name as customer_name, cust.client_code,
                    s.site_name, s.site_code, s.site_address
             FROM {$this->table} c
             JOIN customers cust ON c.customer_id = cust.id
             JOIN sites s ON c.site_id = s.id
             WHERE c.organization_id = :org_id AND c.deleted_at IS NULL
             ORDER BY {$orderBy}"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Find single contract with complete relations
     */
    public function findByTenant(int $id, int $organisationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, cust.name as customer_name, cust.client_code,
                    s.site_name, s.site_code, s.site_address
             FROM {$this->table} c
             JOIN customers cust ON c.customer_id = cust.id
             JOIN sites s ON c.site_id = s.id
             WHERE c.id = :id AND c.organization_id = :org_id AND c.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'org_id' => $organisationId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
