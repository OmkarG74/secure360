<?php

declare(strict_types=1);

use App\Controllers\Auth\AuthController;
use App\Controllers\Public\HomeController;
use App\Controllers\Public\RouteDispatcherController;
use App\Core\Router;

/**
 * Public Web Routes, Authentication Entry Points & Role-Based Dispatchers
 *
 * @var Router $router
 */

// 1. Public landing & informational routes
$router->get('/', [HomeController::class, 'index']);

// 2. Authentication routes (Superadmin & Admin shared auth entry point)
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);

// Explicit support for /superadmin/login (routes to shared login flow without 404)
$router->get('/superadmin/login', [AuthController::class, 'showLogin']);
$router->post('/superadmin/login', [AuthController::class, 'login']);

// Logout routes (supports both POST and GET)
$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout']);

// 3. Central Role-Based Shortcut Routes
$router->get('/dashboard', [RouteDispatcherController::class, 'dashboard']);
$router->get('/customers', [RouteDispatcherController::class, 'customers']);
$router->get('/clients', [RouteDispatcherController::class, 'clients']);
$router->get('/sites', [RouteDispatcherController::class, 'sites']);
$router->get('/guards', [RouteDispatcherController::class, 'guards']);
$router->get('/contracts', [RouteDispatcherController::class, 'contracts']);
$router->get('/attendance', [RouteDispatcherController::class, 'attendance']);
$router->get('/reports', [RouteDispatcherController::class, 'reports']);
$router->get('/settings', [RouteDispatcherController::class, 'settings']);
