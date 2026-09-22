<?php

declare(strict_types=1);

use App\Controllers\Api\Guard\GuardAttendanceController;
use App\Controllers\Api\Guard\GuardAuthController;
use App\Controllers\Api\Guard\GuardDutyController;
use App\Controllers\Api\Guard\GuardLocationController;
use App\Controllers\Api\HealthController;
use App\Core\Router;
use App\Middleware\ApiAuthMiddleware;

/**
 * Mobile Guard REST API Routes
 * Consumed exclusively by Flutter Mobile Application
 *
 * @var Router $router
 */

$router->group([
    'prefix' => '/api/v1',
], function (Router $router) {

    // 1. Health check & timezone diagnostic endpoints
    $router->get('/health', [HealthController::class, 'check']);
    $router->get('/timezone-diagnostic', [HealthController::class, 'timezoneDiagnostic']);

    // 2. Public Guard Authentication (Login)
    $router->post('/auth/guard/login', [GuardAuthController::class, 'login']);
    $router->post('/guard/login', [GuardAuthController::class, 'login']);

    // 3. Protected Guard Mobile Endpoints (Bearer Token Required)
    $router->group([
        'middleware' => [ApiAuthMiddleware::class],
    ], function (Router $router) {
        // Auth & Profile
        $router->post('/guard/logout', [GuardAuthController::class, 'logout']);
        $router->get('/guard/profile', [GuardAuthController::class, 'profile']);

        // Duty Rosters & Site Assignments
        $router->get('/guard/assignments', [GuardDutyController::class, 'assignments']);
        $router->get('/guard/sites/{id}', [GuardDutyController::class, 'siteDetails']);

        // Real-Time Attendance Operations
        $router->post('/guard/attendance/check-in', [GuardAttendanceController::class, 'checkIn']);
        $router->post('/guard/attendance/check-out', [GuardAttendanceController::class, 'checkOut']);
        $router->get('/guard/attendance/history', [GuardAttendanceController::class, 'history']);

        // Live Telemetry & Field Evidence
        $router->post('/guard/location', [GuardLocationController::class, 'submitLocation']);
        $router->post('/guard/selfie', [GuardLocationController::class, 'submitSelfie']);

        // Notifications
        $router->get('/guard/notifications', [GuardLocationController::class, 'notifications']);
    });
});
