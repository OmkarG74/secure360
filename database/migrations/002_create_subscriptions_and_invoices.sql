-- ==============================================================================
-- SECURE360 DATABASE MIGRATION 002: SUBSCRIPTIONS, INVOICES & BILLING
-- Aligned with secure360_v2 database schema & architecture invariants
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Table structure for `system_settings`
-- Stores platform-wide default configurations (e.g. price per guard, currency)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed initial billing defaults
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('subscription_price_per_guard', '500.00', 'Default subscription price per guard per billing cycle'),
('subscription_currency', 'INR', 'Default billing currency code'),
('subscription_expiring_soon_days', '15', 'Number of days before expiry to mark subscription as expiring soon')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- -------------------------------------------------------------
-- Table structure for `subscriptions`
-- Central subscription and guard licensing table per organisation
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `guard_limit` int unsigned NOT NULL DEFAULT '30',
  `price_per_guard` decimal(10,2) NOT NULL DEFAULT '500.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '15000.00',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=active, 1=expiring_soon, 2=expired, 3=suspended, 4=cancelled',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_org_status` (`organization_id`, `status`),
  KEY `idx_subscriptions_end_date` (`end_date`),
  KEY `fk_subscriptions_created_by` (`created_by`),
  KEY `fk_subscriptions_updated_by` (`updated_by`),
  CONSTRAINT `fk_subscriptions_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subscriptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_subscriptions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `invoices`
-- Normalized billing invoice records linked to organizations & subscriptions
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `subscription_id` bigint unsigned NOT NULL,
  `invoice_number` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date NOT NULL,
  `billing_start_date` date NOT NULL,
  `billing_end_date` date NOT NULL,
  `guard_quantity` int unsigned NOT NULL,
  `price_per_guard` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid' COMMENT 'paid, pending, cancelled',
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  KEY `idx_invoices_org_date` (`organization_id`, `invoice_date`),
  KEY `idx_invoices_subscription` (`subscription_id`),
  CONSTRAINT `fk_invoices_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `subscription_history`
-- Audit trail of guard limit adjustments, price updates, and renewals
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscription_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `change_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created, renewed, limit_increased, limit_decreased, price_changed',
  `previous_guard_limit` int unsigned DEFAULT NULL,
  `new_guard_limit` int unsigned NOT NULL,
  `previous_price_per_guard` decimal(10,2) DEFAULT NULL,
  `new_price_per_guard` decimal(10,2) NOT NULL,
  `previous_total_amount` decimal(12,2) DEFAULT NULL,
  `new_total_amount` decimal(12,2) NOT NULL,
  `amount_difference` decimal(12,2) NOT NULL DEFAULT '0.00',
  `effective_date` date NOT NULL,
  `performed_by` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_hist_sub` (`subscription_id`),
  KEY `idx_sub_hist_org` (`organization_id`),
  KEY `fk_sub_hist_user` (`performed_by`),
  CONSTRAINT `fk_sub_hist_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_hist_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sub_hist_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
