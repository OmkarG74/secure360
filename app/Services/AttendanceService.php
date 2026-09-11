<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Attendance Service
 * Handles mobile API check-in, check-out, geolocation verification, and attendance audit logs
 */
class AttendanceService
{
    public function recordCheckIn(int $organisationId, int $guardId, int $siteId, array $geoData): int
    {
        // Placeholder for Phase 2: Validate site geofence, record check_in_time and location
        return 0;
    }

    public function recordCheckOut(int $organisationId, int $attendanceId, array $geoData): bool
    {
        // Placeholder for Phase 2: Record check_out_time and calculate total shift duration
        return true;
    }
}
