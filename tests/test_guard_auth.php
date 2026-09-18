<?php
// Test Guard Login
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/guard/login';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

// Feed JSON input into php://input simulation
$payload = json_encode([
    'email' => 'guard@apexsecurity.com',
    'password' => 'password123',
    'device_name' => 'Pixel 8 Pro (Emulator)',
    'device_type' => 'android',
]);

// Since php://input is read-only in CLI, we test controller directly or mock request
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register(__DIR__ . '/../app');
require_once __DIR__ . '/../app/Config/constants.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

$request = new \App\Core\Request();
$reflection = new \ReflectionClass($request);
$bodyProp = $reflection->getProperty('body');
$bodyProp->setAccessible(true);
$bodyProp->setValue($request, json_decode($payload, true));

$response = new class extends \App\Core\Response {
    public function json(array $data, ?int $statusCode = null): void {
        echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL;
    }

};

$controller = new \App\Controllers\Api\Guard\GuardAuthController($request, $response);
$controller->login();
