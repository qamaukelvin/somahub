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

CREATE INDEX idx_attendance_lookup ON attendance_rows (school_id, admission_no);
