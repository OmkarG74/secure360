<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * ContractShift Model
 * Table: contract_shifts (in secure360_v2)
 */
class ContractShift extends Model
{
    protected string $table = 'contract_shifts';

    /**
     * Retrieve active shifts for a specific contract
     */
    public function findByContract(int $contractId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE contract_id = :contract_id AND deleted_at IS NULL 
             ORDER BY start_time ASC"
        );
        $stmt->execute(['contract_id' => $contractId]);
        return $stmt->fetchAll();
    }
}
