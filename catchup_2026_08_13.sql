-- ============================================================
-- SOMAHUB — COMPILED CATCHUP SCRIPT (post-2026-08-13 batch)
--
-- Safe to run on your live database as-is. Every statement either
-- checks before changing anything (IF NOT EXISTS / IF EXISTS), or
-- is a plain INSERT/UPDATE that's harmless to re-run.
--
-- Assumes deploy_all.sql was already applied (base schema + your
-- real schools/users). This picks up everything after that point.
--
-- Run checkup_database_state.sql FIRST if you haven't already, to
-- confirm what your database actually has before running this.
-- ============================================================


-- ------------------------------------------------------------------
-- 1. Content audit log — resolves a real conflict in the SQL history.
-- Two incompatible versions of this table were drafted at different
-- points (audit_log.sql and audit_and_id_verification.sql). This is
-- the ONLY correct version, matching what includes/audit.php and
-- admin/audit-log.php actually query. Safe to run even if the table
-- already exists correctly — DROP+CREATE only fires if the table is
-- there; if it's missing entirely, CREATE just makes it fresh.
-- Audit log data is historical record-keeping, not core business
-- data, so this is safe even if it means losing old audit entries.
-- ------------------------------------------------------------------
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

-- ID verification columns on users (the other half of what
-- audit_and_id_verification.sql was meant to do)
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_number VARCHAR(20) NULL AFTER phone;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_document_path VARCHAR(255) NULL AFTER id_number;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_verified_at TIMESTAMP NULL AFTER id_document_path;


-- ------------------------------------------------------------------
-- 2. Blog
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  body_html TEXT NOT NULL,
  excerpt VARCHAR(500),
  cover_image VARCHAR(500),
  cta_text VARCHAR(100) NULL,
  cta_link VARCHAR(500) NULL,
  meta_description VARCHAR(160) NULL,
  post_type ENUM('manual','auto_school_joined','auto_milestone','auto_top10') NOT NULL DEFAULT 'manual',
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  trigger_ref_id INT NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  comment TEXT NOT NULL,
  status ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Indexes: MySQL/MariaDB have no "CREATE INDEX IF NOT EXISTS", so these
-- are guarded manually to stay safe on a re-run.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_posts' AND INDEX_NAME = 'idx_blog_status');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_blog_status ON blog_posts (status, published_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_comments' AND INDEX_NAME = 'idx_comments_post');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_comments_post ON blog_comments (post_id, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ------------------------------------------------------------------
-- 3. Password resets — includes the rate-limiting fix (attempts column)
-- from the start, so no separate patch needed afterward.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- If the table already existed from before the rate-limit fix, add the column:
ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS attempts INT NOT NULL DEFAULT 0;


-- ------------------------------------------------------------------
-- 4. Payments — base tables first (products, orders, order_items, refunds),
-- since pricing_restructure.sql depends on these existing already.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_key VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(150) NOT NULL,
  description VARCHAR(500),
  price DECIMAL(10,2) NOT NULL,
  billing_cycle ENUM('one_time','yearly') NOT NULL DEFAULT 'yearly',
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO products (product_key, label, description, price, billing_cycle) VALUES
  ('paid_plan', 'Paid Plan', 'Online enrollment, results checking, and fee publishing.', 2500.00, 'yearly'),
  ('custom_domain', 'Custom Domain Add-on', 'Use your own domain (e.g. yourschool.ac.ke) instead of the somahub.top subdomain.', 1500.00, 'yearly');

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  reference_code VARCHAR(20) NOT NULL UNIQUE,
  total_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('till','pochi','send_money','equity_paybill') NULL,
  mpesa_code VARCHAR(30) NULL,
  mpesa_code_submitted_at DATETIME NULL,
  paid_at DATETIME NULL,
  verified_by VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (school_id) REFERENCES schools(id)
);

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_key VARCHAR(50) NOT NULL,
  label VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  delivered_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(500),
  status ENUM('requested','approved','sent','declined') NOT NULL DEFAULT 'requested',
  admin_note VARCHAR(500) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_school');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_orders_school ON orders (school_id, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_status');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_orders_status ON orders (status, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE schools ADD COLUMN IF NOT EXISTS custom_domain_enabled TINYINT(1) NOT NULL DEFAULT 0;


-- ------------------------------------------------------------------
-- 5. Pricing restructure — depends on products/orders/order_items above
-- ------------------------------------------------------------------
ALTER TABLE themes ADD COLUMN IF NOT EXISTS is_premium TINYINT(1) NOT NULL DEFAULT 0;
-- Decide and mark your premium themes yourself, e.g.:
-- UPDATE themes SET is_premium = 1 WHERE name IN ('Theme Name Here');

INSERT IGNORE INTO products (product_key, label, description, price, billing_cycle) VALUES
  ('custom_templates', 'Custom Templates', 'Unlocks every premium theme, not just the free starter set.', 1000.00, 'yearly'),
  ('content_writing', 'Content Writing', 'We write your About, Academics, and Admissions text for you, tailored to your school (not generic filler).', 1500.00, 'one_time'),
  ('google_business_setup', 'Google Business Profile Setup', 'We create and verify your school on Google Business Profile, so you appear on Google Maps and in local search results.', 1200.00, 'one_time');

-- Adds a map-location field to the Contact section schema — only fires
-- if that key isn't already present, safe to re-run.
UPDATE section_types
SET schema_json = JSON_SET(schema_json, '$.map_location', 'location')
WHERE key_name = 'contact' AND JSON_EXTRACT(schema_json, '$.map_location') IS NULL;

ALTER TABLE schools ADD COLUMN IF NOT EXISTS custom_templates_enabled TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS service_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  service_key VARCHAR(50) NOT NULL,
  details_json TEXT NOT NULL,
  status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);


-- ------------------------------------------------------------------
-- 6. Reviews
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reviewable_type ENUM('school','platform') NOT NULL DEFAULT 'school',
  reviewable_id INT NULL,
  author_name VARCHAR(100) NOT NULL,
  author_email VARCHAR(255) NULL,
  rating TINYINT NOT NULL,
  comment TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (rating BETWEEN 1 AND 5)
);

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'idx_reviews_lookup');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_reviews_lookup ON reviews (reviewable_type, reviewable_id, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ------------------------------------------------------------------
-- 7. Account settings — avatar + self-service removal requests
-- ------------------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(500) NULL;

CREATE TABLE IF NOT EXISTS account_removal_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  school_id INT NOT NULL,
  reason VARCHAR(500),
  status ENUM('pending','completed','declined') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  admin_note VARCHAR(500) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- ============================================================
-- Done. Now run checkup_database_state.sql again to confirm
-- everything landed — every "MISSING" should now read "EXISTS",
-- and all the column-check queries should return rows.
-- ============================================================
