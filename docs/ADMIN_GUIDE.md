# Administrator Guide — Personal Finance Tracker

All admin functions live under **Settings** (`/admin`).

## Categories

The 14 categories from the original workbook (Rent, Groceries, Transport,
Utilities, Dining Out, Shopping, Health, Other, Personal Loan, Savings,
Saving Travel, School, Salaries of Staff, Internet and Phone) are seeded
automatically. You can add new categories with a name and type (Expense /
Savings / Loan). Categories can be deactivated but are never hard-deleted,
so historical months that reference them keep displaying correctly.

## Payment Methods

Cash, KNET, Credit Card, and Bank Transfer are seeded to match the
workbook's dropdown list. Add or remove payment methods as needed.

## Lenders

Bu Easa and Ali are seeded with their original opening balances (48,825 KD
and 11,500 KD) from the workbook. Add a new lender with a name — its
opening balance for its first tracked month is captured as "Initial
Balance" and every month after that is computed automatically from the
previous month's remaining balance.

## Financial Years

Create a new financial year (label + start/end month) to auto-generate 12
monthly pages, exactly like adding 12 new sheets to the workbook. The
currently active financial year is what the Dashboard displays.

## Backup & Restore

- **Backup** downloads a full `.sql` dump of the database (via `mysqldump`).
  Store these somewhere safe — they contain your complete financial history.
- **Restore** uploads a `.sql` file and runs it against the database. This
  **overwrites existing data**, so only use it to restore from a known-good
  backup.

Both require the MySQL command-line tools (`mysqldump`, `mysql`) to be on
your system PATH — in XAMPP these live in `C:\xampp\mysql\bin`.

## Users

The app requires login — every page redirects to `/login` until signed in.
Roles are `admin` (full access, including Settings) and `user` (everyday
data entry: dashboard, monthly pages, wizard, reports, but not Settings).

Manage accounts from **Settings → Users**: add a new user with a name,
email, password, and role; deactivate/reactivate existing ones. Anyone can
change their own password from **My Account** in the top navigation.

The initial administrator account is created by the installer, which
prints its email and a randomly generated password once — see
[INSTALL.md](INSTALL.md).

## Database structure

See `database/schema.sql` for the full, commented schema. Every table maps
directly to a section of the original workbook — the comments in that file
cross-reference the exact worksheet cells/tables each column replicates.
