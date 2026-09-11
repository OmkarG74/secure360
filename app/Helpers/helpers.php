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

if (!function_exists('url')) {
    /**
     * Generate absolute or relative URL
     * Ensures consistent subfolder routing under Apache (e.g. /Secure360)
     */
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $configured = config('app.url', 'http://localhost/Secure360');
        $configuredPath = parse_url($configured, PHP_URL_PATH) ?? '/Secure360';
        $baseDir = rtrim(str_replace('\\', '/', $configuredPath), '/');

        // Dynamically match current request host and scheme if running under web server
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = "{$scheme}://{$host}{$baseDir}";
        } else {
            $baseUrl = rtrim($configured, '/');
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
