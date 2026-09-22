-- ==============================================================================
-- SECURE360 DATABASE MIGRATION 005: ENHANCE NOTIFICATIONS TABLE
-- Adds sender tracking, entity linking, priority levels, and delivery status
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add missing operational columns to notifications table if they do not exist
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `sender_user_id` bigint unsigned DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `entity_type` varchar(50) DEFAULT NULL AFTER `type`,
  ADD COLUMN IF NOT EXISTS `entity_id` varchar(50) DEFAULT NULL AFTER `entity_type`,
  ADD COLUMN IF NOT EXISTS `priority` varchar(20) NOT NULL DEFAULT 'normal' AFTER `entity_id`,
  ADD COLUMN IF NOT EXISTS `delivery_status` varchar(20) NOT NULL DEFAULT 'sent' AFTER `is_read`;

-- 2. Add performance indexes (ignore if exist)
SET @exist_idx_sender = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'notifications' AND index_name = 'idx_notifications_sender');
SET @sql_idx_sender = IF(@exist_idx_sender = 0, 'ALTER TABLE `notifications` ADD KEY `idx_notifications_sender` (`sender_user_id`)', 'SELECT 1');
PREPARE stmt FROM @sql_idx_sender;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_idx_type = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'notifications' AND index_name = 'idx_notifications_type');
SET @sql_idx_type = IF(@exist_idx_type = 0, 'ALTER TABLE `notifications` ADD KEY `idx_notifications_type` (`type`)', 'SELECT 1');
PREPARE stmt FROM @sql_idx_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_idx_priority = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'notifications' AND index_name = 'idx_notifications_priority');
SET @sql_idx_priority = IF(@exist_idx_priority = 0, 'ALTER TABLE `notifications` ADD KEY `idx_notifications_priority` (`priority`)', 'SELECT 1');
PREPARE stmt FROM @sql_idx_priority;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add foreign key constraint to sender_user_id
SET @exist_fk_sender = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'notifications' AND constraint_name = 'fk_notifications_sender_user');
SET @sql_fk_sender = IF(@exist_fk_sender = 0, 'ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_sender_user` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql_fk_sender;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
