-- Run this after the original blog_posts.sql (adds columns, doesn't touch existing rows/data)

ALTER TABLE blog_posts
  ADD COLUMN cta_text VARCHAR(100) NULL AFTER cover_image,
  ADD COLUMN cta_link VARCHAR(500) NULL AFTER cta_text,
  ADD COLUMN meta_description VARCHAR(160) NULL AFTER excerpt;

CREATE TABLE blog_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  comment TEXT NOT NULL,
  status ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

CREATE INDEX idx_comments_post ON blog_comments (post_id, status);
