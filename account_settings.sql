ALTER TABLE users ADD COLUMN avatar_path VARCHAR(500) NULL;

CREATE TABLE account_removal_requests (
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
