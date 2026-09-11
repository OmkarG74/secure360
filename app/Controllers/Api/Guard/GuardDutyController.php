<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Models\Assignment;
use App\Models\Site;

/**
 * Guard Duty & Assignment API Controller
 * Consumed by Flutter Mobile App for duty rosters, assigned sites, and schedules
 */
class GuardDutyController extends Controller
{
    /**
     * Get assigned duties & shifts
     * GET /api/v1/guard/assignments
     */
    public function assignments(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $orgId = (int)$guard['organization_id'];

        $assignmentModel = new Assignment();
        $assignments = $assignmentModel->findByGuard($guardId, $orgId);

        $this->json([
            'success' => true,
            'message' => 'Duty assignments retrieved successfully',
            'data' => [
                'assignments' => $assignments,
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * Get details for an assigned site
     * GET /api/v1/guard/sites/{id}
     */
    public function siteDetails(array $params = []): void
    {
        $siteId = (int)($params['id'] ?? 0);
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;

        if (!$guard || $siteId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid site requested', 'status_code' => 400], 400);
            return;
        }

        $siteModel = new Site();
        $site = $siteModel->findByTenant($siteId, (int)$guard['organization_id']);

        if (!$site) {
            $this->json(['success' => false, 'message' => 'Site not found', 'status_code' => 404], 404);
            return;
        }

        $this->json([
            'success' => true,
            'message' => 'Site details retrieved successfully',
            'data' => [
                'site' => $site,
            ],
            'status_code' => 200,
        ]);
    }
}
