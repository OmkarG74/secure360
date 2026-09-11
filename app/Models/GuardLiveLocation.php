<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * GuardLiveLocation Model
 * Table: guard_live_locations (in secure360_v2)
 */
class GuardLiveLocation extends Model
{
    protected string $table = 'guard_live_locations';

    /**
     * Record guard GPS coordinates
     */
    public function recordLocation(
        int $organisationId,
        int $guardId,
        float $latitude,
        float $longitude,
        ?float $accuracy = null,
        ?string $address = null,
        ?int $assignmentId = null,
        ?int $attendanceId = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} 
             (organization_id, guard_id, assignment_id, attendance_id, latitude, longitude, accuracy_meters, address, recorded_at, created_at)
             VALUES (:org_id, :guard_id, :assignment_id, :attendance_id, :lat, :lng, :acc, :addr, NOW(), NOW())"
        );
        $stmt->execute([
            'org_id' => $organisationId,
            'guard_id' => $guardId,
            'assignment_id' => $assignmentId,
            'attendance_id' => $attendanceId,
            'lat' => $latitude,
            'lng' => $longitude,
            'acc' => $accuracy,
            'addr' => $address,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
