# User Guide — Personal Finance Tracker

## The Monthly Workflow

The app follows the exact same flow as the original spreadsheet:

```
Income → Fixed Commitments → Debt Payments → Savings → Variable Expenses
       → Remaining Cash → Monthly Summary → Annual Dashboard
```

## Two ways to enter data

### 1. Guided Entry (the conversational wizard)

Click **New Entry** in the top navigation any time. If you haven't picked
a month yet, the wizard's first question is which month to work on — just
type something like "August 2026" and it creates that month for you, no
separate setup step needed. From there it asks about salary, additional
income, fixed costs, loans, savings, and variable expenses, one question
at a time, skipping whole sections if you say "No" to them.

A few things make this faster than a strict Q&A:

- **Fixed costs**: when asked how many you have, you can give a number
  ("5") and the wizard counts them down one by one, or say "not sure" and
  it'll keep asking "any other?" until you say no.
- **Loans**: name a lender it hasn't seen before and it'll ask what you
  currently owe them in total, then track their balance automatically
  every month after that — you never re-enter it.
- **Variable expenses**: you don't have to answer separate date/amount/
  category questions — you can just say the whole thing in one line, e.g.
  *"add 15 for groceries"* or *"please add 7.5 for dining out, lunch"*,
  and it's logged immediately. It still understands plain Yes/No too.

### 2. Direct entry on the monthly page

Every monthly page also has plain add/delete forms for Income, Fixed
Costs, and Variable Expenses, if you'd rather type directly into a table.

## The Monthly Page

Open a month from the Dashboard (or via **Monthly Records**) to see:

- **Income** — salary and any additional income lines
- **Fixed Monthly Costs** — rent, utilities, loan installments, savings
  contributions, staff salaries, school fees, etc. — anything that repeats
  every month
- **Loan Tracker** — for each lender, the balance owed at the start of the
  month, what you paid this month, and what's left. Next month's opening
  balance is automatically carried forward from this month's remaining
  balance — you never re-enter it
- **Variable Expenses Log** — day-to-day spending (groceries, dining out,
  shopping, etc.)
- **Summary by Category** — a running total, per category, of fixed +
  variable spending
- **What's Left for This Month** — your budget for variable spending
  (Salary − Fixed Costs), how much of it you've spent, and what remains

### Month actions

- **Duplicate to Next Month** — copies this month's Income and Fixed Costs
  into next month (handy since these rarely change month to month).
  Variable expenses are never copied — each month starts with a clean log.
- **Lock** — freezes a month so entries can't be accidentally added or
  removed once you've finished with it. **Unlock** reverses this.
- **Print** — opens a print-friendly version of the page.
- **PDF / CSV** — downloads a report for that month.

## The Dashboard (Year Summary)

The Dashboard reproduces the workbook's Year Summary sheet:

- **Key Figures** — total salary, total spent, money left over for the
  year, average monthly spend/leftover, and your highest/lowest spending
  months
- **Savings & Debt** — total saved, savings rate, how much loan debt you
  started the year with, how much you've repaid, what's left, and (if
  applicable) the month you become debt-free
- **Monthly Breakdown table** — every month side by side
- **Spending by Category** — a full-year total and percentage share for
  every category
- Charts for Salary vs. Spend by month, and Spending by Category

## Reports

From the Dashboard or a monthly page you can export:
- **Monthly Report (PDF)** and **Annual Report (PDF)**
- **Cash Flow (CSV)** — every income, fixed cost, and expense line for a month
- **Annual Summary (Excel/XLSX)**
