-- ------------------------------------------------------------------
-- Template / Color split
--
-- Previously a "theme" bundled colors+fonts AND structural CSS into one
-- row, so a school picking a look was really picking both at once.
-- This splits that into two independently-choosable things:
--   - templates:      structural layout/CSS. Premium-gated (same as
--                      themes.is_premium was).
--   - color_palettes:  colors + fonts only. Always free to pick, on any
--                      plan, with any template.
--
-- The old `themes` table and `schools.theme_id` are left in place
-- untouched — nothing reads them for new schools going forward, but
-- they're kept as a safety net so any school not yet manually
-- reassigned (see schools.template_id / palette_id below) still
-- renders exactly as before via the app-level fallback in
-- includes/appearance.php. Drop `themes` and `theme_id` once every
-- school has been reassigned.
--
-- Safe to re-run.
-- ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    custom_css LONGTEXT,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS color_palettes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    css_variables_json TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- One templates row + one color_palettes row per existing theme.
-- Guarded by name so this is safe to re-run and won't duplicate.
INSERT INTO templates (name, custom_css, is_premium, is_active)
SELECT th.name, th.custom_css, th.is_premium, th.is_active
FROM themes th
WHERE NOT EXISTS (SELECT 1 FROM templates tpl WHERE tpl.name = th.name);

INSERT INTO color_palettes (name, css_variables_json, is_active)
SELECT th.name, th.css_variables_json, th.is_active
FROM themes th
WHERE NOT EXISTS (SELECT 1 FROM color_palettes cp WHERE cp.name = th.name);

-- New columns on schools. Left NULLABLE on purpose — existing schools
-- keep rendering via the legacy theme_id fallback (matched by name)
-- until reassigned manually; new schools always set both going forward.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'template_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN template_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'palette_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN palette_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- A few starter palettes beyond the 4 inherited from themes, so there's
-- immediately more color freedom to offer without waiting on new templates.
INSERT INTO color_palettes (name, css_variables_json, is_active)
SELECT 'Forest & Gold', '{"primary":"#1B4D3E","accent":"#F2B705","bg":"#FBF8F2","font_display":"Sora","font_body":"Nunito Sans"}', 1
WHERE NOT EXISTS (SELECT 1 FROM color_palettes WHERE name = 'Forest & Gold');

INSERT INTO color_palettes (name, css_variables_json, is_active)
SELECT 'Coastal Blue', '{"primary":"#0B4F6C","accent":"#01BAEF","bg":"#F4FAFC","font_display":"Sora","font_body":"Nunito Sans"}', 1
WHERE NOT EXISTS (SELECT 1 FROM color_palettes WHERE name = 'Coastal Blue');

INSERT INTO color_palettes (name, css_variables_json, is_active)
SELECT 'Maroon Classic', '{"primary":"#5C1A1B","accent":"#D4A24C","bg":"#FBF6EF","font_display":"Sora","font_body":"Nunito Sans"}', 1
WHERE NOT EXISTS (SELECT 1 FROM color_palettes WHERE name = 'Maroon Classic');

INSERT INTO color_palettes (name, css_variables_json, is_active)
SELECT 'Slate & Teal', '{"primary":"#2E3A46","accent":"#2EC4B6","bg":"#F6F8F9","font_display":"Sora","font_body":"Nunito Sans"}', 1
WHERE NOT EXISTS (SELECT 1 FROM color_palettes WHERE name = 'Slate & Teal');
