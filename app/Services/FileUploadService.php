<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Secure File Upload Service
 * Manages secure document, guard photo, and organisation logo uploads
 */
class FileUploadService
{
    /**
     * Upload and sanitize a file
     */
    public function upload(array $file, string $destinationSubfolder): string
    {
        // Placeholder for Phase 2: Validate MIME, size, sanitize name, move to storage/uploads
        return '';
    }
}
