-- One-time migration: gives every user their own completely separate
-- tracker (financial years, months, income, fixed costs, expenses, and
-- loans) instead of one shared tracker for everyone. All of your existing
-- data (every financial year, month, and lender you've entered so far —
-- Bu essa, Ali, NBK, etc.) is assigned to the admin account, since that's
-- the only account that has been used so far. Sarah (and any other user
-- you add later) will start with a completely empty tracker of their own
-- and will not see any of this existing data.
--
-- Run this once in phpMyAdmin (SQL tab) against your finance_tracker
-- database, AFTER updating the app files (this migration and the code
-- must go in together — running one without the other will break the
-- app until both are done).

USE finance_tracker;

-- 1. financial_years: add user_id, assign everything that already exists
--    to the admin account, then make it required.
ALTER TABLE financial_years ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
UPDATE financial_years
SET user_id = (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
WHERE user_id IS NULL;
ALTER TABLE financial_years MODIFY user_id INT UNSIGNED NOT NULL;
ALTER TABLE financial_years
  ADD CONSTRAINT fk_years_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  ADD INDEX idx_years_user (user_id);

-- 2. months: add user_id (denormalized from its financial year), backfill
--    from financial_years, then make it required. This keeps the loan
--    carry-forward logic (which walks months chronologically, including
--    across financial-year boundaries) correctly scoped to one user only.
ALTER TABLE months ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
UPDATE months m
  JOIN financial_years fy ON fy.id = m.financial_year_id
SET m.user_id = fy.user_id
WHERE m.user_id IS NULL;
ALTER TABLE months MODIFY user_id INT UNSIGNED NOT NULL;
ALTER TABLE months
  ADD CONSTRAINT fk_months_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  ADD INDEX idx_months_user_date (user_id, month_date);

-- 3. lenders: add user_id, assign existing lenders to the admin account,
--    then replace the old globally-unique name constraint with one that's
--    unique per user (so two different users can each have their own
--    lender named "Ali" without colliding).
ALTER TABLE lenders ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
UPDATE lenders
SET user_id = (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
WHERE user_id IS NULL;
ALTER TABLE lenders MODIFY user_id INT UNSIGNED NOT NULL;
-- Drops the old UNIQUE(name) index. If this line errors with "check that
-- column/key exists", run `SHOW INDEX FROM lenders;` first to find the
-- actual index name (it will be whatever's listed for the `name` column)
-- and substitute it below.
ALTER TABLE lenders DROP INDEX name;
ALTER TABLE lenders
  ADD UNIQUE KEY uq_lenders_user_name (user_id, name),
  ADD CONSTRAINT fk_lenders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
