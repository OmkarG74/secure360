<?php

declare(strict_types=1);

/**
 * Mobile Guard Authentication Endpoint
 * POST /api/v1/guard/auth
 * Flutter Request: { "badge_number": "...", "pin": "..." }
 */

header('Content-Type: application/json; charset=utf-8');

// Phase 2 implementation placeholder
echo json_encode([
    'success' => true,
    'message' => 'Guard authentication endpoint ready for Phase 2 implementation.',
    'status_code' => 200,
]);
