-- One-time migration: adds the failed-login throttle table (Core/Auth).
-- Purely additive — one new table, does not touch any existing table.
--
-- Run this once in phpMyAdmin (SQL tab) against finance_tracker. If you're
-- installing fresh (running install.php against a brand new database), you
-- do NOT need this file — schema.sql already includes it.

USE finance_tracker;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_email_time (email, attempted_at)
) ENGINE=InnoDB;
