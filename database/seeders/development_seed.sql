-- ==============================================================================
-- SECURE360 DEVELOPMENT SEED DATA
-- For Local Development & Testing Between Web and Flutter
--
-- Credentials for all seed users:
--   Password: password123
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Sample Organization
INSERT INTO `organizations` (`id`, `organization_code`, `name`, `contact_person`, `email`, `phone`, `address`, `status`) 
VALUES (1, 'ORG-APEX', 'Apex Security Services', 'John Smith', 'contact@apexsecurity.com', '+1-555-0100', '100 Security Blvd, Suite 200', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Superadmin User (Global - organization_id = NULL)
INSERT INTO `users` (`id`, `organization_id`, `role_id`, `full_name`, `email`, `phone`, `employee_code`, `password_hash`, `status`)
VALUES (1, NULL, 1, 'Super Administrator', 'superadmin@secure360.local', '+1-555-0001', 'SA-001', '$2y$12$fIb97.Jsx5Xkna601CsxienwLjaiN8bMr74Z7DZ5Wi4gA/7h6WTtG', 0)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 3. Organization Admin User
INSERT INTO `users` (`id`, `organization_id`, `role_id`, `full_name`, `email`, `phone`, `employee_code`, `password_hash`, `status`)
VALUES (2, 1, 2, 'Apex Admin User', 'admin@apexsecurity.com', '+1-555-0101', 'ADM-101', '$2y$12$fIb97.Jsx5Xkna601CsxienwLjaiN8bMr74Z7DZ5Wi4gA/7h6WTtG', 0)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 4. Guard User (Mobile App login)
INSERT INTO `users` (`id`, `organization_id`, `role_id`, `full_name`, `email`, `phone`, `employee_code`, `password_hash`, `status`)
VALUES (3, 1, 3, 'David Guard', 'guard@apexsecurity.com', '+1-555-0102', 'GRD-101', '$2y$12$fIb97.Jsx5Xkna601CsxienwLjaiN8bMr74Z7DZ5Wi4gA/7h6WTtG', 0)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 5. Guard Profile Record
INSERT INTO `guards` (`id`, `user_id`, `photo_url`, `status`)
VALUES (1, 3, 'uploads/guards/sample_guard.jpg', 0)
ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`);

-- 6. Sample Customer / Client
INSERT INTO `customers` (`id`, `organization_id`, `client_code`, `name`, `contact_person`, `phone`, `email`, `address`, `status`)
VALUES (1, 1, 'CLT-METRO', 'Metro Commercial Plaza', 'Sarah Client', '+1-555-0200', 'management@metroplaza.com', '500 Commerce Way', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 7. Multiple Sites for Metro Commercial Plaza (1 Client -> Multiple Sites)
INSERT INTO `sites` (`id`, `organization_id`, `customer_id`, `site_code`, `site_name`, `site_address`, `area`, `latitude`, `longitude`, `zone_gate`, `status`)
VALUES 
(1, 1, 1, 'SITE-METRO-MAIN', 'Metro Plaza - Main Gate', '500 Commerce Way, Gate 1', 'Main Entry', 18.5204303, 73.8567437, 'Gate A', 0),
(2, 1, 1, 'SITE-METRO-NORTH', 'Metro Plaza - North Warehouse', '510 Commerce Way, Bay 3', 'Logistics Bay', 18.5215000, 73.8580000, 'Gate North', 0)
ON DUPLICATE KEY UPDATE `site_name` = VALUES(`site_name`);

-- 8. Contract
INSERT INTO `contracts` (`id`, `organization_id`, `customer_id`, `site_id`, `contract_code`, `start_date`, `end_date`, `required_guard_count`, `extra_notes`, `status`)
VALUES (1, 1, 1, 1, 'CTR-2026-001', '2026-01-01', '2026-12-31', 2, '24/7 Gate security agreement', 0)
ON DUPLICATE KEY UPDATE `contract_code` = VALUES(`contract_code`);

-- 9. Contract Shift
INSERT INTO `contract_shifts` (`id`, `contract_id`, `shift_code`, `shift_name`, `start_time`, `end_time`, `status`)
VALUES (1, 1, 'SHIFT-DAY', 'Day Patrol Shift', '08:00:00', '16:00:00', 0)
ON DUPLICATE KEY UPDATE `shift_name` = VALUES(`shift_name`);

-- 10. Guard Assignment
INSERT INTO `contract_guard_assignments` (`id`, `contract_id`, `contract_shift_id`, `guard_id`, `site_id`, `status`, `notes`)
VALUES (1, 1, 1, 1, 1, 0, 'Assigned to Main Gate day patrol')
ON DUPLICATE KEY UPDATE `notes` = VALUES(`notes`);

SET FOREIGN_KEY_CHECKS = 1;
