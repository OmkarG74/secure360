<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Site Operations Service
 * Manages physical sites assigned to clients within an organisation
 */
class SiteService
{
    public function createSite(int $organisationId, int $clientId, array $siteData): int
    {
        // Placeholder for Phase 2: Create site linked to client and organisation
        return 0;
    }
}
