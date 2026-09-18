<?php

declare(strict_types=1);

/**
 * Database Configuration (MySQL / PDO)
 * Centralized settings mapped from environment variables
 * Fully compatible with Railway MySQL plugin, standard Docker, and local XAMPP/WAMP
 */

$host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1');
$port = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));
$database = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQLDATABASE') ?: 'secure360_v2'));
$username = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('MYSQLUSER') ?: 'root'));

$password = '';
if (getenv('DB_PASS') !== false) {
    $password = (string)getenv('DB_PASS');
} elseif (getenv('DB_PASSWORD') !== false) {
    $password = (string)getenv('DB_PASSWORD');
} elseif (getenv('MYSQLPASSWORD') !== false) {
    $password = (string)getenv('MYSQLPASSWORD');
}

// Support Railway DATABASE_URL / MYSQL_URL connection string if individual variables are not set
$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if (!empty($databaseUrl) && is_string($databaseUrl)) {
    $parsed = parse_url($databaseUrl);
    if (is_array($parsed)) {
        if (!empty($parsed['host']) && empty(getenv('DB_HOST')) && empty(getenv('MYSQLHOST'))) {
            $host = $parsed['host'];
        }
        if (!empty($parsed['port']) && empty(getenv('DB_PORT')) && empty(getenv('MYSQLPORT'))) {
            $port = (int)$parsed['port'];
        }
        if (!empty($parsed['user']) && empty(getenv('DB_USER')) && empty(getenv('DB_USERNAME')) && empty(getenv('MYSQLUSER'))) {
            $username = $parsed['user'];
        }
        if (isset($parsed['pass']) && getenv('DB_PASS') === false && getenv('DB_PASSWORD') === false && getenv('MYSQLPASSWORD') === false) {
            $password = $parsed['pass'];
        }
        if (!empty($parsed['path']) && empty(getenv('DB_NAME')) && empty(getenv('DB_DATABASE')) && empty(getenv('MYSQLDATABASE'))) {
            $database = ltrim($parsed['path'], '/');
        }
    }
}

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => $host,
            'port'      => $port,
            'database'  => $database,
            'username'  => $username,
            'password'  => $password,
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
