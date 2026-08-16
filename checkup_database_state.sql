-- ============================================================
-- DATABASE STATE CHECKUP — READ ONLY, MAKES NO CHANGES
-- Run this first. Paste the full results back so we can confirm
-- exactly what's missing before running the catchup script.
-- ============================================================

-- 1. Which of the expected tables actually exist?
SELECT TABLE_NAME AS 'table_expected',
    CASE WHEN TABLE_NAME IN (
        SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
    ) THEN 'EXISTS' ELSE 'MISSING' END AS status
FROM (
    SELECT 'schools' AS TABLE_NAME UNION SELECT 'users' UNION SELECT 'themes' UNION
    SELECT 'section_types' UNION SELECT 'site_sections' UNION SELECT 'media' UNION
    SELECT 'enrollment_applications' UNION SELECT 'result_uploads' UNION SELECT 'result_rows' UNION
    SELECT 'fee_structures' UNION SELECT 'leads' UNION SELECT 'content_audit_log' UNION
    SELECT 'blog_posts' UNION SELECT 'blog_comments' UNION
    SELECT 'password_resets' UNION
    SELECT 'products' UNION SELECT 'orders' UNION SELECT 'order_items' UNION SELECT 'refunds' UNION
    SELECT 'service_requests' UNION
    SELECT 'reviews' UNION
    SELECT 'account_removal_requests'
) AS expected
ORDER BY status DESC, table_expected;

-- 2. If content_audit_log EXISTS, which schema version does it actually have?
-- (This is the one table with a known history of conflicting definitions —
-- this tells us whether it's the correct version or the old broken one.)
SELECT COLUMN_NAME, DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'content_audit_log'
ORDER BY ORDINAL_POSITION;
-- Correct version has: entity_type, entity_id, old_value, new_value, changed_at
-- Old/wrong version has: section_id, section_key, old_content_json, created_at

-- 3. Does password_resets have the attempts column (rate-limiting fix)?
SELECT COUNT(*) AS has_rate_limiting
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND COLUMN_NAME = 'attempts';
-- 1 = yes, 0 = table exists but missing the rate-limit fix, needs ALTER

-- 4. Does order_items have the delivered_at column (from pricing_restructure.sql)?
SELECT COUNT(*) AS has_delivered_at
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'delivered_at';

-- 5. Does schools have the custom_domain_enabled / custom_templates_enabled columns?
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools'
    AND COLUMN_NAME IN ('custom_domain_enabled', 'custom_templates_enabled', 'verification_status', 'primary_override', 'bg_override');

-- 6. Does users have the id_verification and job_title columns?
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
    AND COLUMN_NAME IN ('id_number', 'id_document_path', 'id_verified_at', 'job_title', 'avatar_path');

-- 7. Does themes have is_premium (from pricing_restructure.sql)?
SELECT COUNT(*) AS has_theme_premium_flag
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'themes' AND COLUMN_NAME = 'is_premium';
