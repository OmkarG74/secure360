<?php
// Test Guard Assignments using the generated token
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register(__DIR__ . '/../app');
require_once __DIR__ . '/../app/Config/constants.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

$token = '90edb394a98f2fb7b56cdf506157352af1c049b602a29c82b30383699c20d9bc';

// Test ApiToken validation
$apiTokenModel = new \App\Models\ApiToken();
$tokenRecord = $apiTokenModel->findValidToken($token);

if ($tokenRecord) {
    echo "Token successfully validated in api_tokens table!\n";
    $guardModel = new \App\Models\Guard();
    $guard = $guardModel->findByUserId((int)$tokenRecord['user_id']);
    $GLOBALS['AUTH_GUARD'] = array_merge($tokenRecord, $guard);

    $assignmentModel = new \App\Models\Assignment();
    $assignments = $assignmentModel->findByGuard((int)$guard['guard_id'], (int)$guard['organization_id']);
    echo "Guard Assignments:\n" . json_encode($assignments, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo "Token validation failed!\n";
}
