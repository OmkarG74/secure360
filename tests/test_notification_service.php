<?php

declare(strict_types=1);

/**
 * SECURE360 CENTRAL NOTIFICATION MODULE INTEGRATION TEST
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');

require_once APP_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
\App\Core\Autoloader::register(APP_PATH);

use App\Core\Database;
use App\Models\Notification;
use App\Services\NotificationService;

echo "============================================================\n";
echo "SECURE360 CENTRAL NOTIFICATION SERVICE TEST RUNNER\n";
echo "============================================================\n\n";

$pdo = Database::getConnection();

// 1. Find a test guard in database
$stmt = $pdo->query(
    "SELECT g.id as guard_id, g.user_id, u.organization_id, u.full_name, u.employee_code 
     FROM guards g 
     JOIN users u ON g.user_id = u.id 
     WHERE g.status = 0 AND g.deleted_at IS NULL 
     ORDER BY g.id DESC LIMIT 1"
);
$guard = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guard) {
    echo "[FAIL] No active guard found in database to test with.\n";
    exit(1);
}

$guardId = (int)$guard['guard_id'];
$userId = (int)$guard['user_id'];
$orgId = (int)$guard['organization_id'];
echo "[INFO] Testing with Guard ID: {$guardId}, User ID: {$userId} ({$guard['full_name']})\n\n";

// Test 1: NotificationService::sendToUser()
echo "--- TEST 1: NotificationService::sendToUser ---\n";
$res1 = NotificationService::sendToUser(
    $userId,
    'Automated System Test',
    'This is a unit verification notice for Secure360 notification architecture.',
    'system_alert',
    ['screen' => 'notifications', 'test_id' => 'SYS-01'],
    [
        'priority' => 'high',
        'entity_type' => 'test',
        'entity_id' => '101',
        'organization_id' => $orgId,
    ]
);
echo "Result: Success=" . ($res1['success'] ? 'YES' : 'NO') . ", NotifID={$res1['notification_id']}\n";
assert($res1['success'] === true && $res1['notification_id'] > 0, "Test 1 Failed");
echo "[PASS] NotificationService::sendToUser successful.\n\n";

// Test 2: Verify in Database
echo "--- TEST 2: Database Record Verification ---\n";
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = :id");
$stmt->execute(['id' => $res1['notification_id']]);
$notifRow = $stmt->fetch(PDO::FETCH_ASSOC);
echo "DB Record: ID={$notifRow['id']}, Title='{$notifRow['title']}', Priority='{$notifRow['priority']}', DeliveryStatus='{$notifRow['delivery_status']}', IsRead={$notifRow['is_read']}\n";
assert($notifRow['priority'] === 'high', "Priority mismatch");
assert($notifRow['entity_type'] === 'test', "Entity type mismatch");
echo "[PASS] Database record properly populated.\n\n";

// Test 3: NotificationService::sendToGuard()
echo "--- TEST 3: NotificationService::sendToGuard ---\n";
$res2 = NotificationService::sendToGuard(
    $guardId,
    'Contract Guard Assignment Notice',
    'You have been allocated to Site Omega for Night Patrol.',
    'contract_assignment',
    ['screen' => 'assignment_details', 'contract_id' => '42'],
    ['priority' => 'critical']
);
echo "Result: Success=" . ($res2['success'] ? 'YES' : 'NO') . ", NotifID={$res2['notification_id']}\n";
assert($res2['success'] === true, "Test 3 Failed");
echo "[PASS] NotificationService::sendToGuard successful.\n\n";

// Test 4: NotificationService::sendToRole()
echo "--- TEST 4: NotificationService::sendToRole ---\n";
$res3 = NotificationService::sendToRole(
    'guard',
    $orgId,
    'Weather Advisory',
    'High winds expected tonight. Exercise caution on perimeter posts.',
    'guard_alert',
    ['screen' => 'notifications'],
    ['priority' => 'normal']
);
echo "Broadcast Result: Total Guards={$res3['total_users']}, Sent={$res3['successful_dispatches']}\n";
assert($res3['successful_dispatches'] > 0, "Test 4 Failed");
echo "[PASS] NotificationService::sendToRole successful.\n\n";

// Test 5: Mark as Read & Read All
echo "--- TEST 5: Model Read State Tracking ---\n";
$model = new Notification();
$markSingle = $model->markAsRead((int)$res1['notification_id'], $userId);
echo "Mark single read: " . ($markSingle ? 'YES' : 'NO') . "\n";
assert($markSingle === true, "Mark single failed");

$markAll = $model->markAllAsReadForUser($userId);
echo "Mark all read: " . ($markAll ? 'YES' : 'NO') . "\n";
$unreadCount = $model->getUnreadCountForUser($userId);
echo "Unread count for guard after markAll: {$unreadCount}\n";
assert($unreadCount === 0, "Unread count should be 0");
echo "[PASS] Read status management verified.\n\n";

// Test 6: Admin History Query
echo "--- TEST 6: Admin History Query & Filtering ---\n";
$adminHistory = $model->findForAdminHistory($orgId, ['type' => 'contract_assignment'], 10, 0);
echo "Admin history records found for 'contract_assignment': " . count($adminHistory) . "\n";
assert(!empty($adminHistory), "Should find at least 1 record");
$first = $adminHistory[0];
echo "First match: Title='{$first['title']}', Recipient='{$first['recipient_name']}', Status='{$first['delivery_status']}'\n";
echo "[PASS] Admin history query and joins verified.\n\n";

echo "============================================================\n";
echo "ALL NOTIFICATION SERVICE INTEGRATION TESTS PASSED (6/6)!\n";
echo "============================================================\n";
