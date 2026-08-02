-- ============================================================
-- SOMAHUB — FULL PRODUCTION DEPLOYMENT SCRIPT (v2 — domain typo fixed)
-- Run this ONCE, top to bottom, on a fresh empty database.
-- Combines: schema.sql + seed.sql + seed_three_schools.sql +
-- theme_upgrade.sql + sections_and_themes_upgrade.sql +
-- section_categories.sql + hero_cta_and_colors.sql + verification.sql
-- ============================================================


-- ============================================================
-- FROM: schema.sql
-- ============================================================
-- ============================================================
-- SOMAHUB.TOP — School Website Platform: Core Schema (v1)
-- ============================================================

-- -------------------------------------------------------------
-- 1. THEMES  (reusable visual themes; sections render inside these)
-- -------------------------------------------------------------
CREATE TABLE themes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(60) NOT NULL,
    preview_media_id    INT UNSIGNED NULL,
    css_variables_json  JSON NOT NULL,      -- {"primary":"#1B4D3E","font_display":"Sora", ...}
    is_active           BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 2. SCHOOLS  (one row per customer/tenant)
-- -------------------------------------------------------------
CREATE TABLE schools (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    slug                VARCHAR(60)  NOT NULL UNIQUE,       -- e.g. "kinangoppride" -> kinangoppride.somahub.top
    custom_domain       VARCHAR(150) NULL UNIQUE,           -- e.g. "kinangoppride.ac.ke" once they bring their own
    theme_id            INT UNSIGNED NOT NULL,
    plan                ENUM('free','paid','promo_paid') NOT NULL DEFAULT 'free',
    promo_ends_at       DATE NULL,                          -- for the free-term promo tracking
    status              ENUM('active','suspended','trial') NOT NULL DEFAULT 'trial',
    county              VARCHAR(60) NULL,
    town                VARCHAR(60) NULL,
    phone               VARCHAR(30) NULL,
    email               VARCHAR(150) NULL,
    logo_media_id       INT UNSIGNED NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (theme_id) REFERENCES themes(id)
);

-- -------------------------------------------------------------
-- 3. USERS  (dashboard logins — school staff + your own admin/staff)
-- -------------------------------------------------------------
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NULL,      -- NULL for your internal platform-admin accounts
    name                VARCHAR(100) NOT NULL,
    email               VARCHAR(150) NOT NULL UNIQUE,
    phone               VARCHAR(30) NULL,
    password_hash       VARCHAR(255) NOT NULL,
    role                ENUM('platform_admin','school_owner','school_editor') NOT NULL DEFAULT 'school_editor',
    last_login_at       TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- -------------------------------------------------------------
-- 4. SECTION TYPES  (the catalogue: home, about, academics, etc.)
-- -------------------------------------------------------------
CREATE TABLE section_types (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name            VARCHAR(40) NOT NULL UNIQUE,   -- 'hero','about','academics','admissions','gallery','contact','blog','fees','results_lookup','enrollment_form'
    label               VARCHAR(60) NOT NULL,
    schema_json         JSON NOT NULL,   -- defines the editable fields for this section type & their types
    is_premium          BOOLEAN DEFAULT FALSE  -- e.g. results_lookup / enrollment_form gated to paid plan
);

-- -------------------------------------------------------------
-- 5. SITE SECTIONS  (a school's actual, ordered, editable page — this is the core of "reorganize/remove/duplicate")
-- -------------------------------------------------------------
CREATE TABLE site_sections (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NOT NULL,
    section_type_id     INT UNSIGNED NOT NULL,
    position            SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- controls display order; drag-reorder updates this
    is_visible          BOOLEAN DEFAULT TRUE,                   -- toggle without deleting
    content_json        JSON NOT NULL,                          -- actual filled content matching section_types.schema_json
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (section_type_id) REFERENCES section_types(id),
    INDEX idx_school_position (school_id, position)
);
-- Duplicating a section = INSERT a new row copying content_json, same school_id, new position.
-- Removing = soft delete via is_visible=FALSE, or hard DELETE if you don't need undo.

-- -------------------------------------------------------------
-- 6. MEDIA  (uploaded images — gallery, logos, hero photos)
-- -------------------------------------------------------------
CREATE TABLE media (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NOT NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    file_path           VARCHAR(255) NOT NULL,   -- path on shared hosting, e.g. /uploads/schools/12/img_xxx.jpg
    file_type           VARCHAR(30) NOT NULL,    -- 'image/jpeg' etc
    file_size_bytes     INT UNSIGNED NULL,
    alt_text            VARCHAR(150) NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
);

-- ============================================================
-- PAID-TIER FEATURES
-- ============================================================

-- -------------------------------------------------------------
-- 7. ENROLLMENT APPLICATIONS
-- -------------------------------------------------------------
CREATE TABLE enrollment_applications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NOT NULL,
    child_name          VARCHAR(100) NOT NULL,
    date_of_birth       DATE NOT NULL,
    grade_applying_for  VARCHAR(30) NOT NULL,
    previous_school     VARCHAR(150) NULL,
    parent_name         VARCHAR(100) NOT NULL,
    parent_phone        VARCHAR(30) NOT NULL,
    parent_email        VARCHAR(150) NULL,
    status              ENUM('new','contacted','enrolled','declined') NOT NULL DEFAULT 'new',
    submitted_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_school_status (school_id, status)
);
-- On submit: trigger email/SMS notification to school's registered contact. No auth needed to submit.

-- -------------------------------------------------------------
-- 8. RESULT UPLOADS  (one row per Excel file uploaded, per term)
-- -------------------------------------------------------------
CREATE TABLE result_uploads (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NOT NULL,
    uploaded_by_user_id INT UNSIGNED NOT NULL,
    term_label          VARCHAR(30) NOT NULL,      -- e.g. "Term 2, 2026"
    original_filename   VARCHAR(150) NOT NULL,
    stored_path         VARCHAR(255) NOT NULL,     -- keep the raw file for audit/reprocessing
    status              ENUM('processing','ready','failed') NOT NULL DEFAULT 'processing',
    row_count            INT UNSIGNED NULL,
    uploaded_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
);

-- -------------------------------------------------------------
-- 9. RESULT ROWS  (the parsed, per-student data from the Excel sheet)
-- -------------------------------------------------------------
CREATE TABLE result_rows (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    result_upload_id    INT UNSIGNED NOT NULL,
    school_id           INT UNSIGNED NOT NULL,       -- denormalized for fast lookup without a join
    admission_no        VARCHAR(30) NOT NULL,
    student_name        VARCHAR(100) NOT NULL,
    date_of_birth       DATE NULL,                    -- used as the 2nd factor for lookup, if school provides it
    grade                VARCHAR(30) NULL,
    scores_json          JSON NOT NULL,                -- {"Math":78,"English":85,"Science":90, ...}
    total                DECIMAL(6,2) NULL,
    position_in_class    SMALLINT UNSIGNED NULL,
    FOREIGN KEY (result_upload_id) REFERENCES result_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_lookup (school_id, admission_no)
);
-- Public results-check endpoint queries: WHERE school_id=? AND admission_no=? AND (name matches OR dob matches)
-- Never expose a "list all" endpoint publicly — lookup by exact admission_no only.

-- -------------------------------------------------------------
-- 10. FEE STRUCTURE  (simple, static — no ledgers/balances in v1)
-- -------------------------------------------------------------
CREATE TABLE fee_structures (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id           INT UNSIGNED NOT NULL,
    grade               VARCHAR(30) NOT NULL,
    term_label           VARCHAR(30) NOT NULL,
    amount               DECIMAL(10,2) NOT NULL,
    payment_details_json JSON NULL,    -- {"paybill":"XXXXXX","account":"admission_no","bank":"..."}
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_school_grade_term (school_id, grade, term_label)
);

-- -------------------------------------------------------------
-- 11. LEADS  (contact form submissions from the public homepage, before a school exists)
-- -------------------------------------------------------------
CREATE TABLE leads (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_name         VARCHAR(150) NOT NULL,
    contact_name        VARCHAR(100) NOT NULL,
    phone               VARCHAR(30) NOT NULL,
    email               VARCHAR(150) NULL,
    county              VARCHAR(60) NULL,
    message             TEXT NULL,
    status              ENUM('new','contacted','converted','declined') NOT NULL DEFAULT 'new',
    submitted_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- ============================================================
-- Notes on scaling this later:
-- - Add `subscriptions` table once billing gets more complex (multiple plan changes over time).
-- - Add `sms_log` / `email_log` tables once you wire up notifications for enrollment + results.
-- - result_rows.scores_json keeps subject lists flexible per school without schema migrations —
--   revisit as a normalized `result_subjects` table only if you need cross-school subject analytics later.
-- ============================================================


-- ============================================================
-- FROM: seed.sql
-- ============================================================
-- ============================================================
-- Seed data — run this AFTER schema.sql
-- ============================================================

-- Starter theme (matches the "Kinangop Pride" demo palette)
INSERT INTO themes (name, css_variables_json) VALUES
('Highland Green', '{"primary":"#1B4D3E","accent":"#F2B705","bg":"#FBF8F2","font_display":"Sora","font_body":"Nunito Sans"}');

-- Section types — schema_json defines the editable fields per section
INSERT INTO section_types (key_name, label, schema_json, is_premium) VALUES
('hero', 'Home / Hero', '{"headline":"text","subheading":"textarea","hero_photo":"image"}', 0),
('about', 'About Us', '{"body":"textarea","photo":"image"}', 0),
('academics', 'Academics', '{"body":"textarea"}', 0),
('admissions', 'Admissions', '{"body":"textarea"}', 0),
('gallery', 'Gallery', '{"caption":"text","photo_1":"image","photo_2":"image","photo_3":"image","photo_4":"image"}', 0),
('contact', 'Contact', '{"address":"text","phone":"text","email":"text","office_hours":"text"}', 0),
('blog', 'News / Blog', '{"title":"text","body":"textarea","photo":"image"}', 0),
('fees', 'Fee Structure', '{"intro_text":"textarea"}', 1),
('results_lookup', 'Results Check', '{"intro_text":"textarea"}', 1),
('enrollment_form', 'Enrollment Form', '{"intro_text":"textarea"}', 1);

-- Platform admin account — CHANGE THIS PASSWORD before deploying
-- Default password below is: changeme123
INSERT INTO users (school_id, name, email, password_hash, role) VALUES
(NULL, 'Platform Admin', 'admin@somahub.top', '$2y$10$5xUjV8W5v9v3NxrPz1BZ9OqL5eYVw6f8H9Q1z3T7X2m1YkC0r6Wai', 'platform_admin');
-- ^ Generate your own hash with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"


-- ============================================================
-- FROM: seed_three_schools.sql
-- ============================================================
-- ============================================================
-- Seed the three real schools with their actual content
-- Run this AFTER schema.sql and seed.sql
-- ============================================================

-- Extra theme for Engineer Central (bus livery yellow/charcoal, distinct from Highland Green)
INSERT INTO themes (name, css_variables_json) VALUES
('Bus Yellow', '{"primary":"#1B1B18","accent":"#F4B400","bg":"#F8F6EF","font_display":"Archivo","font_body":"Inter"}');

-- ------------------------------------------------------------------
-- SCHOOL 1: Kinangop Pride Primary School  (uses theme 1, Highland Green)
-- ------------------------------------------------------------------
INSERT INTO schools (name, slug, theme_id, plan, promo_ends_at, status, county, town, phone, email)
VALUES ('Kinangop Pride Primary School', 'kinangoppride', 1, 'promo_paid', DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'trial', 'Nyandarua', 'North Kinangop', '0723404162', 'kinangoppride@gmail.com');
SET @school1 := LAST_INSERT_ID();

INSERT INTO users (school_id, name, email, phone, password_hash, role)
VALUES (@school1, 'Head Teacher', 'kinangoppride@gmail.com', '0723404162', '$2b$10$aq3UsFhqMagI/h2PJfelH.mDbao6pwFxFjy/pM3Q07WxehVXPFcP.', 'school_owner');

INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json) VALUES
(@school1, (SELECT id FROM section_types WHERE key_name='hero'), 0, 1, JSON_OBJECT(
    'headline', 'Pride begins here.',
    'subheading', 'A community sponsored primary school in Engineer, North Kinangop, guiding learners from PP1 through Grade 8 with discipline, faith, and care.',
    'hero_photo', ''
)),
(@school1, (SELECT id FROM section_types WHERE key_name='about'), 1, 1, JSON_OBJECT(
    'body', 'Kinangop Pride Primary School sits in Engineer, North Kinangop, high in the Nyandarua highlands near the Aberdare foothills. We are a privately sponsored school offering the full primary curriculum under the Kenyan Ministry of Education.\nOur small class sizes and close knit staff mean every learner is known by name, not just a number on a register. We believe pride is not given, it is built, through discipline in the classroom, honesty on the playground, and consistency at home.',
    'photo', ''
)),
(@school1, (SELECT id FROM section_types WHERE key_name='academics'), 2, 1, JSON_OBJECT(
    'body', 'We offer the full CBC curriculum from Pre Primary through Junior School.\nPre Primary (PP1 to PP2): foundational literacy, numeracy, and play based learning.\nLower Primary (Grade 1 to 3): core literacy, mathematics, environmental activities, and creative arts.\nUpper Primary (Grade 4 to 6): expanded sciences, social studies, agriculture, and religious education.\nJunior School (Grade 7 to 8): subject specialization and pathway preparation.'
)),
(@school1, (SELECT id FROM section_types WHERE key_name='admissions'), 3, 1, JSON_OBJECT(
    'body', 'Enrollment for Term 2, 2026 is open for all grades, with limited spaces in PP1 and Grade 1.\nTo enroll, visit or call the school office to check space availability, then bring your child''s birth certificate, immunization card, and previous school report if transferring. A registration fee applies at the time of enrollment.'
)),
(@school1, (SELECT id FROM section_types WHERE key_name='gallery'), 4, 1, JSON_OBJECT(
    'caption', 'Life at Kinangop Pride',
    'photo_1', '', 'photo_2', '', 'photo_3', '', 'photo_4', ''
)),
(@school1, (SELECT id FROM section_types WHERE key_name='contact'), 5, 1, JSON_OBJECT(
    'address', 'Engineer, North Kinangop, Nyandarua County. P.O. Box 110, North Kinangop 20318',
    'phone', '0723 404 162',
    'email', 'kinangoppride@gmail.com',
    'office_hours', 'Mon to Fri, 8:00 AM to 4:45 PM'
));

-- ------------------------------------------------------------------
-- SCHOOL 2: Engineer Central Primary School  (uses theme 2, Bus Yellow)
-- ------------------------------------------------------------------
INSERT INTO schools (name, slug, theme_id, plan, promo_ends_at, status, county, town, phone, email)
VALUES ('Engineer Central Primary School', 'engineercentral', 2, 'promo_paid', DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'trial', 'Nyandarua', 'North Kinangop', '', '');
SET @school2 := LAST_INSERT_ID();

INSERT INTO users (school_id, name, email, phone, password_hash, role)
VALUES (@school2, 'Head Teacher', 'engineercentral@somahub.top', '', '$2b$10$aq3UsFhqMagI/h2PJfelH.mDbao6pwFxFjy/pM3Q07WxehVXPFcP.', 'school_owner');
-- NOTE: no real public email/phone was found for this school — using a placeholder login email.
-- Update this to their real email once you get it directly from the school.

INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json) VALUES
(@school2, (SELECT id FROM section_types WHERE key_name='hero'), 0, 1, JSON_OBJECT(
    'headline', 'Built on discipline. Measured by results.',
    'subheading', 'Engineer Central Primary School serves 215 learners from PP1 through Grade 8 in the heart of North Kinangop, with both day and boarding options.',
    'hero_photo', ''
)),
(@school2, (SELECT id FROM section_types WHERE key_name='about'), 1, 1, JSON_OBJECT(
    'body', 'Engineer Central Primary School is a privately sponsored institution located in North Kinangop, Nyandarua County, offering the full Kenyan primary curriculum from Pre Primary through Junior School, with both day and boarding options.\nOur name reflects the town we serve, a community built on precision and hard work. We bring that same precision into the classroom: clear expectations, consistent routines, and measurable progress for every learner.',
    'photo', ''
)),
(@school2, (SELECT id FROM section_types WHERE key_name='academics'), 2, 1, JSON_OBJECT(
    'body', 'Pre Primary (PP1 to PP2): foundational literacy, numeracy, and play based learning.\nLower Primary (Grade 1 to 3): core literacy, mathematics, and environmental activities.\nUpper Primary (Grade 4 to 6): sciences, social studies, agriculture, and religious education.\nJunior School (Grade 7 to 8): subject specialization and pathway preparation.\nBoarding is available on site alongside day schooling.'
)),
(@school2, (SELECT id FROM section_types WHERE key_name='admissions'), 3, 1, JSON_OBJECT(
    'body', 'Enrollment for Term 2, 2026 is currently open across all grades.\nTo enroll, contact the school office to confirm space availability, then prepare your child''s birth certificate, immunization record, and transfer letter if applicable. A registration fee applies, payable at the school office.'
)),
(@school2, (SELECT id FROM section_types WHERE key_name='gallery'), 4, 1, JSON_OBJECT(
    'caption', 'Around the school',
    'photo_1', '', 'photo_2', '', 'photo_3', '', 'photo_4', ''
)),
(@school2, (SELECT id FROM section_types WHERE key_name='contact'), 5, 1, JSON_OBJECT(
    'address', 'North Kinangop, Nyandarua County. P.O. Box 20318, North Kinangop',
    'phone', '',
    'email', '',
    'office_hours', 'Mon to Fri, 8:00 AM to 4:45 PM'
));

-- ------------------------------------------------------------------
-- SCHOOL 3: St. Paul's Kinangop Academy and Junior School  (uses theme 1, Highland Green)
-- ------------------------------------------------------------------
INSERT INTO schools (name, slug, theme_id, plan, promo_ends_at, status, county, town, phone, email)
VALUES ("St. Paul's Kinangop Academy and Junior School", 'stpaulskinangop', 1, 'promo_paid', DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'trial', 'Nyandarua', 'North Kinangop', '+254708708000', 'stpaulskinangopschool@gmail.com');
SET @school3 := LAST_INSERT_ID();

INSERT INTO users (school_id, name, email, phone, password_hash, role)
VALUES (@school3, 'Head Teacher', 'stpaulskinangopschool@gmail.com', '+254708708000', '$2b$10$aq3UsFhqMagI/h2PJfelH.mDbao6pwFxFjy/pM3Q07WxehVXPFcP.', 'school_owner');

INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json) VALUES
(@school3, (SELECT id FROM section_types WHERE key_name='hero'), 0, 1, JSON_OBJECT(
    'headline', 'Every day here is a story worth telling.',
    'subheading', "St. Paul's Kinangop Academy and Junior School nurtures learners from PP1 through Junior School, in the classroom, on the field, and everywhere in between.",
    'hero_photo', ''
)),
(@school3, (SELECT id FROM section_types WHERE key_name='about'), 1, 1, JSON_OBJECT(
    'body', "St. Paul's Kinangop Academy and Junior School is a private institution in North Kinangop, Nyandarua County, serving learners across two levels, the Academy and the Junior School, under one community and one set of values.\nFrom scouting and cultural days to science fairs and school trips, learning here goes well beyond the classroom. Our pupils travel, perform, and compete, and we believe every parent deserves to see that, not just hear about it secondhand.",
    'photo', ''
)),
(@school3, (SELECT id FROM section_types WHERE key_name='academics'), 2, 1, JSON_OBJECT(
    'body', "Junior School (Green Uniform): PP1 through the lower and middle primary grades, foundational literacy, numeracy, and CBC based learning.\nThe Academy (Navy Uniform): upper primary and Junior School learners preparing for the transition to senior school, with expanded subjects and pathway guidance.\nCo-curricular life: scouting, cultural days, sports, and school trips are a core part of how learners grow here, not an afterthought."
)),
(@school3, (SELECT id FROM section_types WHERE key_name='admissions'), 3, 1, JSON_OBJECT(
    'body', 'Enrollment for Term 2, 2026 is open across Junior School and Academy grades.\nTo enroll, contact the school office by phone or email to check space availability, then prepare your child''s birth certificate, immunization card, and previous school report if transferring. A registration fee applies at enrollment.'
)),
(@school3, (SELECT id FROM section_types WHERE key_name='gallery'), 4, 1, JSON_OBJECT(
    'caption', "Life at St. Paul's Kinangop",
    'photo_1', '', 'photo_2', '', 'photo_3', '', 'photo_4', ''
)),
(@school3, (SELECT id FROM section_types WHERE key_name='contact'), 5, 1, JSON_OBJECT(
    'address', 'North Kinangop, Nyandarua County',
    'phone', '+254 708 708 000',
    'email', 'stpaulskinangopschool@gmail.com',
    'office_hours', 'Mon to Fri, 8:00 AM to 4:45 PM'
));


-- ============================================================
-- FROM: theme_upgrade.sql
-- ============================================================
-- ============================================================
-- Theme upgrade: adds a custom_css field so themes can carry real
-- signature styling (not just color swaps), and extends the hero
-- section to support up to 3 photos for a mosaic layout.
-- Run this AFTER seed_three_schools.sql
-- ============================================================

ALTER TABLE themes ADD COLUMN custom_css TEXT NULL AFTER css_variables_json;

-- Let the hero section optionally hold up to 3 photos (site.php shows a
-- mosaic layout when more than one is present, otherwise a single image)
UPDATE section_types
SET schema_json = JSON_SET(schema_json, '$.hero_photo_2', 'image', '$.hero_photo_3', 'image')
WHERE key_name = 'hero';

-- ------------------------------------------------------------------
-- Highland Green (Kinangop Pride) — restore the original serif/gold identity
-- ------------------------------------------------------------------
UPDATE themes
SET css_variables_json = '{"primary":"#1F3D2F","accent":"#C9A227","bg":"#FBF8F2","font_display":"Fraunces","font_body":"Work Sans"}',
    custom_css = '
        h1, h2, h3 { letter-spacing: -0.01em; }
        .hero h1 { position: relative; padding-bottom: 18px; }
        .hero h1::after { content: ""; position: absolute; bottom: 0; left: 0; width: 70px; height: 3px; background: var(--accent); }
        .section-head h2 { position: relative; padding-left: 18px; }
        .section-head h2::before { content: ""; position: absolute; left: 0; top: 6px; bottom: 6px; width: 4px; background: var(--accent); }
        .navcta, .btn-primary { border-radius: 2px !important; }
    '
WHERE id = 1;

-- ------------------------------------------------------------------
-- Bus Yellow (Engineer Central) — restore the diagonal livery stripe
-- ------------------------------------------------------------------
UPDATE themes
SET custom_css = '
        .hero { position: relative; overflow: hidden; }
        .hero::before {
            content: ""; position: absolute; top: 0; right: 0; bottom: 0; width: 34%;
            background: repeating-linear-gradient(-45deg, var(--accent) 0 26px, var(--primary) 26px 32px);
            opacity: 0.9;
        }
        .hero-inner, .hero-mosaic { position: relative; z-index: 1; }
        .navcta, .btn-primary { border-radius: 0 !important; font-weight: 800; }
        .section-head h2::before { content: ""; display: inline-block; width: 14px; height: 14px; background: var(--accent); margin-right: 8px; vertical-align: middle; }
    '
WHERE id = 2;

-- ------------------------------------------------------------------
-- Canopy Emerald (St. Paul's) — new theme with a photo-mosaic hero identity
-- ------------------------------------------------------------------
INSERT INTO themes (name, css_variables_json, custom_css) VALUES (
    'Canopy Emerald',
    '{"primary":"#1B4D3E","accent":"#F2B705","bg":"#F7F3E6","font_display":"Sora","font_body":"Nunito Sans"}',
    '
        .navcta, .btn-primary { border-radius: 20px !important; }
        nav a { font-weight: 700; }
        .hero-photo img, .gallery-grid img, .about-photo img { border-radius: 12px !important; }
        .hero-mosaic { border-radius: 12px; overflow: hidden; }
        .hero-mosaic img { border-radius: 0 !important; }
    '
);

UPDATE schools SET theme_id = (SELECT id FROM themes WHERE name = 'Canopy Emerald')
WHERE slug = 'stpaulskinangop';


-- ============================================================
-- FROM: sections_and_themes_upgrade.sql
-- ============================================================
-- ============================================================
-- Adds theme variety, a lightweight per-school color override,
-- and 5 new section types for real design variety across sites.
-- Run this AFTER theme_upgrade.sql
-- ============================================================

-- Lets you nudge just the accent color per school without creating a whole new theme
-- (falls back to the theme's own accent if left blank)
ALTER TABLE schools ADD COLUMN accent_override VARCHAR(7) NULL AFTER theme_id;

-- ------------------------------------------------------------------
-- Three more preset themes, each with a distinct palette AND font pairing,
-- not just a recolor of the same layout
-- ------------------------------------------------------------------
INSERT INTO themes (name, css_variables_json, custom_css) VALUES
('Slate Indigo', '{"primary":"#2B2E6B","accent":"#FF6B4A","bg":"#F5F4FA","font_display":"Fraunces","font_body":"Inter"}',
 '.navcta, .btn-primary { border-radius: 6px !important; } .section-head h2 { border-left: 4px solid var(--accent); padding-left: 16px; }'),

('Coral Reef', '{"primary":"#0B6E6E","accent":"#FF8C61","bg":"#F4F9F8","font_display":"Poppins","font_body":"Karla"}',
 '.navcta, .btn-primary { border-radius: 30px !important; } .hero { background: linear-gradient(135deg, var(--primary), #0A5757); }'),

('Midnight Gold', '{"primary":"#12151C","accent":"#D4A93B","bg":"#F7F5EF","font_display":"Playfair Display","font_body":"Lora"}',
 '.hero { position:relative; } .hero::after{content:"";position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--accent);} .section-head h2::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--accent);margin-right:10px;}');

-- ------------------------------------------------------------------
-- 5 new section types
-- ------------------------------------------------------------------
INSERT INTO section_types (key_name, label, schema_json, is_premium) VALUES

('staff', 'Staff & Leadership',
 JSON_OBJECT(
   'intro_text', 'textarea',
   'name_1', 'text', 'role_1', 'text', 'photo_1', 'image',
   'name_2', 'text', 'role_2', 'text', 'photo_2', 'image',
   'name_3', 'text', 'role_3', 'text', 'photo_3', 'image',
   'name_4', 'text', 'role_4', 'text', 'photo_4', 'image'
 ), 0),

('testimonials', 'Parent Testimonials',
 JSON_OBJECT(
   'quote_1', 'textarea', 'author_1', 'text',
   'quote_2', 'textarea', 'author_2', 'text',
   'quote_3', 'textarea', 'author_3', 'text'
 ), 0),

('faq', 'Frequently Asked Questions',
 JSON_OBJECT(
   'question_1', 'text', 'answer_1', 'textarea',
   'question_2', 'text', 'answer_2', 'textarea',
   'question_3', 'text', 'answer_3', 'textarea',
   'question_4', 'text', 'answer_4', 'textarea'
 ), 0),

('stats', 'School Highlights',
 JSON_OBJECT(
   'stat_1_number', 'text', 'stat_1_label', 'text',
   'stat_2_number', 'text', 'stat_2_label', 'text',
   'stat_3_number', 'text', 'stat_3_label', 'text',
   'stat_4_number', 'text', 'stat_4_label', 'text'
 ), 0),

('cta_banner', 'Call to Action Banner',
 JSON_OBJECT(
   'headline', 'text', 'subtext', 'textarea',
   'button_text', 'text', 'button_link', 'text'
 ), 0);


-- ============================================================
-- FROM: section_categories.sql
-- ============================================================
-- ============================================================
-- Adds a category to section_types so the "Add Section" picker
-- can group them instead of showing one long flat list.
-- ============================================================

ALTER TABLE section_types ADD COLUMN category VARCHAR(40) NULL AFTER label;

UPDATE section_types SET category = 'Core Pages' WHERE key_name IN ('hero','about','academics','admissions','gallery','contact','blog');
UPDATE section_types SET category = 'Records & Applications' WHERE key_name IN ('enrollment_form','results_lookup','fees');
UPDATE section_types SET category = 'Community & Extras' WHERE key_name IN ('staff','testimonials','faq','stats','cta_banner');


-- ============================================================
-- FROM: hero_cta_and_colors.sql
-- ============================================================
-- ============================================================
-- Adds a CTA button to hero sections, plus primary/background color
-- overrides so schools aren't limited to the preset theme palettes.
-- ============================================================

UPDATE section_types
SET schema_json = JSON_SET(schema_json, '$.cta_text', 'text', '$.cta_link', 'text')
WHERE key_name = 'hero';

ALTER TABLE schools ADD COLUMN primary_override VARCHAR(7) NULL AFTER accent_override;
ALTER TABLE schools ADD COLUMN bg_override VARCHAR(7) NULL AFTER primary_override;


-- ============================================================
-- FROM: verification.sql
-- ============================================================
-- ============================================================
-- School verification: tracks whether a school has been confirmed
-- as real, and stores their signed/stamped agreement document.
-- ============================================================

ALTER TABLE schools ADD COLUMN verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending' AFTER status;
ALTER TABLE schools ADD COLUMN signed_agreement_path VARCHAR(255) NULL AFTER verification_status;
ALTER TABLE schools ADD COLUMN verification_notes TEXT NULL AFTER signed_agreement_path;

-- Consent tracking for homepage leads too
ALTER TABLE leads ADD COLUMN agreed_to_terms BOOLEAN NOT NULL DEFAULT 0 AFTER message;

