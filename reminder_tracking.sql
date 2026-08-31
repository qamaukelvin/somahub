-- Tracks which reminder thresholds have already been sent, so the daily
-- cron never emails the same warning twice for the same school.
--
-- Uses a dynamic existence check instead of "ADD COLUMN IF NOT EXISTS"
-- (that syntax needs MySQL 8.0.29+, which errors with #1064 on this host —
-- the same bug pattern found and fixed in first_login_tracking.sql).
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'plan_reminder_sent_7d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN plan_reminder_sent_7d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'plan_reminder_sent_1d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN plan_reminder_sent_1d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'plan_reminder_sent_expired');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN plan_reminder_sent_expired TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'verify_reminder_sent_3d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN verify_reminder_sent_3d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'verify_reminder_sent_1d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN verify_reminder_sent_1d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'verify_reminder_sent_offline');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN verify_reminder_sent_offline TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
