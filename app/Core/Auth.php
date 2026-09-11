<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Authentication and Multi-Tenant Identity Manager
 */
class Auth
{
    private const SESSION_USER_KEY = '_auth_user';

    /**
     * Check if a web user is logged in
     */
    public static function check(): bool
    {
        return Session::has(self::SESSION_USER_KEY);
    }

    /**
     * Get the authenticated user array
     */
    public static function user(): ?array
    {
        return Session::get(self::SESSION_USER_KEY);
    }

    /**
     * Get authenticated user ID
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    /**
     * Get authenticated user's role
     */
    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    /**
     * Get current tenant organisation ID
     * Superadmin has null (global scope), Admin and Guard belong to an organisation
     */
    public static function organisationId(): ?int
    {
        $user = self::user();
        return isset($user['organisation_id']) ? (int)$user['organisation_id'] : null;
    }

    /**
     * Log in a user into web session
     */
    public static function login(array $userData): void
    {
        // Never store sensitive credentials in session
        unset($userData['password'], $userData['password_hash']);
        Session::set(self::SESSION_USER_KEY, $userData);
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Log out current user from web session
     */
    public static function logout(): void
    {
        Session::remove(self::SESSION_USER_KEY);
        Session::destroy();
    }

    /**
     * Check if authenticated user has a specific role
     */
    public static function hasRole(string $role): bool
    {
        return self::role() === $role;
    }

    /**
     * Check if current user is Superadmin
     */
    public static function isSuperAdmin(): bool
    {
        return self::role() === ROLE_SUPERADMIN;
    }

    /**
     * Check if current user is Organisation Admin
     */
    public static function isAdmin(): bool
    {
        return self::role() === ROLE_ADMIN;
    }

    /**
     * Verify plain password against hash
     */
    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Generate secure password hash
     */
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
