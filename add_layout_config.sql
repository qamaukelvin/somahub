-- ------------------------------------------------------------------
-- Adds a JSON config column to templates for structural (not CSS)
-- choices - which section variant a template uses. Starts with hero
-- and staff, the two highest-visibility sections. NULL/missing keys
-- always mean "use the default rendering" so every existing template
-- keeps working unchanged until deliberately given a variant.
-- Safe to re-run.
-- ------------------------------------------------------------------

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND COLUMN_NAME = 'layout_config');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE templates ADD COLUMN layout_config JSON NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
