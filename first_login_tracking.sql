-- The verification grace period should count from when a school's owner
-- actually first logs in and can start engaging, not from whenever an
-- admin created the account on their behalf. Without this, an admin-created
-- school could have its site go offline before the owner ever sees it.
ALTER TABLE schools ADD COLUMN IF NOT EXISTS first_login_at TIMESTAMP NULL;
