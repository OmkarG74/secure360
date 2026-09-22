-- ==============================================================================
-- SECURE360 DATABASE MIGRATION 003: ENHANCE SUBSCRIPTIONS AND INVOICES
-- Adds invoice_type, previous_guard_limit, and additional_guards to invoices table
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Enhance `invoices` table to record invoice type and incremental upgrade metrics
ALTER TABLE `invoices`
  ADD COLUMN IF NOT EXISTS `invoice_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Initial Subscription' AFTER `subscription_id`,
  ADD COLUMN IF NOT EXISTS `previous_guard_limit` int unsigned DEFAULT NULL AFTER `guard_quantity`,
  ADD COLUMN IF NOT EXISTS `additional_guards` int unsigned DEFAULT NULL AFTER `previous_guard_limit`;

SET FOREIGN_KEY_CHECKS = 1;
