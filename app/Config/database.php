<?php

declare(strict_types=1);

/**
 * Database Configuration (MySQL / PDO)
 * Centralized settings mapped from environment variables
 */

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => getenv('DB_HOST') ?: '127.0.0.1',
            'port'      => (int)(getenv('DB_PORT') ?: 3306),
            'database'  => getenv('DB_NAME') ?: 'secure360_v2',
            'username'  => getenv('DB_USER') ?: 'root',
            'password'  => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
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
