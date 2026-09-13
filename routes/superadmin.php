<?php

declare(strict_types=1);

use App\Controllers\SuperAdmin\OrganisationController;
use App\Controllers\SuperAdmin\SettingsController;
use App\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\SuperAdminMiddleware;

/**
 * Superadmin Master Control Routes
 * Protected by Auth and SuperAdmin role middleware
 *
 * @var Router $router
 */

$router->group([
    'prefix' => '/superadmin',
    'middleware' => [
        AuthMiddleware::class,
        SuperAdminMiddleware::class,
    ],
], function (Router $router) {
    // 1. Superadmin Dashboard
    $router->get('/dashboard', [SuperAdminDashboardController::class, 'index']);

    // 2. Customers / Organisations Management (Multi-tenant accounts)
    $router->get('/organisations', [OrganisationController::class, 'index']);
    $router->get('/customers', [OrganisationController::class, 'index']);
    $router->get('/organisations/create', [OrganisationController::class, 'createForm']);
    $router->get('/customers/create', [OrganisationController::class, 'createForm']);
    $router->post('/organisations/create', [OrganisationController::class, 'store']);
    $router->post('/customers/create', [OrganisationController::class, 'store']);
    $router->get('/organisations/{id}/edit', [OrganisationController::class, 'editForm']);
    $router->get('/customers/{id}/edit', [OrganisationController::class, 'editForm']);
    $router->post('/organisations/{id}/edit', [OrganisationController::class, 'update']);
    $router->post('/customers/{id}/edit', [OrganisationController::class, 'update']);
    $router->post('/organisations/{id}/toggle-status', [OrganisationController::class, 'toggleStatus']);

    // 3. Platform Settings & Audit Logs
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings', [SettingsController::class, 'update']);
});
