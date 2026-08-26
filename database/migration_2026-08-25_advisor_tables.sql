-- Adds the two tables the AI Advisor needs (chat history). Purely
-- additive — does not touch or alter any existing table, so this is safe
-- to run against your existing finance_tracker database; your working
-- "finance" app (the one in htdocs/finance) will simply never reference
-- these two new tables and is unaffected either way.
--
-- Run this once in phpMyAdmin (SQL tab) against finance_tracker.

USE finance_tracker;

CREATE TABLE IF NOT EXISTS advisor_conversations (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL DEFAULT 'New conversation',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_advisor_conv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_advisor_conv_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS advisor_messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  role            ENUM('user','assistant') NOT NULL,
  content         TEXT NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_advisor_msg_conv FOREIGN KEY (conversation_id) REFERENCES advisor_conversations(id) ON DELETE CASCADE,
  INDEX idx_advisor_msg_conv (conversation_id, id)
) ENGINE=InnoDB;
