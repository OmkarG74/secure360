-- ==============================================================================
-- SECURE360 DATABASE SCHEMA (secure360_v2)
-- Exported on 2026-09-11 09:04:42
-- Database: secure360_v2
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Table structure for `roles`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `role_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`role_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `organizations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organizations_code` (`organization_code`),
  KEY `idx_organizations_status` (`status`),
  KEY `idx_organizations_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `users`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned DEFAULT NULL,
  `role_id` smallint unsigned NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `last_login_at` datetime DEFAULT NULL,
  `session_version` int unsigned NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_org_employee_code` (`organization_id`, `employee_code`),
  KEY `idx_users_org_role_status` (`organization_id`,`role_id`,`status`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `customers`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `client_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_client_code` (`client_code`),
  KEY `idx_customers_org_status` (`organization_id`,`status`),
  KEY `idx_customers_org_name` (`organization_id`,`name`),
  CONSTRAINT `fk_customers_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sites`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sites`;
CREATE TABLE `sites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `site_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_address` text COLLATE utf8mb4_unicode_ci,
  `area` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `zone_gate` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sites_org_site_code` (`organization_id`, `site_code`),
  KEY `idx_sites_org_customer_status` (`organization_id`,`customer_id`,`status`),
  KEY `idx_sites_coordinates` (`latitude`,`longitude`),
  KEY `fk_sites_customer` (`customer_id`),
  CONSTRAINT `fk_sites_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_sites_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `guards`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `guards`;
CREATE TABLE `guards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guards_user` (`user_id`),
  KEY `idx_guards_status` (`status`),
  KEY `idx_guards_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_guards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `contracts`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `contracts`;
CREATE TABLE `contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `contract_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `required_guard_count` int unsigned NOT NULL DEFAULT '0',
  `extra_notes` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `active_site_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` = 0) then `site_id` else NULL end)) STORED,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contracts_contract_code` (`contract_code`),
  UNIQUE KEY `uq_contracts_one_active_contract_per_site` (`active_site_id`),
  KEY `idx_contracts_org_status` (`organization_id`,`status`),
  KEY `idx_contracts_site_dates` (`site_id`,`start_date`,`end_date`),
  KEY `fk_contracts_customer` (`customer_id`),
  CONSTRAINT `fk_contracts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_contracts_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_contracts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `contract_shifts`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `contract_shifts`;
CREATE TABLE `contract_shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint unsigned NOT NULL,
  `shift_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_shifts_code` (`contract_id`,`shift_code`),
  KEY `idx_contract_shifts_contract_status` (`contract_id`,`status`),
  CONSTRAINT `fk_contract_shifts_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `contract_guard_assignments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `contract_guard_assignments`;
CREATE TABLE `contract_guard_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint unsigned NOT NULL,
  `contract_shift_id` bigint unsigned NOT NULL,
  `guard_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=inactive, 2=deleted',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `active_guard_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` = 0) then `guard_id` else NULL end)) STORED,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assignment_guard_contract_shift` (`contract_id`,`contract_shift_id`,`guard_id`),
  UNIQUE KEY `uq_assignment_one_active_contract_per_guard` (`active_guard_id`),
  KEY `idx_assignments_contract_status` (`contract_id`,`status`),
  KEY `idx_assignments_guard_status` (`guard_id`,`status`),
  KEY `idx_assignments_site` (`site_id`),
  KEY `fk_assignments_shift` (`contract_shift_id`),
  CONSTRAINT `fk_assignments_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  CONSTRAINT `fk_assignments_guard` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`),
  CONSTRAINT `fk_assignments_shift` FOREIGN KEY (`contract_shift_id`) REFERENCES `contract_shifts` (`id`),
  CONSTRAINT `fk_assignments_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `attendance`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `guard_id` bigint unsigned NOT NULL,
  `assignment_id` bigint unsigned DEFAULT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `check_in_at` datetime DEFAULT NULL,
  `check_out_at` datetime DEFAULT NULL,
  `check_in_latitude` decimal(10,7) DEFAULT NULL,
  `check_in_longitude` decimal(10,7) DEFAULT NULL,
  `check_out_latitude` decimal(10,7) DEFAULT NULL,
  `check_out_longitude` decimal(10,7) DEFAULT NULL,
  `check_in_address` text COLLATE utf8mb4_unicode_ci,
  `check_out_address` text COLLATE utf8mb4_unicode_ci,
  `selfie_id` bigint unsigned DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=open, 1=completed, 2=cancelled',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_org_date` (`organization_id`,`check_in_at`),
  KEY `idx_attendance_guard_date` (`guard_id`,`check_in_at`),
  KEY `idx_attendance_assignment` (`assignment_id`),
  KEY `idx_attendance_site` (`site_id`),
  CONSTRAINT `fk_attendance_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `contract_guard_assignments` (`id`),
  CONSTRAINT `fk_attendance_guard` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`),
  CONSTRAINT `fk_attendance_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_attendance_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `guard_live_locations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `guard_live_locations`;
CREATE TABLE `guard_live_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `guard_id` bigint unsigned NOT NULL,
  `assignment_id` bigint unsigned DEFAULT NULL,
  `attendance_id` bigint unsigned DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy_meters` decimal(10,2) DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `recorded_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_guard_time` (`guard_id`,`recorded_at`),
  KEY `idx_live_assignment_time` (`assignment_id`,`recorded_at`),
  KEY `idx_live_org_time` (`organization_id`,`recorded_at`),
  KEY `fk_live_attendance` (`attendance_id`),
  CONSTRAINT `fk_live_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `contract_guard_assignments` (`id`),
  CONSTRAINT `fk_live_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`),
  CONSTRAINT `fk_live_guard` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`),
  CONSTRAINT `fk_live_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `selfies`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `selfies`;
CREATE TABLE `selfies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `guard_id` bigint unsigned NOT NULL,
  `attendance_id` bigint unsigned DEFAULT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verification_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_score` decimal(6,3) DEFAULT NULL,
  `verification_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_selfies_guard_time` (`guard_id`,`captured_at`),
  KEY `idx_selfies_attendance` (`attendance_id`),
  KEY `fk_selfies_organization` (`organization_id`),
  CONSTRAINT `fk_selfies_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`),
  CONSTRAINT `fk_selfies_guard` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`),
  CONSTRAINT `fk_selfies_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `activities`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details_json` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activities_org_time` (`organization_id`,`created_at`),
  KEY `idx_activities_user_time` (`user_id`,`created_at`),
  KEY `idx_activities_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_activities_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `api_tokens`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`),
  KEY `idx_api_tokens_expiry` (`expires_at`),
  KEY `idx_api_tokens_revoked` (`revoked_at`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `notifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_json` json DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_org` (`organization_id`,`created_at`),
  CONSTRAINT `fk_notifications_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Seed Data for `roles`
-- -------------------------------------------------------------
INSERT INTO `roles` (`id`, `role_code`, `role_name`, `description`, `status`) VALUES
(1, 'super_admin', 'Super Admin', 'Platform administrator', 0),
(2, 'admin', 'Admin', 'Organization administrator', 0),
(3, 'guard', 'Guard', 'Field security guard', 0);

SET FOREIGN_KEY_CHECKS = 1;
