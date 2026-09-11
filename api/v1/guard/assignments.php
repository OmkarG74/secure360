<?php

declare(strict_types=1);

/**
 * Mobile Guard Assignments Endpoint
 * GET /api/v1/guard/assignments
 * Requires: Authorization: Bearer <token>
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'Guard duty assignments endpoint ready for Phase 2 implementation.',
    'status_code' => 200,
]);
