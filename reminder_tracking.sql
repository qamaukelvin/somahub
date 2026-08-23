-- Tracks which reminder thresholds have already been sent, so the daily
-- cron never emails the same warning twice for the same school.
ALTER TABLE schools ADD COLUMN IF NOT EXISTS plan_reminder_sent_7d TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS plan_reminder_sent_1d TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS plan_reminder_sent_expired TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS verify_reminder_sent_3d TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS verify_reminder_sent_1d TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS verify_reminder_sent_offline TINYINT(1) NOT NULL DEFAULT 0;
