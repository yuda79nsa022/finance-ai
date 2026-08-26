-- =====================================================================
-- One-time migration: lets the Loan Tracker capture ANY loan category,
-- not just Personal Loan / Bank Loan.
--
-- Safe to run once against an already-installed `finance_tracker`
-- database (e.g. via phpMyAdmin > SQL tab). Existing lenders, months,
-- and loan ledger history are untouched — this only adds a column and
-- a few more built-in categories.
--
-- If you're installing fresh (running install.php against a brand new
-- database), you do NOT need this file — schema.sql already includes
-- everything below.
-- =====================================================================

USE finance_tracker;

-- 1. Add the column that lets each lender point at any loan-type
--    category (Car Loan, Mortgage, Credit Card, Student Loan, or any
--    custom one you add later from Settings > Categories) instead of
--    being locked to a Person/Bank binary.
ALTER TABLE lenders
  ADD COLUMN category_id INT UNSIGNED NULL AFTER type,
  ADD CONSTRAINT fk_lenders_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- 2. Seed a few more common loan categories alongside the existing
--    Personal Loan / Bank Loan. Skipped automatically if you already
--    have a category with the same name (categories.name is UNIQUE).
INSERT IGNORE INTO categories (name, type, sort_order) VALUES
  ('Car Loan',     'loan', 16),
  ('Mortgage',     'loan', 17),
  ('Credit Card',  'loan', 18),
  ('Student Loan', 'loan', 19);
