<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SECURE360 - GUARD DUTY FLOW & VALIDATION TEST SUITE
 * ==============================================================================
 */

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register(__DIR__ . '/../app');
require_once __DIR__ . '/../app/Config/constants.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Controllers\Api\Guard\GuardAttendanceController;
use App\Controllers\Api\Guard\GuardLocationController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

$db = Database::getConnection();

// Mock response class capturing output
class TestResponse extends Response
{
    public array $lastData = [];
    public int $lastCode = 200;

    public function json(array $data, ?int $statusCode = null): void
    {
        $this->lastData = $data;
        $this->lastCode = $statusCode ?? 200;
    }

}

function makeRequest(array $body = []): Request
{
    $req = new Request();
    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('body');
    $prop->setAccessible(true);
    $prop->setValue($req, $body);
    return $req;
}

$testsPassed = 0;
$testsFailed = 0;

function assertCondition(bool $condition, string $label): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] {$label}\n";
        $testsPassed++;
    } else {
        echo " [FAIL] {$label}\n";
        $testsFailed++;
    }
}

echo "\n======================================================\n";
echo "SECURE360 DUTY FLOW INTEGRATION TEST RUNNER\n";
echo "======================================================\n\n";

// --- UNIT TESTS: Shift Window & Haversine Geofence Helpers ---
echo "--- 1. Testing Helper Functions ---\n";

// 1.1 Daytime shift early
$res = validate_shift_window('07:30:00', '08:00:00', '16:00:00');
assertCondition($res['allowed'] === false && $res['state'] === 'before_shift' && str_contains($res['message'], '8:00 AM'), 'Day shift early -> rejected with "starts at 8:00 AM"');

// 1.2 Daytime shift active
$res = validate_shift_window('10:15:00', '08:00:00', '16:00:00');
assertCondition($res['allowed'] === true && $res['state'] === 'active', 'Day shift within window -> allowed');

// 1.3 Daytime shift ended
$res = validate_shift_window('16:05:00', '08:00:00', '16:00:00');
assertCondition($res['allowed'] === false && $res['state'] === 'after_shift' && $res['message'] === 'Your assigned shift has ended.', 'Day shift after end -> rejected with "Your assigned shift has ended."');

// 1.4 Overnight shift late evening (active)
$res = validate_shift_window('23:30:00', '22:00:00', '06:00:00');
assertCondition($res['allowed'] === true && $res['state'] === 'active', 'Overnight shift late evening -> allowed');

// 1.5 Overnight shift early morning (active)
$res = validate_shift_window('04:45:00', '22:00:00', '06:00:00');
assertCondition($res['allowed'] === true && $res['state'] === 'active', 'Overnight shift early morning -> allowed');

// 1.6 Overnight shift ended (morning after)
$res = validate_shift_window('08:00:00', '22:00:00', '06:00:00');
assertCondition($res['allowed'] === false && $res['state'] === 'after_shift' && $res['message'] === 'Your assigned shift has ended.', 'Overnight shift midday -> rejected with "Your assigned shift has ended."');

// 1.7 Overnight shift upcoming (evening before)
$res = validate_shift_window('20:00:00', '22:00:00', '06:00:00');
assertCondition($res['allowed'] === false && $res['state'] === 'before_shift' && str_contains($res['message'], '10:00 PM'), 'Overnight shift evening before -> rejected with "starts at 10:00 PM"');

// 1.8 Geofence distance
$distZero = geo_distance_meters(18.5204303, 73.8567437, 18.5204303, 73.8567437);
assertCondition($distZero < 1.0, 'Same coordinates distance is 0m');

// Point ~50 meters away
$dist50m = geo_distance_meters(18.5204303, 73.8567437, 18.5208000, 73.8567437);
assertCondition($dist50m > 35 && $dist50m < 60, "Distance calculation is accurate (~41m: {$dist50m}m)");

// Point ~5 km away
$distFar = geo_distance_meters(18.5204303, 73.8567437, 18.5500000, 73.9000000);
assertCondition($distFar > 4000 && $distFar < 6000, "Distance calculation for far coordinates (~5.6km: {$distFar}m)");

// --- SETUP DATABASE TEST STATE ---
echo "\n--- 2. Setting up Guard Context & Test Fixtures ---\n";

// Authenticate Guard David (Guard ID: 1, User ID: 3, Org ID: 1)
$GLOBALS['AUTH_GUARD'] = [
    'guard_id' => 1,
    'user_id' => 3,
    'organization_id' => 1,
    'role_code' => 'guard',
];

// Clean open attendance records for guard 1
$db->exec("UPDATE attendance SET status = 1, check_out_at = NOW() WHERE guard_id = 1 AND status = 0");

// Get current site coordinates for Site 1
$siteRow = $db->query("SELECT latitude, longitude, site_name FROM sites WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$siteLat = (float)$siteRow['latitude'];
$siteLng = (float)$siteRow['longitude'];

// Create test selfies in database
$db->exec("INSERT INTO selfies (organization_id, guard_id, image_path, captured_at, verification_status, created_at) VALUES (1, 1, 'uploads/selfies/test_checkin.jpg', NOW(), 'verified', NOW())");
$testCheckinSelfieId = (int)$db->lastInsertId();

$db->exec("INSERT INTO selfies (organization_id, guard_id, image_path, captured_at, verification_status, created_at) VALUES (1, 1, 'uploads/selfies/test_checkout.jpg', NOW(), 'verified', NOW())");
$testCheckoutSelfieId = (int)$db->lastInsertId();

// Create a shift that has ended (e.g. 01:00:00 to 02:00:00)
$curDbTime = $db->query("SELECT CURTIME()")->fetchColumn();
echo " Current Database Clock: {$curDbTime}\n";

// --- TEST SCENARIOS ---
echo "\n--- 3. Testing Guard Attendance API Validations ---\n";

// Scenario A: Check-in rejected when shift has ended or not started
// Temporarily set shift to past hours
$origShift = $db->query("SELECT start_time, end_time FROM contract_shifts WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Scenario A: Check-in rejected when shift has ended
// Shift was 00:00:00 to 00:02:00 (which ended earlier today relative to current clock)
$db->exec("UPDATE contract_shifts SET start_time = '00:00:00', end_time = '00:02:00' WHERE id = 1");

$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();

assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'Your assigned shift has ended.'),
    "Scenario A: Check-in after shift end rejected with message: '{$resResp->lastData['message']}'"
);


// Scenario A2: Shift starts in future
$db->exec("UPDATE contract_shifts SET start_time = '23:59:00', end_time = '23:59:59' WHERE id = 1");
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();

assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'Your shift starts at'),
    "Scenario A2: Check-in before shift start rejected with message: '{$resResp->lastData['message']}'"
);

// Set shift to active 24h span (00:00:00 to 23:59:59) for remaining tests
$db->exec("UPDATE contract_shifts SET start_time = '00:00:00', end_time = '23:59:59' WHERE id = 1");

// Scenario C: During valid shift BUT outside geofence (5km away)
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat + 0.05, // ~5.5 km away
    'longitude' => $siteLng + 0.05,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();

assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'You are outside your assigned post area'),
    "Scenario C: Check-in outside geofence rejected with message: '{$resResp->lastData['message']}'"
);

// Scenario B: During valid shift and INSIDE geofence -> Allowed
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
    'address' => 'Metro Plaza - Main Gate',
]), $resResp);
$controller->checkIn();

$createdAttendanceId = $resResp->lastData['data']['attendance_id'] ?? null;
assertCondition(
    $resResp->lastCode === 201 && $resResp->lastData['success'] === true && $createdAttendanceId > 0,
    "Scenario B: Valid check-in inside geofence allowed (Attendance ID: {$createdAttendanceId})"
);

// Scenario J: Duplicate active check-in attempt -> Rejected with 409
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();

assertCondition(
    $resResp->lastCode === 409 && str_contains($resResp->lastData['message'], 'already has an active open check-in'),
    "Scenario J: Duplicate active check-in attempt rejected with 409 Conflict"
);

// Scenario E: Checkout without selfie -> Rejected with 422
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    // selfie_id missing
]), $resResp);
$controller->checkOut();

assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'fresh checkout selfie is required'),
    "Scenario E: Checkout without fresh selfie rejected with 422"
);

// Scenario J2: Checkout reusing check-in selfie -> Rejected with 422
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId, // reusing check-in selfie
]), $resResp);
$controller->checkOut();

assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'must not reuse the check-in selfie'),
    "Scenario J2: Checkout reusing check-in selfie rejected with message: '{$resResp->lastData['message']}'"
);

// Scenario J3: Guard submits fresh checkout selfie with attendance_id linked via GuardLocationController
$locResp = new TestResponse();
$locController = new GuardLocationController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'image_path' => 'uploads/selfies/test_fresh_checkout_flow.jpg',
]), $locResp);
$locController->submitSelfie();
$freshUploadedSelfieId = (int)($locResp->lastData['data']['selfie_id'] ?? 0);

assertCondition(
    $locResp->lastCode === 201 && $freshUploadedSelfieId > 0,
    "Scenario J3: Checkout selfie successfully uploaded via /api/v1/guard/selfie (Selfie ID: {$freshUploadedSelfieId})"
);

// Verify attendance.selfie_id was NOT overwritten and still holds check-in selfie
$attSelfieId = (int)$db->query("SELECT selfie_id FROM attendance WHERE id = {$createdAttendanceId}")->fetchColumn();
assertCondition(
    $attSelfieId === $testCheckinSelfieId,
    "Attendance record preserves original check-in selfie_id ({$testCheckinSelfieId}) and was not overwritten by checkout selfie upload"
);

// Verify check-in selfie reuse rejection still triggers if client sends old check-in selfie ID
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkOut();
assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'must not reuse the check-in selfie'),
    "Checkout with check-in selfie ID correctly rejected with 422 even after fresh selfie was uploaded"
);

// Scenario G: Checkout OUTSIDE geofence (5km away) -> Rejected with 422
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'latitude' => $siteLat + 0.05,
    'longitude' => $siteLng + 0.05,
    'selfie_id' => $testCheckoutSelfieId,
]), $resResp);
$controller->checkOut();

assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'], 'outside your assigned post area. Return to the assigned post'),
    "Scenario G: Checkout outside geofence rejected with message: '{$resResp->lastData['message']}'"
);

// Scenario F: Checkout INSIDE geofence with fresh selfie -> Allowed
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckoutSelfieId,
    'notes' => 'Duty ended; relieved by Sarah',
]), $resResp);
$controller->checkOut();

assertCondition(
    $resResp->lastCode === 200 && $resResp->lastData['success'] === true,
    "Scenario F: Checkout inside geofence with fresh selfie successfully completed"
);

// Verify database state after checkout
$attRow = $db->query("SELECT status, check_out_at, check_out_latitude, check_out_longitude FROM attendance WHERE id = {$createdAttendanceId}")->fetch(PDO::FETCH_ASSOC);
assertCondition(
    (int)$attRow['status'] === 1 && !empty($attRow['check_out_at']) && (float)$attRow['check_out_latitude'] > 0,
    "Attendance record in DB is updated to status 1 (completed) with check_out_at timestamp and GPS coordinates"
);

// Verify checkout selfie status in DB
$selfieRow = $db->query("SELECT attendance_id, verification_status FROM selfies WHERE id = {$testCheckoutSelfieId}")->fetch(PDO::FETCH_ASSOC);
assertCondition(
    (int)$selfieRow['attendance_id'] === $createdAttendanceId && $selfieRow['verification_status'] === 'checkout',
    "Checkout selfie row in selfies table is linked to attendance ID {$createdAttendanceId} with verification_status 'checkout'"
);

// Scenario K: Cross-guard protection: attempt to checkout with another guard ID
$GLOBALS['AUTH_GUARD']['guard_id'] = 999;
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $createdAttendanceId,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckoutSelfieId,
]), $resResp);
$controller->checkOut();

assertCondition(
    $resResp->lastCode === 404,
    "Scenario K: Another guard cannot check out attendance belonging to guard 1 (returns 404)"
);

// Restore original shift timings
$db->prepare("UPDATE contract_shifts SET start_time = :st, end_time = :et WHERE id = 1")->execute([
    'st' => $origShift['start_time'],
    'et' => $origShift['end_time'],
]);

// Restore Guard 1 context
$GLOBALS['AUTH_GUARD'] = [
    'guard_id' => 1,
    'user_id' => 3,
    'organization_id' => 1,
    'role_code' => 'guard',
];

// Scenario L: Check-in rejected when contract is expired
$db->exec("UPDATE contracts SET end_date = '2020-01-01' WHERE id = 1");
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();
assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'] ?? '', 'expired'),
    "Scenario L: Check-in rejected when contract is expired -> '{$resResp->lastData['message']}'"
);
$db->exec("UPDATE contracts SET end_date = '2026-12-31' WHERE id = 1");

// Scenario M: Check-in rejected when assignment is inactive (status = 1)
$db->exec("UPDATE contract_guard_assignments SET status = 1 WHERE id = 1");
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();
assertCondition(
    $resResp->lastCode === 422 && str_contains($resResp->lastData['message'] ?? '', 'inactive'),
    "Scenario M: Check-in rejected when assignment is inactive -> '{$resResp->lastData['message']}'"
);
$db->exec("UPDATE contract_guard_assignments SET status = 0 WHERE id = 1");

// Scenario N: Check-in rejected with 403 Forbidden when guard attempts arbitrary unassigned site
$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 9999,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();
assertCondition(
    $resResp->lastCode === 403,
    "Scenario N: Check-in to unassigned site rejected with 403 Forbidden (Code: {$resResp->lastCode})"
);

// Scenario P: Active guard can check out AFTER scheduled shift end (Requirement 4)
// Setup: set shift to currently active to allow check-in
$db->exec("UPDATE contract_shifts SET start_time = '00:00:00', end_time = '23:59:59' WHERE id = 1");
$db->exec("UPDATE attendance SET status = 1, check_out_at = NOW() WHERE guard_id = 1 AND status = 0");

$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'site_id' => 1,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $testCheckinSelfieId,
]), $resResp);
$controller->checkIn();
$newAttendanceId = (int)($resResp->lastData['data']['attendance_id'] ?? 0);

// Now shift ends (simulate time past shift end by setting shift to an ended hour, e.g. 00:00:00 to 00:01:00)
$db->exec("UPDATE contract_shifts SET start_time = '00:00:00', end_time = '00:01:00' WHERE id = 1");

// Fresh selfie for checkout
$db->exec("INSERT INTO selfies (organization_id, guard_id, image_path, captured_at, verification_status, created_at) VALUES (1, 1, 'uploads/selfies/test_overtime_checkout.jpg', NOW(), 'verified', NOW())");
$overtimeSelfieId = (int)$db->lastInsertId();

$resResp = new TestResponse();
$controller = new GuardAttendanceController(makeRequest([
    'attendance_id' => $newAttendanceId,
    'latitude' => $siteLat,
    'longitude' => $siteLng,
    'selfie_id' => $overtimeSelfieId,
    'notes' => 'Checking out 30 minutes after scheduled shift end',
]), $resResp);
$controller->checkOut();

assertCondition(
    $resResp->lastCode === 200 && $resResp->lastData['success'] === true,
    "Scenario P: Active guard successfully checks out AFTER scheduled shift has ended (Code: {$resResp->lastCode})"
);

// Clean up state
$db->prepare("UPDATE contract_shifts SET start_time = :st, end_time = :et WHERE id = 1")->execute([
    'st' => $origShift['start_time'],
    'et' => $origShift['end_time'],
]);
$db->exec("UPDATE attendance SET status = 1, check_out_at = NOW() WHERE id = {$newAttendanceId}");

echo "\n======================================================\n";
echo "TEST RESULTS SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "======================================================\n\n";

exit($testsFailed === 0 ? 0 : 1);
