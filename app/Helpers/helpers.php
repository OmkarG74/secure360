<?php

declare(strict_types=1);

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

