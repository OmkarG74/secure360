<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=secure360_v2;charset=utf8mb4', 'root', '');

$tables = [
    'roles', 'organizations', 'users', 'customers', 'sites', 'guards', 
    'contracts', 'contract_shifts', 'contract_guard_assignments', 
    'attendance', 'guard_live_locations', 'selfies', 'activities', 
    'api_tokens', 'notifications'
];

$sql = "-- ==============================================================================\n";
$sql .= "-- SECURE360 DATABASE SCHEMA (secure360_v2)\n";
$sql .= "-- Exported on " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Database: secure360_v2\n";
$sql .= "-- ==============================================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $t) {
    $sql .= "-- -------------------------------------------------------------\n";
    $sql .= "-- Table structure for `$t`\n";
    $sql .= "-- -------------------------------------------------------------\n";
    $sql .= "DROP TABLE IF EXISTS `$t`;\n";
    $stmt = $pdo->query("SHOW CREATE TABLE `$t`");
    $create = $stmt->fetch(PDO::FETCH_ASSOC);
    $sql .= $create['Create Table'] . ";\n\n";
}

// Seed roles
$roles = $pdo->query("SELECT * FROM `roles`")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($roles)) {
    $sql .= "-- -------------------------------------------------------------\n";
    $sql .= "-- Seed Data for `roles`\n";
    $sql .= "-- -------------------------------------------------------------\n";
    $sql .= "INSERT INTO `roles` (`id`, `role_code`, `role_name`, `description`, `status`) VALUES\n";
    $roleVals = [];
    foreach ($roles as $r) {
        $roleVals[] = sprintf("(%d, '%s', '%s', '%s', %d)", 
            $r['id'], addslashes($r['role_code']), addslashes($r['role_name']), addslashes($r['description']), $r['status']);
    }
    $sql .= implode(",\n", $roleVals) . ";\n\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents(__DIR__ . '/../database/schema/secure360_v2_schema.sql', $sql);
file_put_contents(__DIR__ . '/../database/migrations/001_initial_schema.sql', $sql);
echo "Schema exported successfully to database/schema/secure360_v2_schema.sql and 001_initial_schema.sql\n";
