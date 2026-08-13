CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_key VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(150) NOT NULL,
  description VARCHAR(500),
  price DECIMAL(10,2) NOT NULL,
  billing_cycle ENUM('one_time','yearly') NOT NULL DEFAULT 'yearly',
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO products (product_key, label, description, price, billing_cycle) VALUES
  ('paid_plan', 'Paid Plan', 'Online enrollment, results checking, and fee publishing.', 2500.00, 'yearly'),
  ('custom_domain', 'Custom Domain Add-on', 'Use your own domain (e.g. yourschool.ac.ke) instead of the somahub.top subdomain.', 1500.00, 'yearly');

CREATE TABLE orders (
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

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_key VARCHAR(50) NOT NULL,
  label VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE refunds (
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

CREATE INDEX idx_orders_school ON orders (school_id, status);
CREATE INDEX idx_orders_status ON orders (status, created_at);

-- Add this if your schools table doesn't already have a way to track the
-- custom-domain add-on (skip if it already exists under a different name):
ALTER TABLE schools ADD COLUMN custom_domain_enabled TINYINT(1) NOT NULL DEFAULT 0;

-- If you already ran this file before the Equity Paybill option was added,
-- run this ALTER instead of re-creating the table:
-- ALTER TABLE orders MODIFY payment_method ENUM('till','pochi','send_money','equity_paybill') NULL;
