-- ============================================================
-- Content audit log — a permanent record of every change made to
-- a school's site content, for dispute resolution and integrity
-- monitoring. Never deleted, never editable, append-only.
-- ============================================================

CREATE TABLE content_audit_log (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NULL,          -- who made the change; NULL if system-generated
    section_id          INT UNSIGNED NULL,          -- the section affected, if applicable
    section_key         VARCHAR(40) NULL,           -- e.g. 'hero', 'contact' — kept even if the section is later deleted
    action              ENUM('content_updated','section_added','section_removed','section_duplicated','visibility_toggled','reordered') NOT NULL,
    old_content_json    TEXT NULL,
    new_content_json    TEXT NULL,
    ip_address          VARCHAR(45) NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_school_time (school_id, created_at)
);
