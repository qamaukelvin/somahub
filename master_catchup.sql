-- ============================================================
-- SOMAHUB — MASTER CATCHUP (safe to run anytime, any number of times)
-- Covers everything from first_login_tracking.sql onward. Does NOT
-- repeat anything from catchup_v2_safe.sql (content_audit_log, blog,
-- password_resets, products/orders/refunds, reviews, account removal,
-- users.avatar_path/id_number etc) — that one already ran successfully.
-- Every ALTER uses a dynamic existence check; every CREATE uses
-- IF NOT EXISTS; every INSERT checks first. Nothing here can error
-- regardless of what has or hasn't already been applied.
-- ============================================================

-- ------------------------------------------------------------------
-- 1. schools.first_login_at (verification grace period anchor)
-- ------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'first_login_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN first_login_at TIMESTAMP NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 2. schools.activate_trial_on_login (lead-conversion trial timing)
-- ------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'activate_trial_on_login');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN activate_trial_on_login TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 3. Magic login tokens + users.password_is_temp
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS magic_login_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'magic_login_tokens' AND INDEX_NAME = 'idx_magic_login_token');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_magic_login_token ON magic_login_tokens (token_hash)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_is_temp');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN password_is_temp TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 4. Notifications
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(200) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link VARCHAR(300) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'idx_notifications_school');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_notifications_school ON notifications (school_id, is_read, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 5. Attendance
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_uploads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  uploaded_by_user_id INT UNSIGNED NULL,
  term_label VARCHAR(100) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  status ENUM('processing','ready','failed') NOT NULL DEFAULT 'processing',
  row_count INT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_rows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attendance_upload_id INT NOT NULL,
  school_id INT UNSIGNED NOT NULL,
  admission_no VARCHAR(50) NOT NULL,
  student_name VARCHAR(150) NULL,
  days_present INT NULL,
  days_absent INT NULL,
  days_late INT NULL,
  FOREIGN KEY (attendance_upload_id) REFERENCES attendance_uploads(id) ON DELETE CASCADE
);

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance_rows' AND INDEX_NAME = 'idx_attendance_lookup');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_attendance_lookup ON attendance_rows (school_id, admission_no)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 6. Contact messages (homepage contact form)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------------
-- 7. Reminder tracking (used by cron-daily-reminders.php)
-- This is the one most likely still missing — the original file used
-- unsupported "ADD COLUMN IF NOT EXISTS" syntax that errors on this host.
-- ------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'plan_reminder_sent_7d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN plan_reminder_sent_7d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'plan_reminder_sent_1d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN plan_reminder_sent_1d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'plan_reminder_sent_expired');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN plan_reminder_sent_expired TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'verify_reminder_sent_3d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN verify_reminder_sent_3d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'verify_reminder_sent_1d');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN verify_reminder_sent_1d TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'verify_reminder_sent_offline');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE schools ADD COLUMN verify_reminder_sent_offline TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 8. Premium templates (theme rows) — guarded against duplicate inserts
-- ------------------------------------------------------------------
INSERT INTO themes (name, css_variables_json, custom_css, is_premium, is_active)
SELECT 'Editorial', '{"primary":"#2B2118","accent":"#B5482A","bg":"#F5EEE1","font_display":"Playfair Display","font_body":"Source Sans 3"}',
'
  h1 { font-size: clamp(2.1rem, 5vw, 3.4rem); line-height: 1.05; font-weight: 700; }
  .hero { background: var(--bg); color: var(--primary); padding: 100px 24px 70px; }
  .hero-inner { grid-template-columns: 0.9fr 1.1fr; }
  .hero-inner > div:first-child { order: 2; }
  .hero-photo, .hero-mosaic { order: 1; }
  .hero-photo img { aspect-ratio: 4/5; object-fit: cover; border-radius: 2px; }
  .hero p { font-size: 1.05rem; border-left: 3px solid var(--accent); padding-left: 16px; margin-top: 18px; }
  .section-head { border-bottom: 2px solid var(--accent); padding-bottom: 14px; }
  .section-head h2 { font-size: 1.6rem; text-transform: uppercase; letter-spacing: 0.04em; }
  .about-grid { grid-template-columns: 0.85fr 1.15fr; }
  .about-photo { border-radius: 2px; }
  .about-grid p:first-of-type { font-size: 1.2rem; font-style: italic; color: var(--primary); border-left: 3px solid var(--accent); padding-left: 18px; }
  .gallery-grid { grid-template-columns: repeat(6, 1fr); grid-auto-rows: 140px; gap: 10px; }
  .gallery-grid img { border-radius: 2px; }
  .gallery-grid > *:nth-child(6n+1) { grid-column: span 3; grid-row: span 2; }
  .gallery-grid > *:nth-child(6n+4) { grid-column: span 3; }
  @media(max-width:820px){ .gallery-grid { grid-template-columns: repeat(2,1fr); } .gallery-grid > * { grid-column: span 1 !important; grid-row: span 1 !important; } }
  .staff-grid, .testimonial-grid { gap: 28px; }
  .testimonial-card { border: none; border-top: 3px solid var(--accent); border-radius: 0; padding-top: 18px; background: transparent; box-shadow: none; }
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM themes WHERE name = 'Editorial');

INSERT INTO themes (name, css_variables_json, custom_css, is_premium, is_active)
SELECT 'Bold Blocks', '{"primary":"#0B2C4D","accent":"#FF6B35","bg":"#F7F9FC","font_display":"Poppins","font_body":"Inter"}',
'
  h1, h2, h3 { font-weight: 700; }
  .hero { background: var(--primary); padding: 70px 24px 110px; position: relative; }
  .hero-inner { align-items: start; }
  .hero h1 { color: #fff; }
  .hero p { color: rgba(255,255,255,0.85); }
  .hero-photo img, .hero-mosaic .m-main { border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); border: 5px solid #fff; transform: translateY(40px); }
  section { padding: 60px 24px; }
  .section-head h2 { display: inline-block; background: var(--accent); color: #fff; padding: 6px 16px; border-radius: 8px; font-size: 1.1rem; }
  .about-grid > div, .about-photo { border-radius: 16px; }
  .about-photo { box-shadow: 0 14px 30px rgba(11,44,77,0.12); }
  .staff-grid > *, .testimonial-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 8px 24px rgba(11,44,77,0.08); border: none; transition: transform 0.15s ease; }
  .staff-grid > *:hover, .testimonial-card:hover { transform: translateY(-4px); }
  .gallery-grid { grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); gap: 16px; }
  .gallery-grid img { border-radius: 14px; transition: transform 0.2s ease; }
  .gallery-grid img:hover { transform: scale(1.04); }
  .btn-primary, .plan-cta, .navcta { border-radius: 10px !important; }
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM themes WHERE name = 'Bold Blocks');

INSERT INTO themes (name, css_variables_json, custom_css, is_premium, is_active)
SELECT 'Minimal Mono', '{"primary":"#1A1A1A","accent":"#5B6E5B","bg":"#FFFFFF","font_display":"Space Grotesk","font_body":"Inter"}',
'
  body { line-height: 1.75; }
  h1, h2, h3 { font-weight: 500; letter-spacing: -0.01em; }
  section { padding: 70px 24px; }
  .hero { background: var(--bg); color: var(--primary); padding: 120px 24px 80px; }
  .hero-inner { gap: 60px; }
  .hero h1 { font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 400; }
  .hero p { color: #555; font-size: 1rem; }
  .hero-photo img, .hero-mosaic .m-main { border-radius: 0; filter: grayscale(15%); }
  .section-head { border-bottom: none; margin-bottom: 48px; }
  .section-head h2 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.18em; color: #777; font-weight: 500; }
  .about-photo, .gallery-grid img { border-radius: 0; filter: grayscale(10%); }
  .about-grid { gap: 60px; }
  .staff-grid > *, .testimonial-card { background: transparent; box-shadow: none; border: none; border-top: 1px solid #E5E5E0; padding-top: 20px; border-radius: 0; }
  .gallery-grid { gap: 2px; }
  .btn-primary, .plan-cta { background: transparent !important; color: var(--primary) !important; border: 1px solid var(--primary) !important; border-radius: 0 !important; box-shadow: none !important; }
  .navcta { border-radius: 0 !important; }
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM themes WHERE name = 'Minimal Mono');

INSERT INTO themes (name, css_variables_json, custom_css, is_premium, is_active)
SELECT 'Vibrant Photo', '{"primary":"#7A2E8E","accent":"#FFC93C","bg":"#FFF8EE","font_display":"Baloo 2","font_body":"Nunito Sans"}',
'
  .hero { position: relative; overflow: hidden; padding: 150px 24px 100px; min-height: 420px; display: flex; align-items: center; }
  .hero::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(122,46,142,0.25), rgba(20,10,25,0.65)); z-index: 1; }
  .hero-photo, .hero-mosaic { position: absolute; inset: 0; z-index: 0; margin: 0; }
  .hero-photo img, .hero-mosaic img, .hero-mosaic .m-main { width: 100%; height: 100%; object-fit: cover; border-radius: 0; border: none; box-shadow: none; }
  .hero-inner { position: relative; z-index: 2; grid-template-columns: 1fr; text-align: center; max-width: 720px; }
  .hero h1 { color: #fff; font-size: clamp(2.2rem, 6vw, 3.6rem); text-shadow: 0 2px 12px rgba(0,0,0,0.3); }
  .hero p { color: #fff; font-size: 1.1rem; text-shadow: 0 1px 6px rgba(0,0,0,0.3); }
  .section-head h2 { color: var(--primary); }
  .section-head { text-align: center; margin-left: auto; margin-right: auto; }
  .about-photo, .gallery-grid img { border-radius: 24px; }
  .about-grid { align-items: center; }
  .staff-grid > *, .testimonial-card { border-radius: 24px; border: none; background: #fff; box-shadow: 0 10px 26px rgba(122,46,142,0.1); }
  .gallery-grid { grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 18px; }
  .gallery-grid img { aspect-ratio: 1/1; }
  .btn-primary, .plan-cta, .navcta { background: var(--accent) !important; color: #3D2200 !important; border-radius: 999px !important; font-weight: 700 !important; }
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM themes WHERE name = 'Vibrant Photo');

-- ------------------------------------------------------------------
-- 9. Premium tier restructure (Paid -> Premium, KSh 3,000)
-- Safe to re-run — UPDATE with WHERE, no duplication risk.
-- ------------------------------------------------------------------
UPDATE products
SET label = 'Premium Plan',
    description = 'Enrollment forms, the full report (results, attendance, position, trends, fees), and every premium theme.',
    price = 3000.00
WHERE product_key = 'paid_plan';

UPDATE products SET is_active = 0 WHERE product_key = 'custom_templates';

-- ============================================================
-- VERIFY — run this after, confirm all columns show up:
-- SELECT COLUMN_NAME FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools'
-- AND COLUMN_NAME IN ('first_login_at','activate_trial_on_login',
--   'plan_reminder_sent_7d','plan_reminder_sent_1d','plan_reminder_sent_expired',
--   'verify_reminder_sent_3d','verify_reminder_sent_1d','verify_reminder_sent_offline');
-- Should return all 8 rows.
-- ============================================================
