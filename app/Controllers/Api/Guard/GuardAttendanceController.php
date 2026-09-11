<?php

declare(strict_types=1);

namespace App\Controllers\Api\Guard;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Attendance;
use PDO;

/**
 * Guard Attendance API Controller
 * Consumed by Flutter Mobile App for check-in, check-out, and duty logs
 */
class GuardAttendanceController extends Controller
{
    /**
     * Mark check-in
     * POST /api/v1/guard/attendance/check-in
     */
    public function checkIn(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $orgId = (int)$guard['organization_id'];

        $body = $this->request->getBody();
        $siteId = isset($body['site_id']) ? (int)$body['site_id'] : null;
        $assignmentId = isset($body['assignment_id']) ? (int)$body['assignment_id'] : null;
        $latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
        $longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;
        $address = (string)($body['address'] ?? '');
        $notes = (string)($body['notes'] ?? '');

        $selfieId = isset($body['selfie_id']) ? (int)$body['selfie_id'] : null;

        // Check if guard already has an open check-in
        $attendanceModel = new Attendance();
        $openAttendance = $attendanceModel->getOpenAttendance($guardId);
        if ($openAttendance) {
            $this->json([
                'success' => false,
                'message' => 'Guard already has an active open check-in. Check out first before checking in again.',
                'data' => [
                    'active_attendance_id' => $openAttendance['id'],
                    'checked_in_at' => $openAttendance['check_in_at'],
                ],
                'status_code' => 409,
            ], 409);
            return;
        }

        $db = Database::getConnection();

        // If site_id was not explicitly passed, attempt to deduce it from assignment
        if (empty($siteId) && !empty($assignmentId)) {
            $cgaStmt = $db->prepare("SELECT site_id FROM contract_guard_assignments WHERE id = :cga_id AND organization_id = :org_id LIMIT 1");
            $cgaStmt->execute(['cga_id' => $assignmentId, 'org_id' => $orgId]);
            $foundSite = $cgaStmt->fetchColumn();
            if ($foundSite) {
                $siteId = (int)$foundSite;
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO attendance 
             (organization_id, guard_id, assignment_id, site_id, check_in_at, 
              check_in_latitude, check_in_longitude, check_in_address, selfie_id, status, notes, created_at)
             VALUES 
             (:org_id, :guard_id, :assignment_id, :site_id, NOW(), 
              :lat, :lng, :addr, :selfie_id, 0, :notes, NOW())"
        );
        $stmt->execute([
            'org_id' => $orgId,
            'guard_id' => $guardId,
            'assignment_id' => $assignmentId,
            'site_id' => $siteId,
            'lat' => $latitude,
            'lng' => $longitude,
            'addr' => $address,
            'selfie_id' => $selfieId,
            'notes' => $notes,
        ]);

        $attendanceId = (int)$db->lastInsertId();

        $this->json([
            'success' => true,
            'message' => 'Attendance check-in recorded successfully',
            'data' => [
                'attendance_id' => $attendanceId,
                'guard_id' => $guardId,
                'site_id' => $siteId,
                'selfie_id' => $selfieId,
                'check_in_at' => date('Y-m-d H:i:s'),
                'status' => 'checked_in',
            ],
            'status_code' => 201,
        ], 201);
    }

    /**
     * Mark check-out
     * POST /api/v1/guard/attendance/check-out
     */
    public function checkOut(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $body = $this->request->getBody();

        $attendanceModel = new Attendance();
        $attendanceId = isset($body['attendance_id']) ? (int)$body['attendance_id'] : null;

        if (!$attendanceId) {
            // Auto-detect open attendance
            $open = $attendanceModel->getOpenAttendance($guardId);
            if (!$open) {
                $this->json([
                    'success' => false,
                    'message' => 'No active open check-in found to check out from.',
                    'data' => null,
                    'status_code' => 404,
                ], 404);
                return;
            }
            $attendanceId = (int)$open['id'];
        }

        $latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
        $longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;
        $address = (string)($body['address'] ?? '');
        $notes = (string)($body['notes'] ?? '');

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "UPDATE attendance 
             SET check_out_at = NOW(), 
                 check_out_latitude = :lat, 
                 check_out_longitude = :lng, 
                 check_out_address = :addr, 
                 status = 1, 
                 notes = CASE WHEN :notes_chk <> '' THEN CONCAT(IFNULL(notes, ''), ' | Checkout: ', :notes_val) ELSE notes END,
                 updated_at = NOW()
             WHERE id = :id AND guard_id = :guard_id"
        );
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'addr' => $address,
            'notes_chk' => $notes,
            'notes_val' => $notes,
            'id' => $attendanceId,
            'guard_id' => $guardId,
        ]);

        $this->json([
            'success' => true,
            'message' => 'Attendance check-out recorded successfully',
            'data' => [
                'attendance_id' => $attendanceId,
                'guard_id' => $guardId,
                'check_out_at' => date('Y-m-d H:i:s'),
                'status' => 'completed',
            ],
            'status_code' => 200,
        ]);
    }

    /**
     * View attendance history for the authenticated guard
     * GET /api/v1/guard/attendance/history
     */
    public function history(): void
    {
        $guard = $GLOBALS['AUTH_GUARD'] ?? null;
        if (!$guard) {
            $this->json(['success' => false, 'message' => 'Unauthorized', 'data' => null, 'status_code' => 401], 401);
            return;
        }

        $guardId = (int)$guard['guard_id'];
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');

        $attendanceModel = new Attendance();
        $records = $attendanceModel->getGuardHistory($guardId, $startDate, $endDate);

        $this->json([
            'success' => true,
            'message' => 'Attendance history retrieved successfully',
            'data' => [
                'records' => $records,
                'history' => $records,
            ],
            'status_code' => 200,
        ]);
    }
}
