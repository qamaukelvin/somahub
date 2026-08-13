CREATE TABLE blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  body_html TEXT NOT NULL,
  excerpt VARCHAR(500),
  cover_image VARCHAR(500),
  post_type ENUM('manual','auto_school_joined','auto_milestone','auto_top10') NOT NULL DEFAULT 'manual',
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  trigger_ref_id INT NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_blog_status ON blog_posts (status, published_at);
