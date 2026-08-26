-- Adds a single app-wide AI Advisor setting (provider + API key + model),
-- configured once by Admin in Settings > AI Advisor instead of editing a
-- config file. Purely additive — one new table, seeded with one empty
-- row — does not touch any existing table.
--
-- Run this once in phpMyAdmin (SQL tab) against finance_tracker. If you
-- already ran migration_2026-08-25_advisor_tables.sql, this is safe to
-- run alongside it — they add different tables.

USE finance_tracker;

CREATE TABLE IF NOT EXISTS ai_settings (
  id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1, -- always exactly one row (id=1) — one setting for the whole app
  provider   ENUM('anthropic','openai','deepseek') NOT NULL DEFAULT 'anthropic',
  api_key    VARCHAR(255) NOT NULL DEFAULT '',
  model      VARCHAR(100) NOT NULL DEFAULT '',        -- blank = use the provider's built-in default model
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO ai_settings (id, provider, api_key, model) VALUES (1, 'anthropic', '', '');
