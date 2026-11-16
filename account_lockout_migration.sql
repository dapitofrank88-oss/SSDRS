USE ssdrs_db;
ALTER TABLE users
  ADD COLUMN date_of_birth DATE NULL AFTER full_name,
  ADD COLUMN failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN locked_until DATETIME NULL AFTER failed_attempts;

-- Demo birthdays only. Replace these with the correct birthdays for your test accounts.
UPDATE users SET date_of_birth='2000-01-01' WHERE username='admin';
UPDATE users SET date_of_birth='2004-01-01' WHERE username='student1';
UPDATE users SET date_of_birth='2005-01-01' WHERE username='student2';
