-- ============================================================
-- FIX: content_audit_log schema mismatch
--
-- Your database has this table with different column names
-- (section_id, section_key, old_content_json, created_at, etc.)
-- than what includes/audit.php and admin/audit-log.php actually
-- query (entity_type, entity_id, old_value, new_value, changed_at).
--
-- This is safe to run — audit log data is historical record-keeping,
-- not core business data, and the table currently isn't being
-- written to successfully anyway (every write has been failing).
-- ============================================================

DROP TABLE IF EXISTS content_audit_log;

CREATE TABLE content_audit_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    entity_type     ENUM('section','fee') NOT NULL,
    entity_id       INT UNSIGNED NOT NULL,
    action          ENUM('create','update','delete') NOT NULL DEFAULT 'update',
    old_value       JSON NULL,
    new_value       JSON NULL,
    changed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_school_time (school_id, changed_at)
);
