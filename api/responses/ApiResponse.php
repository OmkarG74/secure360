<?php

declare(strict_types=1);

namespace Api\Responses;

/**
 * Standardized API Response Formatter
 * Used by all Secure360 REST endpoints
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status_code' => $statusCode,
            'timestamp' => time(),
        ];
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, array $errors = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'status_code' => $statusCode,
            'timestamp' => time(),
        ];
    }
}
