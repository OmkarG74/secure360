-- ==============================================================================
-- SECURE360 DATABASE MIGRATION 006: ADD OUTSIDE POST TRACKING TO ATTENDANCE
-- Adds is_outside_post flag to track guard post departure / geofence state
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add is_outside_post column to attendance table if it does not exist
SET @exist_col_outside = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'attendance' AND column_name = 'is_outside_post');
SET @sql_col_outside = IF(@exist_col_outside = 0, 'ALTER TABLE `attendance` ADD COLUMN `is_outside_post` tinyint NOT NULL DEFAULT 0 AFTER `status`', 'SELECT 1');
PREPARE stmt_col FROM @sql_col_outside;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- 2. Add performance index for active attendance post tracking
SET @exist_idx_outside = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'attendance' AND index_name = 'idx_attendance_guard_outside');
SET @sql_idx_outside = IF(@exist_idx_outside = 0, 'ALTER TABLE `attendance` ADD KEY `idx_attendance_guard_outside` (`guard_id`, `status`, `is_outside_post`)', 'SELECT 1');
PREPARE stmt FROM @sql_idx_outside;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
