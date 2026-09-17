-- One-time migration: lets a Variable Expense carry a photo of its receipt
-- (Expense Tracker > Variable Expenses Log > "Scan Receipt"). Purely
-- additive — one new nullable column, no existing data touched.
--
-- Run this once in phpMyAdmin (SQL tab) against finance_tracker. If you're
-- installing fresh (running install.php against a brand new database), you
-- do NOT need this file — schema.sql already includes this column.

USE finance_tracker;

ALTER TABLE expenses
  ADD COLUMN receipt_path VARCHAR(255) NULL AFTER payment_method_id;
