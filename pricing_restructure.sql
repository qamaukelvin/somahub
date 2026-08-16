ALTER TABLE themes ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0;

-- Mark whichever themes you consider "premium" — adjust theme names to match yours.
-- Example (edit before running):
-- UPDATE themes SET is_premium = 1 WHERE name IN ('Modern Slate', 'Editorial Green');

INSERT INTO products (product_key, label, description, price, billing_cycle) VALUES
  ('custom_templates', 'Custom Templates', 'Unlocks every premium theme, not just the free starter set.', 1000.00, 'yearly'),
  ('content_writing', 'Content Writing', 'We write your About, Academics, and Admissions text for you, tailored to your school (not generic filler).', 1500.00, 'one_time'),
  ('google_business_setup', 'Google Business Profile Setup', 'We create and verify your school on Google Business Profile, so you appear on Google Maps and in local search results.', 1200.00, 'one_time');

-- Adds a location picker (lat/lng, shown as an interactive map) to the
-- existing Contact section schema. Adjust the key_name/JSON merge below if
-- your actual contact schema_json differs from address/phone/email/office_hours.
UPDATE section_types
SET schema_json = JSON_SET(schema_json, '$.map_location', 'location')
WHERE key_name = 'contact';

ALTER TABLE schools ADD COLUMN custom_templates_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE order_items ADD COLUMN delivered_at DATETIME NULL;

CREATE TABLE service_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  service_key VARCHAR(50) NOT NULL,
  details_json TEXT NOT NULL,
  status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);
