<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Selfie Model
 * Table: selfies (in secure360_v2)
 */
class Selfie extends Model
{
    protected string $table = 'selfies';

    public function recordSelfie(
        int $organisationId,
        int $guardId,
        string $imagePath,
        ?int $attendanceId = null,
        ?string $verificationStatus = 'verified'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} 
             (organization_id, guard_id, attendance_id, image_path, captured_at, verification_status, created_at)
             VALUES (:org_id, :guard_id, :attendance_id, :path, NOW(), :status, NOW())"
        );
        $stmt->execute([
            'org_id' => $organisationId,
            'guard_id' => $guardId,
            'attendance_id' => $attendanceId,
            'path' => $imagePath,
            'status' => $verificationStatus,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
