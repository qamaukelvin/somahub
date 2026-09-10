-- ------------------------------------------------------------------
-- Adds a 4th color slot (secondary) and a way to tell whether a school
-- is using one of the preset palettes or has gone fully custom.
-- Safe to re-run.
-- ------------------------------------------------------------------

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'secondary_override');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN secondary_override VARCHAR(20) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 'preset' = using a color_palettes row (palette_id) as the base, optionally
-- nudged with the *_override columns. 'custom' = the school picked its own
-- 4 colors from scratch; palette_id is ignored/left null and every color
-- comes from the override columns instead.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'color_mode');
SET @sql = IF(@col_exists = 0, "ALTER TABLE schools ADD COLUMN color_mode ENUM('preset','custom') NOT NULL DEFAULT 'preset'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Give each existing preset palette a sensible 'secondary' value (a muted
-- version of primary) so the 4th swatch isn't blank for palettes created
-- before this column existed. Adjust these by hand later if you want a
-- more deliberate secondary per palette.
UPDATE color_palettes
SET css_variables_json = JSON_SET(css_variables_json, '$.secondary', JSON_UNQUOTE(JSON_EXTRACT(css_variables_json, '$.primary')))
WHERE JSON_EXTRACT(css_variables_json, '$.secondary') IS NULL;
