<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Multi-Tenant Isolation Middleware
 * Ensures the authenticated user is scoped to a valid, active organisation tenant
 */
class TenantMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        // Superadmin operates globally across all tenants
        if (Auth::role() === ROLE_SUPERADMIN) {
            return;
        }

        $orgId = Auth::organisationId();

        if (empty($orgId)) {
            $response->setStatusCode(403)->html(
                "<!DOCTYPE html><html><head><title>Tenant Error - Secure360</title></head><body><h1>Tenant Error</h1><p>Your account is not assigned to an active organisation.</p></body></html>"
            );
        }
    }
}
