CREATE DATABASE IF NOT EXISTS ssdrs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ssdrs_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS cases;
DROP TABLE IF EXISTS violations;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Student','Admin') NOT NULL DEFAULT 'Student',
  student_id INT NULL,
  full_name VARCHAR(120) NOT NULL,
  date_of_birth DATE NULL,
  email VARCHAR(120) NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 ) ENGINE=InnoDB;

CREATE TABLE account_recovery_codes (
  code_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recovery_user (user_id),
  CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  student_number VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  course VARCHAR(80) NOT NULL DEFAULT 'BSIT',
  year_level VARCHAR(20) NOT NULL DEFAULT '1st Year',
  section VARCHAR(20) NOT NULL DEFAULT 'A',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE violations (
  violation_id INT AUTO_INCREMENT PRIMARY KEY,
  violation_name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  default_sanction VARCHAR(160) NULL
) ENGINE=InnoDB;

CREATE TABLE cases (
  case_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  violation_id INT NOT NULL,
  reported_by INT NULL,
  incident_date DATE NOT NULL,
  remarks TEXT NULL,
  sanction VARCHAR(160) NOT NULL,
  status ENUM('Pending','Under Review','Resolved','Dismissed') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cases_student FOREIGN KEY (student_id) REFERENCES students(student_id),
  CONSTRAINT fk_cases_violation FOREIGN KEY (violation_id) REFERENCES violations(violation_id),
  CONSTRAINT fk_cases_reporter FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reports (
  report_id INT AUTO_INCREMENT PRIMARY KEY,
  generated_by INT NOT NULL,
  report_type VARCHAR(80) NOT NULL,
  generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_user FOREIGN KEY (generated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(120) NOT NULL,
  module VARCHAR(80) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO students (student_id, student_number, first_name, last_name, course, year_level, section) VALUES
(1, 'IT-2024-001', 'Juan', 'Dela Cruz', 'BSIT', '3rd Year', 'B'),
(2, 'IT-2024-002', 'Maria', 'Santos', 'BSIT', '2nd Year', 'A');

INSERT INTO violations (violation_id, violation_name, description, default_sanction) VALUES
(1, 'Late in Class', 'Repeated tardiness to a scheduled class.', 'Written Warning'),
(2, 'Improper Uniform', 'Failure to follow the prescribed school uniform policy.', 'Counseling'),
(3, 'Unauthorized Absence', 'Absence without an accepted reason or documentation.', 'Advising');

-- Passwords are initialized by the temporary setup_passwords.php helper after import.
INSERT INTO users (user_id, username, password_hash, role, student_id, full_name, date_of_birth, email, status) VALUES
(1, 'admin', 'temporary', 'Admin', NULL, 'OSA Administrator', '2000-01-01', 'admin@example.local', 'Active'),
(2, 'student1', 'temporary', 'Student', 1, 'Juan Dela Cruz', '2004-01-01', 'student1@example.local', 'Active'),
(3, 'student2', 'temporary', 'Student', 2, 'Maria Santos', '2005-01-01', 'student2@example.local', 'Active');

INSERT INTO cases (student_id, violation_id, reported_by, incident_date, remarks, sanction, status) VALUES
(1, 1, 1, '2026-08-10', 'Demo record for system testing.', 'Written Warning', 'Resolved'),
(1, 2, 1, '2026-07-15', 'Demo record for system testing.', 'Counseling', 'Resolved'),
(2, 3, 1, '2026-09-02', 'Demo record for system testing.', 'Advising', 'Pending');

-- Change the demo birthdays before use. They are placeholders for classroom testing only.
-- Lockout: 3 failed passwords trigger a 5-minute cooldown; after it expires, birthday verification is required to unlock.
