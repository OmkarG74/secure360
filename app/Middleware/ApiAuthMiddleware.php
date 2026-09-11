<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Models\Guard;

/**
 * Mobile Guard REST API Authentication Middleware
 * Validates Authorization: Bearer <token> against api_tokens table in secure360_v2
 */
class ApiAuthMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        $token = $request->getBearerToken();

        if (empty($token)) {
            $response->json([
                'success' => false,
                'message' => 'Unauthorized: Missing Bearer authorization token',
                'status_code' => 401,
            ], 401);
            exit;
        }

        $apiTokenModel = new ApiToken();
        $tokenRecord = $apiTokenModel->findValidToken($token);

        if (!$tokenRecord) {
            $response->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or expired Bearer token',
                'status_code' => 401,
            ], 401);
            exit;
        }

        // Verify that this token belongs to a guard
        if ($tokenRecord['role_code'] !== 'guard') {
            $response->json([
                'success' => false,
                'message' => 'Forbidden: API token does not belong to a security guard',
                'status_code' => 403,
            ], 403);
            exit;
        }

        // Fetch guard details
        $guardModel = new Guard();
        $guard = $guardModel->findByUserId((int)$tokenRecord['user_id']);

        if (!$guard) {
            $response->json([
                'success' => false,
                'message' => 'Unauthorized: Guard profile not found',
                'status_code' => 401,
            ], 401);
            exit;
        }

        // Attach authenticated guard data into global context for downstream controllers
        $GLOBALS['AUTH_GUARD'] = array_merge($tokenRecord, $guard);
    }
}
