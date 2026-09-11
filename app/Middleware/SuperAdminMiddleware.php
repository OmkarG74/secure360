<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Superadmin Role Middleware
 * Ensures the authenticated user possesses the 'superadmin' role
 */
class SuperAdminMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        if (!Auth::check()) {
            $response->redirect('/login?redirect=' . urlencode($request->getUri()));
            return;
        }

        if (Auth::role() !== ROLE_SUPERADMIN) {
            $response->setStatusCode(403)->html(
                "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Access restricted to System Superadministrators.</p></body></html>"
            );
        }
    }
}
