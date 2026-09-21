<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Site Model
 * Table: sites (in secure360_v2)
 * Relationship: Belongs to Customer and Organization
 */
class Site extends Model
{
    protected string $table = 'sites';

    /**
     * Retrieve all active sites belonging to an organization
     */
    public function allByTenant(int $organisationId, string $orderBy = 'site_name ASC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.name as customer_name, c.client_code
             FROM {$this->table} s
             JOIN customers c ON s.customer_id = c.id
             WHERE s.organization_id = :org_id AND s.deleted_at IS NULL
             ORDER BY s.{$orderBy}"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve sites for a specific customer
     */
    public function findByCustomer(int $customerId, int $organisationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE customer_id = :customer_id AND organization_id = :org_id AND deleted_at IS NULL 
             ORDER BY site_name ASC"
        );
        $stmt->execute(['customer_id' => $customerId, 'org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Find single site with customer details
     */
    public function findByTenant(int $id, int $organisationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.name as customer_name, c.client_code
             FROM {$this->table} s
             JOIN customers c ON s.customer_id = c.id
             WHERE s.id = :id AND s.organization_id = :org_id AND s.deleted_at IS NULL 
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'org_id' => $organisationId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Generate the next sequential site code for an organisation.
     * Codes are unique per organisation (composite unique key on organization_id + site_code).
     * Uses MAX of the numeric suffix so gaps from deleted records never cause duplicates.
     */
    public function getNextSiteCode(int $organisationId): string
    {
        $stmt = $this->db->prepare(
            "SELECT site_code FROM {$this->table}
             WHERE organization_id = :org_id
               AND site_code LIKE 'SITE-%'
               AND site_code REGEXP '^SITE-[0-9]+$'"
        );
        $stmt->execute(['org_id' => $organisationId]);
        $codes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $maxNum = 100;
        foreach ($codes as $code) {
            if (preg_match('/^SITE-(\d+)$/', $code, $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }

        return 'SITE-' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
    }
}

