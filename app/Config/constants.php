<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SECURE360 SYSTEM CONSTANTS
 * Aligned with secure360_v2 database schema & roles
 * ==============================================================================
 */

// Application Role Codes (Matches `roles.role_code`)
if (!defined('ROLE_SUPERADMIN')) {
    define('ROLE_SUPERADMIN', 'super_admin');
}
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin');
}
if (!defined('ROLE_GUARD')) {
    define('ROLE_GUARD', 'guard');
}

// Entity Status Codes (Matches tinyint: 0=active, 1=inactive, 2=deleted)
if (!defined('STATUS_ACTIVE')) {
    define('STATUS_ACTIVE', 0);
}
if (!defined('STATUS_INACTIVE')) {
    define('STATUS_INACTIVE', 1);
}
if (!defined('STATUS_DELETED')) {
    define('STATUS_DELETED', 2);
}

// Subscription Status Codes (Matches tinyint: 0=active, 1=expiring_soon, 2=expired, 3=suspended, 4=cancelled)
if (!defined('SUBSCRIPTION_ACTIVE')) {
    define('SUBSCRIPTION_ACTIVE', 0);
}
if (!defined('SUBSCRIPTION_EXPIRING_SOON')) {
    define('SUBSCRIPTION_EXPIRING_SOON', 1);
}
if (!defined('SUBSCRIPTION_EXPIRED')) {
    define('SUBSCRIPTION_EXPIRED', 2);
}
if (!defined('SUBSCRIPTION_SUSPENDED')) {
    define('SUBSCRIPTION_SUSPENDED', 3);
}
if (!defined('SUBSCRIPTION_CANCELLED')) {
    define('SUBSCRIPTION_CANCELLED', 4);
}

// Attendance Status Codes (Matches tinyint: 0=open, 1=completed, 2=cancelled)
if (!defined('ATTENDANCE_STATUS_OPEN')) {
    define('ATTENDANCE_STATUS_OPEN', 0);
}
if (!defined('ATTENDANCE_STATUS_COMPLETED')) {
    define('ATTENDANCE_STATUS_COMPLETED', 1);
}
if (!defined('ATTENDANCE_STATUS_CANCELLED')) {
    define('ATTENDANCE_STATUS_CANCELLED', 2);
}

// HTTP Response Status Codes
if (!defined('HTTP_OK')) {
    define('HTTP_OK', 200);
}
if (!defined('HTTP_CREATED')) {
    define('HTTP_CREATED', 201);
}
if (!defined('HTTP_BAD_REQUEST')) {
    define('HTTP_BAD_REQUEST', 400);
}
if (!defined('HTTP_UNAUTHORIZED')) {
    define('HTTP_UNAUTHORIZED', 401);
}
if (!defined('HTTP_FORBIDDEN')) {
    define('HTTP_FORBIDDEN', 403);
}
if (!defined('HTTP_NOT_FOUND')) {
    define('HTTP_NOT_FOUND', 404);
}
if (!defined('HTTP_UNPROCESSABLE_ENTITY')) {
    define('HTTP_UNPROCESSABLE_ENTITY', 422);
}
if (!defined('HTTP_INTERNAL_SERVER_ERROR')) {
    define('HTTP_INTERNAL_SERVER_ERROR', 500);
}
