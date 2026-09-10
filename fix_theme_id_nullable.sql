-- ------------------------------------------------------------------
-- schools.theme_id is deprecated (see template_color_split.sql) but was
-- left in place as a legacy fallback for schools created before the
-- template/color split. New signups never set it anymore, which was
-- failing the NOT NULL + foreign key constraint on every new school
-- creation. Made nullable so new rows can simply leave it NULL.
-- Safe to re-run.
-- ------------------------------------------------------------------

ALTER TABLE schools MODIFY COLUMN theme_id INT NULL;
