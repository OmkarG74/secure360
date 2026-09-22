<?php

declare(strict_types=1);

use App\Controllers\SuperAdmin\InvoiceController;
use App\Controllers\SuperAdmin\OrganisationController;
use App\Controllers\SuperAdmin\SettingsController;
use App\Controllers\SuperAdmin\SubscriptionController;
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

    // 2. Tenant Organisations Management
    $router->get('/organisations', [OrganisationController::class, 'index']);
    $router->get('/customers', [OrganisationController::class, 'index']);
    $router->get('/organisations/create', [OrganisationController::class, 'createForm']);
    $router->get('/customers/create', [OrganisationController::class, 'createForm']);
    $router->post('/organisations/create', [OrganisationController::class, 'store']);
    $router->post('/customers/create', [OrganisationController::class, 'store']);

    // Organisation Data Export (Excel / PDF) - Must precede dynamic {id} route
    $router->get('/organisations/export-excel', [OrganisationController::class, 'exportExcel']);
    $router->get('/organisations/export-pdf', [OrganisationController::class, 'exportPdf']);
    $router->get('/customers/export-excel', [OrganisationController::class, 'exportExcel']);
    $router->get('/customers/export-pdf', [OrganisationController::class, 'exportPdf']);

    // Organisation Details / Overview & Edit
    $router->get('/organisations/{id}', [OrganisationController::class, 'show']);
    $router->get('/customers/{id}', [OrganisationController::class, 'show']);
    $router->get('/organisations/{id}/edit', [OrganisationController::class, 'editForm']);
    $router->get('/customers/{id}/edit', [OrganisationController::class, 'editForm']);
    $router->post('/organisations/{id}/edit', [OrganisationController::class, 'update']);
    $router->post('/customers/{id}/edit', [OrganisationController::class, 'update']);
    $router->post('/organisations/{id}/toggle-status', [OrganisationController::class, 'toggleStatus']);
    $router->get('/organisations/{id}/toggle-status', [OrganisationController::class, 'toggleStatus']);

    // Organisation Admins Management
    $router->post('/organisations/{id}/admins/create', [OrganisationController::class, 'addAdmin']);
    $router->post('/organisations/{id}/admins/{adminId}/edit', [OrganisationController::class, 'updateAdmin']);
    $router->post('/organisations/{id}/admins/{adminId}/toggle-status', [OrganisationController::class, 'toggleAdminStatus']);
    $router->get('/organisations/{id}/admins/{adminId}/toggle-status', [OrganisationController::class, 'toggleAdminStatus']);

    // Legacy organisation subscription shortcuts (preserved for backwards compatibility)
    $router->get('/organisations/{id}/subscription', [OrganisationController::class, 'showSubscription']);
    $router->post('/organisations/{id}/subscription', [OrganisationController::class, 'updateSubscription']);
    $router->post('/organisations/{id}/renew', [OrganisationController::class, 'renewSubscription']);
    $router->get('/organisations/{id}/invoices', [OrganisationController::class, 'invoices']);

    // 3. Dedicated Subscriptions Module
    $router->get('/subscriptions', [SubscriptionController::class, 'index']);
    $router->get('/subscriptions/create', [SubscriptionController::class, 'createForm']);
    $router->post('/subscriptions/create', [SubscriptionController::class, 'store']);

    // Subscriptions Data Export (Excel / PDF) - Must precede dynamic {id} route
    $router->get('/subscriptions/export-excel', [SubscriptionController::class, 'exportExcel']);
    $router->get('/subscriptions/export-pdf', [SubscriptionController::class, 'exportPdf']);

    $router->get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    $router->get('/subscriptions/{id}/edit', [SubscriptionController::class, 'editForm']);
    $router->post('/subscriptions/{id}/edit', [SubscriptionController::class, 'update']);
    $router->post('/subscriptions/{id}/toggle-status', [SubscriptionController::class, 'toggleStatus']);
    $router->get('/subscriptions/{id}/toggle-status', [SubscriptionController::class, 'toggleStatus']);
    $router->post('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);

    // 4. Dedicated Invoices Module (Read/View-oriented)
    $router->get('/invoices', [InvoiceController::class, 'index']);
    $router->get('/invoices/export-excel', [InvoiceController::class, 'exportExcel']);
    $router->get('/invoices/export-pdf', [InvoiceController::class, 'exportPdf']);
    $router->get('/invoices/{id}', [InvoiceController::class, 'show']);
    $router->get('/invoices/{id}/download', [InvoiceController::class, 'download']);

    // 5. Platform Settings & Audit Logs
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings', [SettingsController::class, 'update']);
});
