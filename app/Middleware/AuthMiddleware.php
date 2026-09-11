<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Web Authentication Middleware
 * Ensures that incoming web request has an authenticated session
 */
class AuthMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        if (!Auth::check()) {
            $response->redirect('/login?error=session_expired');
        }
    }
}
