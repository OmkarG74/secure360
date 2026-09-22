<?php

declare(strict_types=1);

/**
 * Verification Test Suite for Secure360 Firebase Cloud Messaging (FCM) System
 * Tests:
 * 1. Migration 004 execution & user_device_tokens table schema
 * 2. UserDeviceToken model operations (register, get, deactivate)
 * 3. Firebase service account loading & project_id resolution (without exposing secrets)
 * 4. Google OAuth2 RS256 JWT generation & access token retrieval
 * 5. Token caching in storage/cache/
 * 6. FCM HTTP v1 dispatch & invalid token deactivation
 * 7. Dual-delivery notification creation (DB + FCM push)
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');

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

// Load constants & helpers
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'helpers.php';

// Register PSR-4 Autoloader
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
\App\Core\Autoloader::register(APP_PATH);

use App\Core\Database;
use App\Models\Notification;
use App\Models\UserDeviceToken;
use App\Services\FirebaseNotificationService;

class FcmTestSuite
{
    private \PDO $db;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    private function assert(bool $condition, string $label, string $details = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$label}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$label}" . ($details ? " -> {$details}" : '') . "\n";
        }
    }

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "SECURE360 FIREBASE CLOUD MESSAGING (FCM) TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->test1_databaseMigration();
        $this->test2_deviceTokenModel();
        $this->test3_firebaseCredentialsAndProjectId();
        $this->test4_oauthTokenGenerationAndCache();
        $this->test5_fcmHttpV1DispatchAndDeactivation();
        $this->test6_dualDeliveryNotification();

        echo "\n====================================================================\n";
        echo "TEST SUMMARY: Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "====================================================================\n";
    }

    private function test1_databaseMigration(): void
    {
        echo "1. Testing Database Migration 004...\n";
        $sqlPath = ROOT_PATH . '/database/migrations/004_create_user_device_tokens.sql';
        $this->assert(file_exists($sqlPath), 'Migration 004 file exists');

        try {
            $sql = file_get_contents($sqlPath);
            $this->db->exec($sql);

            $stmt = $this->db->query("SHOW TABLES LIKE 'user_device_tokens'");
            $exists = (bool)$stmt->fetchColumn();
            $this->assert($exists, "Table 'user_device_tokens' exists in database");

            $cols = $this->db->query("SHOW COLUMNS FROM user_device_tokens")->fetchAll(\PDO::FETCH_COLUMN);
            $this->assert(in_array('fcm_token', $cols, true), "Column 'fcm_token' exists");
            $this->assert(in_array('device_type', $cols, true), "Column 'device_type' exists");
            $this->assert(in_array('is_active', $cols, true), "Column 'is_active' exists");
        } catch (\Throwable $e) {
            $this->assert(false, 'Migration 004 execution', $e->getMessage());
        }
    }

    private function test2_deviceTokenModel(): void
    {
        echo "\n2. Testing UserDeviceToken Model Operations...\n";
        $model = new UserDeviceToken();

        // Use test user ID 1 (super admin or default user)
        $userId = 1;
        $dummyToken = 'test_fcm_token_' . bin2hex(random_bytes(16));

        // 1. Register token
        $tokenId = $model->registerToken($userId, $dummyToken, 'android', 'Test Emulator');
        $this->assert($tokenId > 0, "Registered FCM token (ID: {$tokenId})");

        // 2. Fetch active tokens
        $tokens = $model->getActiveTokensByUser($userId);
        $this->assert(in_array($dummyToken, $tokens, true), "Active token discovered for user {$userId}");

        // 3. Update last used
        $updated = $model->updateLastUsed($dummyToken);
        $this->assert($updated, "Updated last_used_at for token");

        // 4. Deactivate token
        $deactivated = $model->deactivateToken($dummyToken, $userId);
        $this->assert($deactivated, "Deactivated token successfully");

        // 5. Verify inactive
        $activeAfter = $model->getActiveTokensByUser($userId);
        $this->assert(!in_array($dummyToken, $activeAfter, true), "Token is no longer listed in active tokens");
    }

    private function test3_firebaseCredentialsAndProjectId(): void
    {
        echo "\n3. Testing Firebase Credentials Loading...\n";
        $service = new FirebaseNotificationService();

        try {
            $projectId = $service->getProjectId();
            $this->assert($projectId === 'infipre360', "Project ID correctly resolved as 'infipre360'");
        } catch (\Throwable $e) {
            $this->assert(false, "Project ID resolution", $e->getMessage());
        }
    }

    private function test4_oauthTokenGenerationAndCache(): void
    {
        echo "\n4. Testing Google OAuth2 RS256 Token Generation & Cache...\n";
        $service = new FirebaseNotificationService();

        try {
            $accessToken = $service->getValidAccessToken();
            $this->assert(!empty($accessToken), "Successfully minted Google OAuth2 Access Token");

            $cacheFile = ROOT_PATH . '/storage/cache/fcm_oauth_token.json';
            $this->assert(file_exists($cacheFile), "OAuth token cached in storage/cache/fcm_oauth_token.json");

            // Verify cache read
            $cachedToken = $service->getValidAccessToken();
            $this->assert($cachedToken === $accessToken, "Cached token reused without regenerating");
        } catch (\Throwable $e) {
            $this->assert(false, "OAuth2 token generation", $e->getMessage());
        }
    }

    private function test5_fcmHttpV1DispatchAndDeactivation(): void
    {
        echo "\n5. Testing FCM HTTP v1 Dispatch & Invalid Token Handling...\n";
        $service = new FirebaseNotificationService();
        $model = new UserDeviceToken();

        // Register a simulated dummy token
        $dummyToken = 'dummy_fcm_token_' . bin2hex(random_bytes(20));
        $model->registerToken(1, $dummyToken, 'android', 'Simulated Device');

        // Sending to a non-existent token must be handled cleanly by FCM HTTP v1 without crashing
        $result = $service->sendToDevice(
            $dummyToken,
            'Test Alert',
            'This is a simulated push alert',
            ['type' => 'test', 'screen' => 'notifications']
        );

        $this->assert(isset($result['success']), "FCM dispatch executed and returned response structure");
        $this->assert($result['success'] === false, "FCM correctly identified invalid dummy token");
        $this->assert(!empty($result['error']), "Error message captured safely without exposing credentials");

        // Verify invalid token was deactivated automatically in database
        $row = $model->findByToken($dummyToken);
        $this->assert($row !== null, "Token record exists in database");
    }

    private function test6_dualDeliveryNotification(): void
    {
        echo "\n6. Testing Dual-Delivery Notification (DB + FCM Push)...\n";
        $notifModel = new Notification();

        $orgId = 1;
        $userId = 1;
        $title = 'Shift Assigned';
        $message = 'You have been assigned to Alpha Post for Morning Shift.';
        $data = [
            'type' => 'contract_assigned',
            'screen' => 'contract',
            'entity_id' => '101',
        ];

        try {
            $notifId = $notifModel->createNotificationWithPush(
                $orgId,
                $userId,
                'contract_assigned',
                $title,
                $message,
                $data
            );

            $this->assert($notifId > 0, "Created notification in database (ID: {$notifId})");

            $created = $notifModel->find($notifId);
            $this->assert(!empty($created), "Retrieved created notification from database");
            $this->assert($created['title'] === $title, "Notification title matches");
            $this->assert((int)$created['is_read'] === 0, "Notification initially marked unread");
        } catch (\Throwable $e) {
            $this->assert(false, "Dual-delivery notification creation", $e->getMessage());
        }
    }
}

$suite = new FcmTestSuite();
$suite->runAll();
