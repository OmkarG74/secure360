<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Generic Role Verification Middleware
 */
class RoleMiddleware
{
    protected array $allowedRoles = [];

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(Request $request, Response $response): void
    {
        if (!Auth::check()) {
            $response->redirect('/login');
            return;
        }

        $userRole = Auth::role();
        if (!empty($this->allowedRoles) && !in_array($userRole, $this->allowedRoles, true)) {
            $response->setStatusCode(403)->html(
                "<!DOCTYPE html><html><head><title>403 Forbidden - Secure360</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8fafc;color:#1e293b;}h1{color:#e11d48;}</style></head><body><h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p><a href='/'>Return to Safety</a></body></html>"
            );
        }
    }
}
