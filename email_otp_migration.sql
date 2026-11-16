USE ssdrs_db;

-- Run this migration once on the existing SSDRS database.
-- Existing date_of_birth is left in place for data safety but is no longer used for recovery.
CREATE TABLE IF NOT EXISTS account_recovery_codes (
  code_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recovery_user (user_id),
  CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Make sure every active account has a valid registered email address before using email OTP.
-- Update email values in phpMyAdmin > ssdrs_db > users > Edit.
