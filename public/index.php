<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SECURE360 - FRONT CONTROLLER
 * ==============================================================================
 */

// Define application directory constants
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', __DIR__);

// 1. Lightweight .env loader (Core PHP without external dependencies)
$envFile = ROOT_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// 2. Load constants & helpers
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'helpers.php';

// 3. Register PSR-4 Autoloader
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
\App\Core\Autoloader::register(APP_PATH);

// Register Core class aliases for view templates
class_alias(\App\Core\Auth::class, 'Auth');
class_alias(\App\Core\Session::class, 'Session');
class_alias(\App\Core\View::class, 'View');

// 4. Configure error reporting based on environment
$debug = config('app.debug', false);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Set timezone (Strict Asia/Kolkata standard)
date_default_timezone_set(config('app.timezone', 'Asia/Kolkata'));

// 5. Initialize session
\App\Core\Session::start();

// 6. Bootstrap Router and load route definitions
$router = new \App\Core\Router();

require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'superadmin.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';

// 7. Dispatch incoming Request with production-safe error handling
$request = new \App\Core\Request();
$response = new \App\Core\Response();

try {
    $router->dispatch($request, $response);
} catch (\Throwable $e) {
    // Log the full exception and stack trace server-side (captured in Railway logs)
    error_log("[Secure360 Front Controller][FATAL] " . (string)$e);

    if ($debug) {
        // In local development, re-throw exception so full stack trace is visible to developer
        throw $e;
    }

    // In production, return clean, user-friendly response without leaking system internals
    if ($request->isJson() || str_starts_with($request->getUri(), '/api/')) {
        $response->setStatusCode(500)->json([
            'success' => false,
            'message' => 'An internal server error occurred. Please try again later.',
            'status_code' => 500,
        ], 500);
    } else {
        $response->setStatusCode(500)->html(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
            '<title>Service Unavailable - Secure360</title>' .
            '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:center;padding:80px 20px;background:#f8fafc;color:#1e293b;}' .
            'h1{font-size:1.75rem;color:#0f172a;margin-bottom:12px;}.card{max-width:480px;margin:0 auto;background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);border:1px solid #e2e8f0;}' .
            'p{color:#64748b;line-height:1.5;margin-bottom:24px;font-size:0.95rem;}.btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:500;font-size:0.9rem;}</style></head>' .
            '<body><div class="card"><h1>System Temporarily Unavailable</h1><p>We are experiencing a temporary system or database connection issue. Our engineering team has been notified. Please try again in a few moments.</p><a href="/" class="btn">Return to Safety</a></div></body></html>',
            500
        );
    }
}
