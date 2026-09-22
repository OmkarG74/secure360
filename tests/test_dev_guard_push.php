<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SECURE360 DEVELOPMENT-ONLY FCM TEST RUNNER
 * ==============================================================================
 *
 * Sends a test push notification to a registered guard device using the
 * existing FirebaseNotificationService and the database's user_device_tokens table.
 *
 * Usage:
 *   php tests/test_dev_guard_push.php              (targets most recently active guard)
 *   php tests/test_dev_guard_push.php <user_id>    (targets specific guard user ID)
 *
 * Security Invariants:
 * - Blocked in production (APP_ENV=production).
 * - Never prints or exposes service-account private keys or raw JSON credentials.
 * - Targets only active tokens stored in database for verified guards.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');

// Load .env
$envFile = ROOT_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// 1. Ensure development environment only
$env = strtolower(trim((string)(getenv('APP_ENV') ?: 'development')));
if ($env === 'production') {
    fwrite(STDERR, "[ERROR] This test script is disabled in production environment.\n");
    exit(1);
}

// 2. Bootstrap application helpers & autoloader
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
\App\Core\Autoloader::register(APP_PATH);

use App\Core\Database;
use App\Models\UserDeviceToken;
use App\Services\FirebaseNotificationService;

echo "====================================================================\n";
echo "SECURE360 DEVELOPMENT FCM PUSH NOTIFICATION TEST\n";
echo "====================================================================\n\n";

try {
    $db = Database::getConnection();
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// 3. Resolve target guard(s) and active real device tokens
$targetUserId = null;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $targetUserId = (int)$argv[1];
}

$query = "
    SELECT 
        udt.id AS token_id,
        udt.user_id,
        u.full_name,
        u.email,
        u.employee_code,
        udt.fcm_token,
        udt.device_type,
        udt.device_name,
        udt.updated_at
    FROM user_device_tokens udt
    JOIN users u ON u.id = udt.user_id
    JOIN guards g ON g.user_id = u.id
    WHERE udt.is_active = 1
      AND udt.user_id != 1
      AND udt.fcm_token NOT LIKE 'dummy_fcm_token_%'
      AND udt.fcm_token NOT LIKE 'test_fcm_token_%'
";

$params = [];
if ($targetUserId !== null) {
    $query .= " AND udt.user_id = :uid";
    $params['uid'] = $targetUserId;
}

$query .= " ORDER BY udt.updated_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$validTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($validTokens)) {
    if ($targetUserId !== null) {
        fwrite(STDERR, "[ERROR] No active real device tokens found for Guard User ID {$targetUserId}.\n");
    } else {
        echo "[INFO] No active real device tokens currently found for guards in 'user_device_tokens'.\n";
        echo "       To test push notifications:\n";
        echo "       1. Launch the Flutter mobile app.\n";
        echo "       2. Log in as a guard.\n";
        echo "       3. The app will automatically register an FCM device token to the database.\n";
        echo "       4. Re-run this command: php tests/test_dev_guard_push.php\n\n";
    }
    exit(1);
}

// Group by user for display
$usersGrouped = [];
foreach ($validTokens as $t) {
    $uid = (int)$t['user_id'];
    if (!isset($usersGrouped[$uid])) {
        $usersGrouped[$uid] = [
            'user_id' => $uid,
            'name' => $t['full_name'],
            'employee_code' => $t['employee_code'],
            'devices' => 0,
        ];
    }
    $usersGrouped[$uid]['devices']++;
}

if (count($usersGrouped) === 1) {
    $primary = reset($usersGrouped);
    echo "Target Guard:\n";
    echo "  - User ID       : {$primary['user_id']}\n";
    echo "  - Name          : {$primary['name']}\n";
    echo "  - Employee Code : " . ($primary['employee_code'] ?? 'N/A') . "\n";
    echo "  - Active Devices: {$primary['devices']}\n\n";
} else {
    echo "Target Guards (" . count($usersGrouped) . " guards found):\n";
    foreach ($usersGrouped as $uid => $g) {
        echo "  - User ID: {$uid} | Name: {$g['name']} | Code: {$g['employee_code']} | Devices: {$g['devices']}\n";
    }
    echo "\n";
}

// 4. Notification payload specifications
$title = 'Secure360 Test Notification';
$body = 'FCM push notification is working successfully.';
$data = [
    'type' => 'test',
    'screen' => 'home',
    'entity_id' => '0',
];

echo "Notification Payload:\n";
echo "  - Title: {$title}\n";
echo "  - Body : {$body}\n";
echo "  - Data : " . json_encode($data) . "\n\n";

echo "Dispatching via FirebaseNotificationService (HTTP v1 RS256 OAuth2)...\n";

try {
    $fcm = new FirebaseNotificationService();
    $totalDevices = count($validTokens);
    $sentCount = 0;
    $failedCount = 0;
    $results = [];

    foreach ($validTokens as $tokenRow) {
        $token = $tokenRow['fcm_token'];
        $res = $fcm->sendToDevice($token, $title, $body, $data);
        $results[] = [
            'token' => $token,
            'user_id' => $tokenRow['user_id'],
            'user_name' => $tokenRow['full_name'],
            'device_name' => $tokenRow['device_name'],
            'res' => $res,
        ];
        if ($res['success']) {
            $sentCount++;
        } else {
            $failedCount++;
        }
    }

    echo "Dispatch Results:\n";
    echo "  - Total Devices Targeted: {$totalDevices}\n";
    echo "  - Successfully Sent     : {$sentCount}\n";
    echo "  - Delivery Failures     : {$failedCount}\n\n";

    foreach ($results as $item) {
        $token = $item['token'];
        $res = $item['res'];
        $prefix = substr($token, 0, 14);
        $length = strlen($token);
        $maskedToken = "{$prefix}... (length: {$length})";

        if ($res['success']) {
            echo "  [SUCCESS] Token: {$maskedToken}\n";
            echo "            Device: " . ($item['device_name'] ?? 'Android Device') . "\n";
            echo "            Message ID: " . ($res['message_id'] ?? 'N/A') . "\n";
        } else {
            echo "  [FAILED]  Token: {$maskedToken}\n";
            echo "            Error : " . ($res['error'] ?? 'Unknown error') . "\n";
            if (!empty($res['unregistered'])) {
                echo "            Status: Token expired/unregistered (automatically deactivated in DB)\n";
            }
        }
    }

    if ($sentCount > 0) {
        echo "\n[RESULT] Test notification delivered successfully!\n";
        echo "Check the Android device or emulator running Secure360 mobile.\n";
    } else {
        echo "\n[RESULT] Test dispatch completed but notification was not delivered.\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] FCM Service threw exception: " . $e->getMessage() . "\n");
    exit(1);
}
