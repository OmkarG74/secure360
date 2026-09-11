<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Admin Role Middleware
 * Ensures the authenticated user possesses the 'admin' role
 */
class AdminMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        if (!Auth::check()) {
            $response->redirect('/login?redirect=' . urlencode($request->getUri()));
            return;
        }

        if (Auth::role() !== ROLE_ADMIN) {
            // If superadmin, redirect to superadmin dashboard; otherwise forbidden
            if (Auth::role() === ROLE_SUPERADMIN) {
                $response->redirect('/superadmin/dashboard');
                return;
            }

            $response->setStatusCode(403)->html(
                "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Access restricted to Organisation Administrators.</p></body></html>"
            );
        }
    }
}
