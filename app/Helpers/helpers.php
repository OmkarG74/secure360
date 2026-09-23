<?php

declare(strict_types=1);

// Enforce application timezone standard: Asia/Kolkata (IST, UTC+05:30)
if (date_default_timezone_get() !== 'Asia/Kolkata') {
    date_default_timezone_set('Asia/Kolkata');
}

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;

if (!function_exists('e')) {
    /**
     * Escape HTML special characters
     */
    function e(mixed $value): string
    {
        return View::e($value);
    }
}

if (!function_exists('config')) {
    /**
     * Retrieve configuration value by dot notation (e.g. 'app.name')
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];
        $parts = explode('.', $key);
        $file = $parts[0];

        if (!isset($configs[$file])) {
            $path = dirname(__DIR__) . "/Config/{$file}.php";
            if (file_exists($path)) {
                $configs[$file] = require $path;
            } else {
                $configs[$file] = [];
            }
        }

        $current = $configs[$file];
        for ($i = 1; $i < count($parts); $i++) {
            if (!is_array($current) || !array_key_exists($parts[$i], $current)) {
                return $default;
            }
            $current = $current[$parts[$i]];
        }

        return $current;
    }
}

if (!function_exists('base_path_url')) {
    /**
     * Determine the deployment subdirectory base path.
     * Returns '/Secure360' on local WAMP, and '' on production / domain-root hosting.
     */
    function base_path_url(): string
    {
        // 1. If running under Apache, determine if we are in a subfolder from SCRIPT_NAME
        if (!empty($_SERVER['SCRIPT_NAME'])) {
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            $dir = dirname($scriptName);
            // If running as /Secure360/public/index.php, strip /public to get /Secure360
            $dir = preg_replace('#/public$#', '', $dir);
            $cleanDir = rtrim(str_replace('\\', '/', $dir), '/');
            if ($cleanDir !== '' && $cleanDir !== '/') {
                return $cleanDir;
            }
            return '';
        }

        // 2. Fallback to configured APP_URL path
        $configured = config('app.url', '');
        if (!empty($configured)) {
            $path = parse_url($configured, PHP_URL_PATH);
            if (!empty($path) && $path !== '/') {
                return rtrim($path, '/');
            }
        }

        return '';
    }
}

if (!function_exists('url')) {
    /**
     * Generate absolute or relative URL
     * Automatically adapts between local subfolder (e.g. /Secure360) and production root (/)
     */
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $baseDir = base_path_url();

        // Dynamically match current request host and scheme if running under web server
        if (!empty($_SERVER['HTTP_HOST'])) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
            $scheme = $isHttps ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = "{$scheme}://{$host}{$baseDir}";
        } else {
            $configured = config('app.url', 'http://localhost');
            $baseUrl = rtrim($configured, '/');
            if ($baseDir !== '' && !str_ends_with($baseUrl, $baseDir)) {
                $baseUrl .= $baseDir;
            }
        }

        $cleanPath = ltrim($path, '/');
        return $cleanPath === '' ? $baseUrl . '/' : $baseUrl . '/' . $cleanPath;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate URL for public asset
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the active CSRF token
     */
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render hidden CSRF form input field
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
    }
}

if (!function_exists('auth')) {
    /**
     * Retrieve authenticated user details or Auth helper
     */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('geo_distance_meters')) {
    /**
     * Calculate geodesic surface distance in meters between two GPS coordinates using Haversine formula
     */
    function geo_distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // Earth mean radius in meters
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($lonDelta / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

if (!function_exists('validate_shift_window')) {
    /**
     * Validate whether current time falls within assigned shift window.
     * Accurately supports both regular day shifts (e.g. 08:00 - 16:00)
     * and overnight shifts (e.g. 22:00 - 06:00).
     *
     * Returns:
     * [
     *   'allowed' => bool,
     *   'state'   => 'active' | 'before_shift' | 'after_shift',
     *   'message' => string
     * ]
     */
    function validate_shift_window(string $currentTime, string $startTime, string $endTime): array
    {
        // Normalize time strings to HH:MM:SS
        $curTs = strtotime("1970-01-01 " . trim($currentTime));
        $startTs = strtotime("1970-01-01 " . trim($startTime));
        $endTs = strtotime("1970-01-01 " . trim($endTime));

        $formattedStart = date('g:i A', $startTs);

        // Case 1: Standard Day Shift (e.g. 08:00:00 to 16:00:00)
        if ($startTs <= $endTs) {
            if ($curTs < $startTs) {
                return [
                    'allowed' => false,
                    'state' => 'before_shift',
                    'message' => "Your shift starts at {$formattedStart}.",
                ];
            }
            if ($curTs > $endTs) {
                return [
                    'allowed' => false,
                    'state' => 'after_shift',
                    'message' => "Your assigned shift has ended.",
                ];
            }
            return [
                'allowed' => true,
                'state' => 'active',
                'message' => "Shift is active.",
            ];
        }

        // Case 2: Overnight Shift spanning midnight (e.g. 22:00:00 to 06:00:00)
        // Active if >= 22:00:00 OR <= 06:00:00
        if ($curTs >= $startTs || $curTs <= $endTs) {
            return [
                'allowed' => true,
                'state' => 'active',
                'message' => "Shift is active.",
            ];
        }

        // Outside overnight shift: between $endTs (e.g. 06:00) and $startTs (e.g. 22:00)
        $midpoint = $endTs + (int)(($startTs - $endTs) / 2);
        if ($curTs <= $midpoint) {
            return [
                'allowed' => false,
                'state' => 'after_shift',
                'message' => "Your assigned shift has ended.",
            ];
        }

        return [
            'allowed' => false,
            'state' => 'before_shift',
            'message' => "Your shift starts at {$formattedStart}.",
        ];
    }
}

if (!function_exists('parse_timestamp')) {
    /**
     * Parse various date/time input formats into a Unix timestamp.
     */
    function parse_timestamp(mixed $datetime): ?int
    {
        if (empty($datetime)) {
            return null;
        }
        if ($datetime instanceof DateTimeInterface) {
            return $datetime->getTimestamp();
        }
        if (is_numeric($datetime)) {
            return (int)$datetime;
        }
        if (is_string($datetime)) {
            $trimmed = trim($datetime);
            if ($trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '0000-00-00 00:00:00') {
                return null;
            }
            // If it's pure time (e.g. "08:00:00" or "08:00"), prepend reference date for parsing
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $trimmed)) {
                $trimmed = '1970-01-01 ' . $trimmed;
            }
            $ts = strtotime($trimmed);
            return ($ts !== false) ? $ts : null;
        }
        return null;
    }
}

if (!function_exists('format_date')) {
    /**
     * Format to global standard Date Only: dd-MMM-yy (e.g. 18-Sep-26)
     */
    function format_date(mixed $date, string $fallback = '—'): string
    {
        $ts = parse_timestamp($date);
        return $ts !== null ? date('d-M-y', $ts) : $fallback;
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format to global standard Date and Time: dd-MMM-yy hh:mm AM/PM (e.g. 18-Sep-26 05:45 PM)
     */
    function format_datetime(mixed $datetime, string $fallback = '—'): string
    {
        $ts = parse_timestamp($datetime);
        return $ts !== null ? date('d-M-y h:i A', $ts) : $fallback;
    }
}

if (!function_exists('format_time')) {
    /**
     * Format to global standard Time Only: hh:mm AM/PM (e.g. 05:45 PM, 08:00 AM)
     */
    function format_time(mixed $time, string $fallback = '—'): string
    {
        $ts = parse_timestamp($time);
        return $ts !== null ? date('h:i A', $ts) : $fallback;
    }
}

if (!function_exists('format_utc_time')) {
    /**
     * Convert stored UTC timestamp to Asia/Kolkata and format as Time Only: hh:mm AM/PM (e.g. 04:07 PM)
     */
    function format_utc_time(mixed $utcDatetime, string $fallback = '—'): string
    {
        if (empty($utcDatetime)) {
            return $fallback;
        }
        try {
            $raw = trim((string)$utcDatetime);
            if (str_ends_with($raw, 'UTC') || str_ends_with($raw, 'Z') || str_contains($raw, '+')) {
                $dt = new \DateTime($raw);
            } else {
                $dt = new \DateTime($raw, new \DateTimeZone('UTC'));
            }
            $dt->setTimezone(new \DateTimeZone('Asia/Kolkata'));
            return $dt->format('h:i A');
        } catch (\Throwable) {
            return format_time($utcDatetime, $fallback);
        }
    }
}

if (!function_exists('format_utc_datetime')) {
    /**
     * Convert stored UTC timestamp to Asia/Kolkata and format as Date and Time: dd-MMM-yy hh:mm AM/PM (e.g. 23-Sep-26 04:07 PM)
     */
    function format_utc_datetime(mixed $utcDatetime, string $fallback = '—'): string
    {
        if (empty($utcDatetime)) {
            return $fallback;
        }
        try {
            $raw = trim((string)$utcDatetime);
            if (str_ends_with($raw, 'UTC') || str_ends_with($raw, 'Z') || str_contains($raw, '+')) {
                $dt = new \DateTime($raw);
            } else {
                $dt = new \DateTime($raw, new \DateTimeZone('UTC'));
            }
            $dt->setTimezone(new \DateTimeZone('Asia/Kolkata'));
            return $dt->format('d-M-y h:i A');
        } catch (\Throwable) {
            return format_datetime($utcDatetime, $fallback);
        }
    }
}

if (!function_exists('format_date_range')) {
    /**
     * Format to global standard Date Range: dd-MMM-yy → dd-MMM-yy (e.g. 01-Jan-26 → 31-Dec-26)
     */
    function format_date_range(mixed $startDate, mixed $endDate, string $fallback = '—'): string
    {
        $startFormatted = format_date($startDate, '');
        $endFormatted = !empty($endDate) ? format_date($endDate, '') : 'Ongoing';

        if ($startFormatted === '' && ($endFormatted === '' || $endFormatted === 'Ongoing')) {
            return $fallback;
        }
        if ($startFormatted === '') {
            return $endFormatted;
        }
        return "{$startFormatted} → {$endFormatted}";
    }
}

if (!function_exists('format_time_range')) {
    /**
     * Format to global standard Shift Time Range: hh:mm AM/PM - hh:mm AM/PM (e.g. 08:00 AM - 04:00 PM)
     */
    function format_time_range(mixed $startTime, mixed $endTime, string $fallback = '—'): string
    {
        $startFormatted = format_time($startTime, '');
        $endFormatted = format_time($endTime, '');

        if ($startFormatted === '' && $endFormatted === '') {
            return $fallback;
        }
        if ($startFormatted === '') return $endFormatted;
        if ($endFormatted === '') return $startFormatted;

        return "{$startFormatted} - {$endFormatted}";
    }
}

if (!function_exists('format_time_ago')) {
    /**
     * Format relative time with fallback to dd-MMM-yy for events older than 7 days
     */
    function format_time_ago(mixed $datetime, string $fallback = 'Just now'): string
    {
        $ts = parse_timestamp($datetime);
        if ($ts === null) {
            return $fallback;
        }
        $diff = time() - $ts;
        if ($diff < 0) {
            return format_datetime($datetime);
        }
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = max(1, (int)floor($diff / 60));
            return "{$mins}m ago";
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return "{$hours}h ago";
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return "{$days}d ago";
        }
        return format_date($datetime);
    }
}

