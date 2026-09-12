-- ------------------------------------------------------------------
-- Per-school-section layout variant, replacing template-level
-- layout_config as the runtime source of truth. A school's own choice
-- (made in the new Website Design step) now lives directly on their
-- site_sections row, not inherited from whichever template they picked.
--
-- templates.layout_config (added in add_layout_config.sql) becomes
-- reference data only - "what a Quick Start preset suggests" - used to
-- pre-fill layout_variant when a preset is chosen, not read at render
-- time anymore. site.php now always reads the school's own persisted
-- choice, falling back to 'default' if never set.
--
-- Safe to re-run.
-- ------------------------------------------------------------------

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_sections' AND COLUMN_NAME = 'layout_variant');
SET @sql = IF(@col_exists = 0, "ALTER TABLE site_sections ADD COLUMN layout_variant VARCHAR(50) NOT NULL DEFAULT 'default'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
