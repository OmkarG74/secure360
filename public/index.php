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

// Set timezone
date_default_timezone_set(config('app.timezone', 'UTC'));

// 5. Initialize session
\App\Core\Session::start();

// 6. Bootstrap Router and load route definitions
$router = new \App\Core\Router();

require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'superadmin.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';

// 7. Dispatch incoming Request
$request = new \App\Core\Request();
$response = new \App\Core\Response();

$router->dispatch($request, $response);
