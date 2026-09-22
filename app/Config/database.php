<?php

declare(strict_types=1);

/**
 * Database Configuration (MySQL / PDO)
 * Centralized settings mapped from environment variables
 * Fully compatible with Railway MySQL plugin, standard Docker, and local XAMPP/WAMP
 */

$isProduction = (getenv('APP_ENV') === 'production')
    || !empty(getenv('RAILWAY_ENVIRONMENT'))
    || !empty(getenv('RAILWAY_PROJECT_ID'))
    || !empty(getenv('RAILWAY_SERVICE_ID'));

$host = null;
$port = null;
$database = null;
$username = null;
$password = null;
$configSource = 'none';

// -----------------------------------------------------------------------------
// Priority 1: Explicit Application Database Environment Variables (DB_*)
// -----------------------------------------------------------------------------
if (getenv('DB_HOST') !== false && getenv('DB_HOST') !== '') {
    $host = trim((string)getenv('DB_HOST'));
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $database = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: null);
    $username = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: null);
    if (getenv('DB_PASS') !== false) {
        $password = (string)getenv('DB_PASS');
    } elseif (getenv('DB_PASSWORD') !== false) {
        $password = (string)getenv('DB_PASSWORD');
    }
    $configSource = 'explicit_db_env';
}

// -----------------------------------------------------------------------------
// Priority 2: Railway MySQL Plugin Environment Variables (MYSQL*)
// -----------------------------------------------------------------------------
if (empty($host) && getenv('MYSQLHOST') !== false && getenv('MYSQLHOST') !== '') {
    $host = trim((string)getenv('MYSQLHOST'));
    $port = (int)(getenv('MYSQLPORT') ?: 3306);
    $database = getenv('MYSQLDATABASE') ?: null;
    $username = getenv('MYSQLUSER') ?: null;
    if (getenv('MYSQLPASSWORD') !== false) {
        $password = (string)getenv('MYSQLPASSWORD');
    }
    $configSource = 'railway_mysql_env';
}

// -----------------------------------------------------------------------------
// Priority 3: Connection URL Strings (DATABASE_URL / MYSQL_URL / MYSQL_PRIVATE_URL)
// -----------------------------------------------------------------------------
$rawUrl = getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: (getenv('MYSQL_PRIVATE_URL') ?: getenv('MYSQL_PUBLIC_URL')));
if (empty($host) && !empty($rawUrl) && is_string($rawUrl)) {
    $parsed = parse_url($rawUrl);
    if (is_array($parsed) && !empty($parsed['host'])) {
        $host = $parsed['host'];
        $port = (int)($parsed['port'] ?? 3306);
        $username = isset($parsed['user']) ? urldecode($parsed['user']) : null;
        $password = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
        if (!empty($parsed['path'])) {
            $database = ltrim($parsed['path'], '/');
        }
        $configSource = 'connection_url';
    }
}

// -----------------------------------------------------------------------------
// Priority 4: Local Development Fallback ONLY (forbidden in production)
// -----------------------------------------------------------------------------
$isConfigMissing = false;
if (empty($host) || empty($database) || empty($username)) {
    if ($isProduction) {
        $isConfigMissing = true;
    } else {
        // Safe local WAMP defaults
        $host = '127.0.0.1';
        $port = 3306;
        $database = 'secure360_v2';
        $username = 'root';
        $password = $password ?? '';
        $configSource = 'local_wamp_fallback';
    }
}

return [
    'default' => 'mysql',
    'is_production' => $isProduction,
    'config_missing' => $isConfigMissing,
    'config_source' => $configSource,
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => $host ?? '',
            'port'      => $port ?? 3306,
            'database'  => $database ?? '',
            'username'  => $username ?? '',
            'password'  => $password ?? '',
            'charset'   => getenv('DB_CHARSET') ?: 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options'   => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],
    ],
];
