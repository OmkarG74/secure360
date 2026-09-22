<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ApiToken;
use App\Models\Guard;
use App\Models\User;

/**
 * Guard Mobile API Authentication Controller
 * Consumed by Flutter Mobile App for guard login and token management
 */
class GuardAuthController extends Controller
{
    /**
     * Guard Login API
     * POST /api/v1/auth/guard/login (or /api/v1/guard/login)
     */
    public function login(): void
    {
        $body = $this->request->getBody();
        $identifier = trim((string)($body['email'] ?? $body['employee_code'] ?? $body['badge_number'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $deviceName = (string)($body['device_name'] ?? 'Flutter Device');
        $deviceType = (string)($body['device_type'] ?? 'android');

        if (empty($identifier) || empty($password)) {
            $this->json([
                'success' => false,
                'message' => 'Email/Employee Code and Password are required',
                'data' => null,
                'status_code' => 422,
            ], 422);
            return;
        }

        $userModel = new User();
        // Support login by email or employee_code
        $user = $userModel->findByEmail($identifier);
        if (!$user) {
            // Try by employee_code
            $stmt = \App\Core\Database::getConnection()->prepare(
                "SELECT u.*, r.role_code, r.role_name, o.name as organization_name
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 LEFT JOIN organizations o ON u.organization_id = o.id
                 WHERE u.employee_code = :code AND u.deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['code' => $identifier]);
            $user = $stmt->fetch() ?: null;
        }

        if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
            $this->json([
                'success' => false,
                'message' => 'Invalid credentials or inactive guard account',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        // Verify that user role is 'guard'
        if ($user['role_code'] !== 'guard') {
            $this->json([
                'success' => false,
                'message' => 'Access denied: User does not have a Guard role',
                'data' => null,
                'status_code' => 403,
            ], 403);
            return;
        }

        $guardModel = new Guard();
        $guard = $guardModel->findByUserId((int)$user['id']);

        if (!$guard) {
            $this->json([
                'success' => false,
                'message' => 'Guard record not found for this account',
                'data' => null,
                'status_code' => 404,
            ], 404);
            return;
        }

        // Generate secure 64-character token
        $plainToken = bin2hex(random_bytes(32));
        $apiTokenModel = new ApiToken();
        $apiTokenModel->createToken((int)$user['id'], $plainToken, $deviceName, $deviceType, 30);

        // Update user last login
        $userModel->updateLastLogin((int)$user['id']);

        $guardProfile = [
            'id' => (int)$user['id'],
            'user_id' => (int)$user['id'],
            'guard_id' => (int)$guard['guard_id'],
            'name' => $user['full_name'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'employee_code' => $user['employee_code'],
            'organization_id' => (int)$user['organization_id'],
            'organization_name' => $user['organization_name'],
            'photo_url' => $guard['photo_url'] ?? null,
            'role' => $user['role_code'],
        ];

        $this->json([
            'success' => true,
            'message' => 'Guard login successful',
            'data' => [
                'token' => $plainToken,
                'user' => $guardProfile,
                'guard' => $guardProfile,
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * Guard Profile API
     * GET /api/v1/guard/profile
     */
    public function profile(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;

        if (!$guard) {
            $this->json([
                'success' => false,
                'message' => 'Unauthorized: Guard not authenticated',
                'data' => null,
                'status_code' => 401,
            ], 401);
            return;
        }

        $orgName = $guard['organization_name'] ?? null;
        if (empty($orgName) && !empty($guard['organization_id'])) {
            $db = \App\Core\Database::getConnection();
            $orgStmt = $db->prepare("SELECT name FROM organizations WHERE id = :id LIMIT 1");
            $orgStmt->execute(['id' => (int)$guard['organization_id']]);
            $orgName = $orgStmt->fetchColumn() ?: 'Apex Security';
        }

        $guardProfile = [
            'id' => (int)($guard['user_id'] ?? $guard['id'] ?? 0),
            'guard_id' => (int)$guard['guard_id'],
            'user_id' => (int)$guard['user_id'],
            'name' => $guard['full_name'],
            'full_name' => $guard['full_name'],
            'email' => $guard['email'],
            'phone' => $guard['phone'],
            'employee_code' => $guard['employee_code'],
            'organization_id' => (int)$guard['organization_id'],
            'organization_name' => $orgName,
            'photo_url' => $guard['photo_url'] ?? null,
            'role' => 'guard',
        ];

        $this->json([
            'success' => true,
            'message' => 'Guard profile retrieved successfully',
            'data' => array_merge($guardProfile, [
                'user' => $guardProfile,
                'guard' => $guardProfile,
            ]),
            'status_code' => 200,
        ]);
    }

    /**
     * Guard Logout / Revoke Token API
     * POST /api/v1/guard/logout
     */
    public function logout(): void
    {
        $token = $this->request->getBearerToken();
        if ($token) {
            (new ApiToken())->revokeToken($token);
        }

        $body = $this->request->getBody();
        $fcmToken = trim((string)($body['fcm_token'] ?? ''));
        if ($fcmToken !== '') {
            $guard = $GLOBALS['AUTH_GUARD'] ?? null;
            $userId = (int)($guard['user_id'] ?? $guard['id'] ?? 0);
            (new \App\Models\UserDeviceToken())->deactivateToken($fcmToken, $userId > 0 ? $userId : null);
        }

        $this->json([
            'success' => true,
            'message' => 'Logged out successfully, token revoked',
            'data' => null,
            'status_code' => 200,
        ]);
    }
}
