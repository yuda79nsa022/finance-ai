-- One-time cleanup for accounts created before emails were normalized.
-- Trims stray whitespace and lowercases every stored email so login
-- (which now normalizes the typed-in email the same way) can match it.
-- Run this once in phpMyAdmin (or any MySQL client) against your existing
-- database, then affected accounts (e.g. sarah.almahdi@gmail.com if it
-- was saved with a trailing space or different casing) will be able to
-- log in immediately with no other changes.

USE finance_tracker;

UPDATE users
SET email = LOWER(TRIM(email));
