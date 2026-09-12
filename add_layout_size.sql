-- ------------------------------------------------------------------
-- Independent "size" setting (full page / half page / auto) alongside
-- layout_variant. Kept as its own column rather than folded into
-- layout_variant since it's a separate axis - a school might want any
-- variant at full height, not just one specific variant.
-- Safe to re-run.
-- ------------------------------------------------------------------

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_sections' AND COLUMN_NAME = 'layout_size');
SET @sql = IF(@col_exists = 0, "ALTER TABLE site_sections ADD COLUMN layout_size VARCHAR(20) NOT NULL DEFAULT 'auto'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
