-- ------------------------------------------------------------------
-- Extends the About section type's schema with fields its new layout
-- variants need. Existing schools are unaffected - these are additive
-- fields, empty by default, and About's existing text_only/photo_right
-- rendering (the current default look) doesn't require any of them.
--
-- Safe to re-run (JSON_SET on an already-present key is a no-op change).
-- ------------------------------------------------------------------

UPDATE section_types
SET schema_json = JSON_SET(
    schema_json,
    '$.photo_2', 'image',
    '$.photo_3', 'image',
    '$.list_items', 'textarea',
    '$.author_name', 'text',
    '$.inline_stat_1', 'text',
    '$.inline_stat_1_label', 'text',
    '$.inline_stat_2', 'text',
    '$.inline_stat_2_label', 'text'
)
WHERE key_name = 'about';
