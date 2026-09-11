<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=secure360_v2;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = file_get_contents(__DIR__ . '/../database/seeders/development_seed.sql');
$pdo->exec($sql);
echo "Development seed imported successfully.\n";
