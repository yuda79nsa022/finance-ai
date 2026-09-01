-- =====================================================================
-- Personal Finance Tracker — Database Schema
-- Faithful relational recreation of "personal_.xlsx"
-- Engine: InnoDB | Charset: utf8mb4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS finance_tracker
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finance_tracker;

-- ---------------------------------------------------------------------
-- users : Administrator Functions > Manage users
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- financial_years : Administrator Functions > Manage financial years
-- Mirrors the workbook: one workbook = one financial year of 12 sheets.
-- Each financial year belongs to exactly one user — every user gets their
-- own completely separate tracker (own years, months, income, fixed
-- costs, expenses and loans), not a single shared one. Admin is a normal
-- user for tracker purposes: their own financial data is scoped by
-- user_id like anyone else's, with no built-in visibility into other
-- users' data.
-- ---------------------------------------------------------------------
CREATE TABLE financial_years (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  label         VARCHAR(50)   NOT NULL,           -- e.g. "Aug 2026 to Jul 2027"
  start_month   DATE          NOT NULL,            -- e.g. 2026-08-01
  end_month     DATE          NOT NULL,            -- e.g. 2027-07-01
  currency_code VARCHAR(5)    NOT NULL DEFAULT 'KD',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_years_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_years_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- categories : the fixed 14-row "Summary by Category" list (H5:H18)
-- Admin-editable (Category management / income / expense / savings editing)
-- type identifies which of the three category groups it belongs to,
-- exactly like the workbook implicitly groups Rent/Utilities/... vs
-- Savings/Saving Travel vs (income has none — income is single-line salary
-- plus "additional income" handled in the income table).
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)  NOT NULL UNIQUE,       -- 'Rent','Groceries','Transport',...
  type        ENUM('expense','savings','loan') NOT NULL DEFAULT 'expense',
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- preserves H5..H18 row order
  is_active   TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- payment_methods : the fixed dropdown list (Cash, KNET, Credit Card, Bank Transfer)
-- ---------------------------------------------------------------------
CREATE TABLE payment_methods (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- months : one row per monthly sheet ("Aug 2026" ... "Jul 2027")
-- Replaces the 12 duplicated worksheets. C4 (Monthly Salary) lives here
-- because it is one hard input per sheet, same as the workbook.
-- ---------------------------------------------------------------------
CREATE TABLE months (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,             -- denormalized from financial_years.user_id — lets the
                                                         -- cross-financial-year "previous month" / "next month"
                                                         -- lookups (loan balance carry-forward) stay scoped to one
                                                         -- user's own chronology instead of drifting across users
  financial_year_id INT UNSIGNED NOT NULL,
  month_date        DATE          NOT NULL,          -- first day of month, e.g. 2026-08-01
  label              VARCHAR(20)   NOT NULL,          -- 'Aug 2026'
  monthly_salary     DECIMAL(12,3) NOT NULL DEFAULT 0,-- sheet cell C4
  is_locked          TINYINT(1)    NOT NULL DEFAULT 0,-- Monthly Pages > Lock
  created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_year_month (financial_year_id, month_date),
  CONSTRAINT fk_months_year FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
  CONSTRAINT fk_months_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_months_user_date (user_id, month_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- income_entries : "1. INCOME" section.
-- Sheet only has Monthly Salary as a hard cell (C4), but the wizard spec
-- explicitly asks "Do you have any additional income?" — so income is
-- modelled as a table (salary row is auto-seeded, additional rows added
-- by the wizard), and monthly_salary total = SUM(income_entries.amount)
-- for that month, which still reconciles to C4 for the base case.
-- ---------------------------------------------------------------------
CREATE TABLE income_entries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  month_id    INT UNSIGNED NOT NULL,
  source      VARCHAR(150)  NOT NULL,           -- 'Monthly Salary','Freelance',...
  amount      DECIMAL(12,3) NOT NULL,
  notes       VARCHAR(255)  NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_income_month FOREIGN KEY (month_id) REFERENCES months(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- fixed_costs : "2. FIXED MONTHLY COSTS" table (FixedCosts Excel Table, A7:F19)
-- Columns match exactly: Item, Category, Amount (KD), Due Day, Payment Method, Notes
-- Includes rows whose Category is 'Personal Loan' (loan installments),
-- 'Savings' / 'Saving Travel' (savings contributions), and 'Salaries of
-- Staff' (household staff salaries) — exactly as the workbook mixes them
-- into one FixedCosts table.
-- ---------------------------------------------------------------------
CREATE TABLE fixed_costs (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  month_id          INT UNSIGNED NOT NULL,
  item              VARCHAR(150)  NOT NULL,          -- Column A
  category_id       INT UNSIGNED NOT NULL,           -- Column B
  amount            DECIMAL(12,3) NOT NULL DEFAULT 0, -- Column C 'Amount (KD)'
  due_day           TINYINT UNSIGNED NULL,            -- Column D (1-31)
  payment_method_id INT UNSIGNED NULL,                -- Column E
  notes             VARCHAR(255)  NULL,                -- Column F
  sort_order        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fixed_month    FOREIGN KEY (month_id) REFERENCES months(id) ON DELETE CASCADE,
  CONSTRAINT fk_fixed_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_fixed_payment  FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- expenses : "4. VARIABLE EXPENSES LOG" table (Expenses Excel Table, A29:F41)
-- Columns: Date, Month (computed), Amount (KD), Category, Description, Payment Method
-- ---------------------------------------------------------------------
CREATE TABLE expenses (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  month_id          INT UNSIGNED NOT NULL,
  expense_date      DATE          NOT NULL,           -- Column A 'Date'
  amount            DECIMAL(12,3) NOT NULL,            -- Column C 'Amount (KD)'
  category_id       INT UNSIGNED NOT NULL,             -- Column D
  description       VARCHAR(255)  NULL,                 -- Column E
  payment_method_id INT UNSIGNED NULL,                  -- Column F
  receipt_path      VARCHAR(255)  NULL,                 -- relative path under storage/receipts/, e.g. "3/ab12....jpg" — set when this
                                                          -- expense was added from a scanned receipt photo (see ReceiptScanner/MonthController::scanReceipt)
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_exp_month    FOREIGN KEY (month_id) REFERENCES months(id) ON DELETE CASCADE,
  CONSTRAINT fk_exp_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_exp_payment  FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
  -- 'Month' column (B) is NOT stored: it is derived as DATE_FORMAT(expense_date,'%b %Y'),
  -- exactly mirroring the sheet's calculated column formula
  -- =TEXT([Date],"mmm yyyy").
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- lenders : "5. LOAN TRACKER" row source (H23:H24 = 'Bu easa','Ali').
-- Admin-editable so new lenders can be added without changing code.
-- ---------------------------------------------------------------------
CREATE TABLE lenders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,              -- each user has their own lender list — two users can each
                                                        -- have their own "Ali" without colliding
  name            VARCHAR(100)  NOT NULL,             -- 'Bu easa','Ali', or a bank name — unique per user, not globally
  type            ENUM('person','bank') NOT NULL DEFAULT 'person', -- who you owe, purely descriptive (badge)
  category_id     INT UNSIGNED NULL,                  -- which loan-type category this lender's payments fall under
                                                        -- (Personal Loan, Bank Loan, Car Loan, Mortgage, Credit Card, ...
                                                        -- any category with type='loan' — not limited to the two built-ins)
  initial_balance DECIMAL(12,3) NOT NULL DEFAULT 0,   -- opening balance (I23/I24) for this lender's very first tracked month
  sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_lenders_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_lenders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_lenders_user_name (user_id, name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- loan_ledger : one row per lender per month (I23:K24 block).
-- opening_balance for month N = remaining_balance of month N-1 for the
-- SAME lender (this is exactly what the workbook does: I23 on Sep 2026
-- = ='Aug 2026'!K23). The very first month's opening_balance is a hard
-- input (48825 / 11500 in the sample file) captured on financial_years
-- setup / lender creation.
-- paid_this_month = MIN(fixed_costs installment amount for that lender's
-- Personal-Loan row, opening_balance)  — mirrors =MIN(C14,I23).
-- remaining_balance = MAX(0, opening_balance - paid_this_month) — mirrors
-- =MAX(0,I23-J23).
-- ---------------------------------------------------------------------
CREATE TABLE loan_ledger (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  month_id          INT UNSIGNED NOT NULL,
  lender_id         INT UNSIGNED NOT NULL,
  opening_balance   DECIMAL(12,3) NOT NULL DEFAULT 0,  -- I23/I24
  paid_this_month   DECIMAL(12,3) NOT NULL DEFAULT 0,  -- J23/J24
  remaining_balance DECIMAL(12,3) NOT NULL DEFAULT 0,  -- K23/K24
  UNIQUE KEY uq_month_lender (month_id, lender_id),
  CONSTRAINT fk_loan_month  FOREIGN KEY (month_id) REFERENCES months(id) ON DELETE CASCADE,
  CONSTRAINT fk_loan_lender FOREIGN KEY (lender_id) REFERENCES lenders(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- advisor_conversations / advisor_messages : the "AI Advisor" chat.
-- Each user has their own separate conversation threads, same as every
-- other user-owned table in this schema. The computed insight cards
-- (InsightsEngine) don't need any table — they're recalculated on every
-- page load straight from the existing financial data.
-- ---------------------------------------------------------------------
CREATE TABLE advisor_conversations (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL DEFAULT 'New conversation',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_advisor_conv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_advisor_conv_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE advisor_messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  role            ENUM('user','assistant') NOT NULL,
  content         TEXT NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_advisor_msg_conv FOREIGN KEY (conversation_id) REFERENCES advisor_conversations(id) ON DELETE CASCADE,
  INDEX idx_advisor_msg_conv (conversation_id, id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ai_settings : Settings > AI Advisor. One app-wide row (id always 1) —
-- Admin picks a provider (Claude/Anthropic, ChatGPT/OpenAI, or DeepSeek),
-- pastes that provider's API key, and optionally overrides the default
-- model. Used by AIClient for every user's Advisor chat.
-- ---------------------------------------------------------------------
CREATE TABLE ai_settings (
  id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  provider   ENUM('anthropic','openai','deepseek') NOT NULL DEFAULT 'anthropic',
  api_key    VARCHAR(255) NOT NULL DEFAULT '',
  model      VARCHAR(100) NOT NULL DEFAULT '',
  prompt     TEXT NULL,                            -- custom system prompt template; NULL/blank falls back to AiSettings::DEFAULT_PROMPT
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- login_attempts : failed-login throttle (Core/Auth::attempt()).
-- One row per failed attempt, keyed by the normalized email that was
-- typed in — not tied to a user id, since the account may not even
-- exist (a typo'd or guessed email still gets throttled). Auth counts
-- rows within the last N minutes to decide whether to lock that email
-- out; Auth::attempt() prunes rows older than a day on every failure so
-- this never grows unbounded on a personal-scale install.
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_email_time (email, attempted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Seed data: categories. The first 14 preserve the exact order from the
-- original workbook's H5:H18. Everything from 'Bank Loan' (15) onward is
-- an addition so the Loan Tracker isn't limited to just Personal/Bank
-- loans — any category with type='loan' works as a loan type; the loan
-- ledger matches purely on category type + item name, not a hardcoded
-- category name. Admins can add still more from Settings > Categories.
-- ---------------------------------------------------------------------
INSERT INTO categories (name, type, sort_order) VALUES
  ('Rent',              'expense', 1),
  ('Groceries',         'expense', 2),
  ('Transport',         'expense', 3),
  ('Utilities',         'expense', 4),
  ('Dining Out',        'expense', 5),
  ('Shopping',          'expense', 6),
  ('Health',            'expense', 7),
  ('Other',             'expense', 8),
  ('Personal Loan',     'loan',    9),
  ('Savings',           'savings',10),
  ('Saving Travel',     'savings',11),
  ('School',            'expense',12),
  ('Salaries of Staff', 'expense',13),
  ('Internet and Phone','expense',14),
  ('Bank Loan',         'loan',   15),
  ('Car Loan',          'loan',   16),
  ('Mortgage',          'loan',   17),
  ('Credit Card',       'loan',   18),
  ('Student Loan',      'loan',   19);

-- ---------------------------------------------------------------------
-- Seed data: payment methods (dropdown formula1 list in the sheet)
-- ---------------------------------------------------------------------
INSERT INTO payment_methods (name) VALUES
  ('Cash'), ('KNET'), ('Credit Card'), ('Bank Transfer');

-- ---------------------------------------------------------------------
-- Seed data: ai_settings — the one row, unconfigured until Admin fills
-- in a provider + API key from Settings > AI Advisor.
-- ---------------------------------------------------------------------
INSERT INTO ai_settings (id, provider, api_key, model) VALUES (1, 'anthropic', '', '');

-- ---------------------------------------------------------------------
-- Seed data: lenders present in the sample workbook's Loan Tracker
-- ---------------------------------------------------------------------
-- Lenders are created on the fly through the wizard's loan questions —
-- no sample lenders are seeded here, so a fresh install starts with a
-- genuinely empty ledger.

-- Note: the initial administrator account is created by install.php using
-- PHP's password_hash() (a real bcrypt hash), not seeded here — a
-- hardcoded hash in a public schema file would be a known/guessable
-- credential for every install.
