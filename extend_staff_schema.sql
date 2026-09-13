-- ------------------------------------------------------------------
-- Extends Staff's schema from a hard cap of 4 people to 10, plus a bio
-- and a department field per person for the upcoming "List with bio"
-- and "Grouped by department" variants. Existing schools are
-- unaffected - purely additive, empty by default.
--
-- Note on org-chart variant: there's no explicit "tier" field - it
-- infers seniority from list position (person 1 = top tier, 2-3 =
-- second tier, rest = third tier), documented in site.php where it's
-- rendered. Adding an explicit tier field is a reasonable future
-- improvement if that convention proves confusing in practice.
--
-- Safe to re-run.
-- ------------------------------------------------------------------

UPDATE section_types
SET schema_json = JSON_SET(
    schema_json,
    '$.name_5', 'text',
    '$.role_5', 'text',
    '$.photo_5', 'image',
    '$.name_6', 'text',
    '$.role_6', 'text',
    '$.photo_6', 'image',
    '$.name_7', 'text',
    '$.role_7', 'text',
    '$.photo_7', 'image',
    '$.name_8', 'text',
    '$.role_8', 'text',
    '$.photo_8', 'image',
    '$.name_9', 'text',
    '$.role_9', 'text',
    '$.photo_9', 'image',
    '$.name_10', 'text',
    '$.role_10', 'text',
    '$.photo_10', 'image',
    '$.bio_1', 'textarea',
    '$.bio_2', 'textarea',
    '$.bio_3', 'textarea',
    '$.bio_4', 'textarea',
    '$.bio_5', 'textarea',
    '$.bio_6', 'textarea',
    '$.bio_7', 'textarea',
    '$.bio_8', 'textarea',
    '$.bio_9', 'textarea',
    '$.bio_10', 'textarea',
    '$.department_1', 'text',
    '$.department_2', 'text',
    '$.department_3', 'text',
    '$.department_4', 'text',
    '$.department_5', 'text',
    '$.department_6', 'text',
    '$.department_7', 'text',
    '$.department_8', 'text',
    '$.department_9', 'text',
    '$.department_10', 'text'
)
WHERE key_name = 'staff';
