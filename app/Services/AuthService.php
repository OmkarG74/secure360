<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Authentication Service
 * Manages credential verification, session lifecycle, and token generation
 */
class AuthService
{
    public function authenticateWeb(string $email, string $password): ?array
    {
        // Placeholder for Phase 2 implementation
        return null;
    }

    public function authenticateGuardApi(string $badgeOrPhone, string $pinOrPassword): ?array
    {
        // Placeholder for Phase 2 mobile API token generation
        return null;
    }
}
