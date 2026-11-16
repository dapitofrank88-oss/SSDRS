USE ssdrs_db;
-- Run ONCE in phpMyAdmin > SQL. Every existing user (and every new account) must change their password at next login.
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 1 AFTER locked_until;
