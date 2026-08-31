-- The verification grace period should count from when a school's owner
-- actually first logs in and can start engaging, not from whenever an
-- admin created the account on their behalf. Without this, an admin-created
-- school could have its site go offline before the owner ever sees it.
--
-- Uses a dynamic existence check instead of "ADD COLUMN IF NOT EXISTS"
-- (that syntax needs MySQL 8.0.29+, which errored with #1064 on this host).
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'first_login_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN first_login_at TIMESTAMP NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
