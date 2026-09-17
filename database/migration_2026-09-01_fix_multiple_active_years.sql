-- One-time data fix: FinancialYear::create() used to leave every new row's
-- is_active at its column default (1) without ever deactivating a user's
-- earlier years, so anyone with 2+ financial years currently has ALL of
-- them marked active in the database — FinancialYear::active() only
-- looked correct because of its ORDER BY id DESC tie-break, and there was
-- no way to switch back to an older year through the UI.
--
-- This keeps each user's most-recently-created year active (matching what
-- the app already displayed as "current") and deactivates the rest, so
-- the data is consistent before the new Admin > Financial Years > "Switch
-- to this year" action is used for the first time.
--
-- Run this once in phpMyAdmin (SQL tab) against finance_tracker. Not
-- needed on a fresh install (a new database has no financial_years rows
-- yet), but harmless to run — it's a no-op if only one year exists (or
-- none) per user.

USE finance_tracker;

UPDATE financial_years fy
JOIN (
  SELECT user_id, MAX(id) AS latest_id
  FROM financial_years
  GROUP BY user_id
) latest ON latest.user_id = fy.user_id
SET fy.is_active = (fy.id = latest.latest_id);
