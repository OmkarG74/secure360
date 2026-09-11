<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Customer Model (Client Entity)
 * Table: customers (in secure360_v2)
 * Relationship: 1 Organization -> Many Customers -> Many Sites
 */
class Customer extends Model
{
    protected string $table = 'customers';

    /**
     * Retrieve all active customers belonging to an organization
     */
    public function allByTenant(int $organisationId, string $orderBy = 'name ASC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE organization_id = :org_id AND deleted_at IS NULL 
             ORDER BY {$orderBy}"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve all sites associated with a customer
     */
    public function getSites(int $customerId, int $organisationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM sites 
             WHERE customer_id = :customer_id AND organization_id = :org_id AND deleted_at IS NULL 
             ORDER BY site_name ASC"
        );
        $stmt->execute(['customer_id' => $customerId, 'org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Find customer by ID with tenant check
     */
    public function findByTenant(int $id, int $organisationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL 
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'org_id' => $organisationId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
