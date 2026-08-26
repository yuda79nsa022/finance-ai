# Personal Finance Tracker

A faithful PHP + MySQL web recreation of `personal_.xlsx`. Every formula,
category, workflow step, and calculation from the workbook has been
reproduced exactly — see `database/schema.sql` for the full mapping from
worksheet cells to database columns, and `app/Core/MonthCalculator.php` /
`app/Core/YearSummaryCalculator.php` for the formula-by-formula logic.

## Quick start

1. Copy this folder into `C:\xampp\htdocs\finance`
2. Start Apache + MySQL in XAMPP
3. Visit `http://localhost/finance/install.php` once, then delete that file
4. Visit `http://localhost/finance/public/`

Full instructions: **[docs/INSTALL.md](docs/INSTALL.md)**

## Documentation

- [docs/INSTALL.md](docs/INSTALL.md) — installation & setup
- [docs/USER_GUIDE.md](docs/USER_GUIDE.md) — how to use the app day to day
- [docs/ADMIN_GUIDE.md](docs/ADMIN_GUIDE.md) — categories, backup/restore, financial years

## Tech stack

PHP 8+, MySQL, HTML5, Bootstrap 5, vanilla JavaScript/AJAX, Chart.js,
PhpSpreadsheet, Dompdf — no frameworks, per the project spec.

## What's included

- Full MySQL schema replicating every worksheet relationship
- Login system: session-based auth, admin vs. user roles, password
  management, every page protected
- Monthly pages: Income, Fixed Costs, Loan Tracker (with automatic
  month-to-month balance carry-forward), Variable Expenses Log, Category
  Summary, and the Remaining-to-Spend calculation — view / edit / duplicate
  / lock / print
- Year Summary dashboard: Key Figures, Savings & Debt, Monthly Breakdown,
  Spending by Category, with Chart.js visualizations
- A conversational, one-question-at-a-time data-entry wizard that branches
  based on previous answers (skips loan questions if you have no loans, etc.)
- Admin panel: categories, payment methods, lenders, financial years, users,
  database backup/restore
- Reports: PDF (monthly & annual), CSV (cash flow), Excel/XLSX (annual summary)
- Installer that seeds the exact sample data from the original workbook and
  creates the first admin account, so you can verify the numbers match on
  first login

## Verified against the source workbook

Every seeded number in this app was checked against `personal_.xlsx`'s
cached formula results after installation — including the loan tracker's
capped final payment in the last month (Ali's loan finishes with a 500 KD
payment instead of the usual 1,000 KD, because only 500 KD was left owed —
exactly matching the workbook's `=MIN(...)` formula behavior).
