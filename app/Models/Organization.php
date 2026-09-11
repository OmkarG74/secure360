<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Organization Model (Customer Tenant Entity)
 * Table: organizations (in secure360_v2)
 */
class Organization extends Model
{
    protected string $table = 'organizations';

    /**
     * Find active organization by unique organization code
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE organization_code = :code AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retrieve all non-deleted organizations for Superadmin
     */
    public function allActive(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE deleted_at IS NULL ORDER BY name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
