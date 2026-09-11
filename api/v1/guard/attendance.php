<?php

declare(strict_types=1);

/**
 * Mobile Guard Attendance Endpoint
 * POST /api/v1/guard/attendance
 * Requires: Authorization: Bearer <token>
 * Actions: check-in, check-out
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'Guard attendance tracking endpoint ready for Phase 2 implementation.',
    'status_code' => 200,
]);
