<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Guard Operations Service
 * Handles guard registration, credential provisioning, and duty assignment
 */
class GuardService
{
    public function registerGuard(int $organisationId, array $guardData): int
    {
        // Placeholder for Phase 2: Create guard record, generate mobile credentials
        return 0;
    }

    public function assignToDuty(int $organisationId, int $guardId, int $siteId, array $shiftData): int
    {
        // Placeholder for Phase 2: Create assignment record
        return 0;
    }
}
