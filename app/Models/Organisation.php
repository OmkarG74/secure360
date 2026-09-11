<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Organisation Model (Customer / Tenant Entity)
 * Represents individual security company organizations managed by Superadmin
 */
class Organisation extends Model
{
    protected string $table = 'organisations';

    /**
     * Find active organisation by code or slug
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
