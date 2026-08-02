-- ============================================================
-- Content audit log — tracks every change to a school's public
-- content and fee/payment details, with who changed it and when.
-- ============================================================

CREATE TABLE content_audit_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,          -- who made the change (NULL if system-generated)
    entity_type     ENUM('section','fee') NOT NULL,
    entity_id       INT UNSIGNED NOT NULL,       -- site_sections.id or fee_structures.id
    action          ENUM('create','update','delete') NOT NULL DEFAULT 'update',
    old_value       JSON NULL,
    new_value       JSON NULL,
    changed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_school_time (school_id, changed_at)
);

-- ============================================================
-- ID verification — ties real-world accountability to the specific
-- person managing a school's account, not just the school itself.
-- ============================================================

ALTER TABLE users ADD COLUMN id_number VARCHAR(20) NULL AFTER phone;
ALTER TABLE users ADD COLUMN id_document_path VARCHAR(255) NULL AFTER id_number;
ALTER TABLE users ADD COLUMN id_verified_at TIMESTAMP NULL AFTER id_document_path;
