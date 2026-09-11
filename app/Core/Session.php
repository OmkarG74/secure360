<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session and CSRF Security Manager
 */
class Session
{
    private static bool $started = false;

    /**
     * Start secure session if not already active
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $configPath = dirname(__DIR__) . '/Config/app.php';
        $config = file_exists($configPath) ? require $configPath : [];
        $sessionConfig = $config['session'] ?? [];

        if (!headers_sent()) {
            session_name($sessionConfig['name'] ?? 'secure360_sess');

            session_set_cookie_params([
                'lifetime' => $sessionConfig['lifetime'] ?? 7200,
                'path' => '/',
                'domain' => '',
                'secure' => $sessionConfig['secure'] ?? false,
                'httponly' => $sessionConfig['httponly'] ?? true,
                'samesite' => $sessionConfig['samesite'] ?? 'Lax',
            ]);

            session_start();
        } elseif (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (!headers_sent() && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    /**
     * Flash messages for session lifecycle
     */
    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $data = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $data;
    }

    /**
     * Retrieve and clear flash message
     */
    public static function getFlash(string $key): mixed
    {
        return self::flash($key);
    }

    /**
     * Generate or retrieve CSRF token
     */
    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Validate submitted CSRF token
     */
    public static function validateCsrf(?string $token): bool
    {
        self::start();
        $stored = $_SESSION['_csrf_token'] ?? '';
        return !empty($stored) && !empty($token) && hash_equals($stored, $token);
    }
}
