<?php

declare(strict_types=1);

/**
 * Application Settings Configuration
 */

$isProduction = (getenv('APP_ENV') === 'production')
    || !empty(getenv('RAILWAY_ENVIRONMENT'))
    || !empty(getenv('RAILWAY_PROJECT_ID'))
    || !empty(getenv('RAILWAY_SERVICE_ID'));

$appDebugEnv = getenv('APP_DEBUG');
$debug = $appDebugEnv !== false ? filter_var($appDebugEnv, FILTER_VALIDATE_BOOLEAN) : !$isProduction;

$railwayDomain = getenv('RAILWAY_PUBLIC_DOMAIN');
$defaultUrl = $railwayDomain ? 'https://' . rtrim($railwayDomain, '/') : ($isProduction ? 'http://localhost' : 'http://localhost/Secure360');
$appUrl = getenv('APP_URL') ?: $defaultUrl;

return [
    'name' => getenv('APP_NAME') ?: 'Secure360',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => $debug,
    'url' => $appUrl,
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Kolkata',
    'locale' => getenv('APP_LOCALE') ?: 'en',
    
    // Session parameters
    'session' => [
        'name' => 'secure360_sess',
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 7200),
        'secure' => filter_var(getenv('SESSION_SECURE_COOKIE') ?: false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // API & JWT Security
    'api' => [
        'jwt_secret' => getenv('JWT_SECRET') ?: 'secure360_default_development_secret_key',
        'token_expiry' => 86400 * 7, // 7 days for Flutter mobile app
    ],

    // Upload directories
    'uploads' => [
        'max_size' => (int)(getenv('UPLOAD_MAX_SIZE') ?: 10485760),
        'allowed_types' => explode(',', getenv('UPLOAD_ALLOWED_TYPES') ?: 'jpg,jpeg,png,pdf,doc,docx'),
    ],
];
