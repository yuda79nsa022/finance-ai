-- =====================================================================
-- Migration: add a `prompt` column to ai_settings
-- Run this once in phpMyAdmin's SQL tab against the finance_tracker
-- database, ONLY if you already created the ai_settings table from an
-- earlier migration (migration_2026-08-26_ai_settings.sql). Fresh
-- installs already get this column from schema.sql.
--
-- Lets Settings > AI Advisor store a custom system prompt for the AI
-- Advisor chat. Leaving it blank in the UI keeps using the app's
-- built-in default prompt (see AiSettings::DEFAULT_PROMPT).
-- =====================================================================

ALTER TABLE ai_settings ADD COLUMN prompt TEXT NULL AFTER model;
