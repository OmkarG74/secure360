<?php
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register(__DIR__ . '/../app');

$res = \App\Core\Database::testConnection();
echo json_encode($res, JSON_PRETTY_PRINT) . PHP_EOL;
