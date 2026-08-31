CREATE TABLE IF NOT EXISTS archived_schools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_school_id INT NOT NULL,
  school_name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  county VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(255) NULL,
  export_json LONGTEXT NOT NULL,
  archived_by VARCHAR(150) NULL,
  archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_archived_schools_name ON archived_schools (school_name);
