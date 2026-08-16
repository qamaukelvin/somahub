CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reviewable_type ENUM('platform','school') NOT NULL,
  reviewable_id INT NULL,  -- NULL for platform reviews; school_id for school reviews
  reviewer_name VARCHAR(100) NOT NULL,
  reviewer_role VARCHAR(100) NULL,  -- e.g. "Parent", "Head Teacher" — free text, optional
  rating TINYINT NOT NULL,  -- 1 to 5
  comment TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_reviews_lookup ON reviews (reviewable_type, reviewable_id, status);

-- Register "reviews" as a proper section type so schools can add/toggle it
-- from their dashboard exactly like Hero, About, Academics, etc.
-- Adjust section_types columns here if your actual schema differs slightly.
INSERT INTO section_types (key_name, label, schema_json, is_premium)
VALUES ('reviews', 'Reviews', '{"intro_text":""}', 0);
