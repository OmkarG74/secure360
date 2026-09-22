<?php

declare(strict_types=1);

use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AttendanceController;
use App\Controllers\Admin\BillingController;
use App\Controllers\Admin\ClientSiteController;
use App\Controllers\Admin\ContractController;
use App\Controllers\Admin\GuardController;
use App\Controllers\Admin\NotificationController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\SettingsController;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;

/**
 * Organisation Admin Routes
 * Protected by Auth, Admin, and Tenant isolation middleware
 *
 * @var Router $router
 */

$router->group([
    'prefix' => '/admin',
    'middleware' => [
        AuthMiddleware::class,
        AdminMiddleware::class,
        TenantMiddleware::class,
    ],
], function (Router $router) {
    // 1. Dashboard
    $router->get('/dashboard', [AdminDashboardController::class, 'index']);

    // 2. Clients & Sites (One client can have multiple sites)
    $router->get('/clients-sites', [ClientSiteController::class, 'index']);
    $router->get('/clients', [ClientSiteController::class, 'index']);
    $router->get('/sites', [ClientSiteController::class, 'index']);
    $router->get('/clients/register', [ClientSiteController::class, 'registerForm']);
    $router->post('/clients/register', [ClientSiteController::class, 'storeClient']);
    $router->get('/clients/{id}/edit', [ClientSiteController::class, 'editClient']);
    $router->post('/clients/{id}/edit', [ClientSiteController::class, 'updateClient']);
    $router->post('/clients/{id}/delete', [ClientSiteController::class, 'deleteClient']);
    $router->post('/clients/{id}/sites', [ClientSiteController::class, 'addSiteToClient']);
    $router->post('/sites/{id}/delete', [ClientSiteController::class, 'deleteSite']);

    // 3. Guards Roster & Management (Strictly NO checkboxes on tables)
    $router->get('/guards', [GuardController::class, 'index']);
    $router->get('/guards/setup', [GuardController::class, 'setupForm']);
    $router->post('/guards/setup', [GuardController::class, 'storeGuard']);
    $router->get('/guards/{id}/edit', [GuardController::class, 'editForm']);
    $router->post('/guards/{id}/edit', [GuardController::class, 'updateGuard']);
    $router->post('/guards/{id}/delete', [GuardController::class, 'deleteGuard']);

    // 4. Contracts & Agreements
    $router->get('/contracts', [ContractController::class, 'index']);
    $router->get('/contracts/create', [ContractController::class, 'createForm']);
    $router->post('/contracts/create', [ContractController::class, 'storeContract']);
    $router->get('/contracts/{id}/edit', [ContractController::class, 'editForm']);
    $router->post('/contracts/{id}/edit', [ContractController::class, 'updateContract']);

    // 5. Attendance & Real-Time Tracking
    $router->get('/attendance', [AttendanceController::class, 'index']);
    $router->get('/attendance/{id}', [AttendanceController::class, 'show']);

    // 6. Operational Reports & Telemetry
    $router->get('/reports', [ReportController::class, 'index']);

    // 7. Modular Organisation Settings
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings', [SettingsController::class, 'update']);

    // 8. Notifications Popover API
    $router->get('/notifications', [NotificationController::class, 'index']);
    $router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    $router->post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // 9. Subscription & Billing Statements
    $router->get('/billing', [BillingController::class, 'index']);
    $router->get('/invoices/{id}/download', [BillingController::class, 'downloadInvoice']);
});
