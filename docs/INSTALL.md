# Installation Guide — Personal Finance Tracker

This app is a full recreation of `personal_.xlsx` as a PHP + MySQL web
application. It is built to run on **XAMPP** (Apache + MySQL + PHP 8+)
with no other server software required.

## 1. Requirements

- XAMPP with PHP 8.0+ and MySQL/MariaDB (https://www.apachefriends.org)
- (Optional, for PDF/Excel report exports) [Composer](https://getcomposer.org/download/)

## 2. Copy the project into XAMPP

1. Copy the whole `finance-tracker` project folder into `C:\xampp\htdocs\`.
   The final path should look like `C:\xampp\htdocs\finance\` (rename the
   folder to `finance`, or update `config/app.php`'s `base_path` to match
   whatever folder name you use).
2. Start Apache and MySQL — either from the **XAMPP Control Panel**, or by
   running `batch\start_servers.bat` (edit the `XAMPP_PATH` line first if
   XAMPP isn't installed at `C:\xampp`).

## 3. Configure the database connection

Open `config/database.php`. The defaults match a fresh XAMPP install
(`root` user, no password, host `127.0.0.1`):

```php
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'database' => 'finance_tracker',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];
```

If your MySQL has a different username/password, edit this file.

## 4. Run the installer

Open your browser to:

```
http://localhost/finance/install.php
```

This will:
- Create the `finance_tracker` database and all tables (`database/schema.sql`)
- Seed the built-in category and payment-method lists (the same ones from
  the original workbook's dropdowns) — everything else starts completely
  empty. No sample months, income, expenses, or loans.
- Create an administrator account and print its email + a random generated
  password **once**, directly in the browser output. Copy it down immediately —
  it is not stored anywhere in plain text and won't be shown again. You can
  change it any time from **My Account** after logging in.

**Delete `install.php` from the server once installation is complete** —
it is a one-time setup script and shouldn't stay publicly accessible.

## 5. Log in and get started

```
http://localhost/finance/public/
```

Sign in with the administrator email/password printed by the installer in
step 4. You'll land on an empty dashboard — click **New Entry** in the top
navigation to start the guided wizard. It will ask what month you'd like
to work on (this creates it automatically — no separate setup step needed),
then walk you through salary, fixed costs, loans, savings, and expenses
one question at a time. Change your password any time from **My Account**.

## 6. (Optional) Enable PDF and Excel report exports

The core app works fully without this step. PDF and Excel exports
additionally require two libraries (Dompdf, PhpSpreadsheet), installed via
Composer:

```
cd C:\xampp\htdocs\finance
composer install
```

Or double-click `batch\install_dependencies.bat`. Until this is run, PDF/Excel
export buttons will show a friendly message instead of a file — everything
else in the app (dashboard, monthly pages, wizard, CSV export, admin) works
regardless.

## 7. Adding more months

You don't need to set anything up in advance — just start the wizard
(**New Entry**) and tell it which month you want, and it creates that
month on the fly. If you'd rather create several months at once (or
manage the underlying financial year directly), **Settings → Financial
Years** is still available for that.

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Database connection failed" | Confirm MySQL is running in the XAMPP Control Panel; re-check `config/database.php` |
| Blank page / 500 error | Check Apache error log at `C:\xampp\apache\logs\error.log` |
| PDF/CSV export shows a library message | Run `composer install` (see step 6) |
| Backup/Restore fails | Ensure `C:\xampp\mysql\bin` is on your Windows PATH (needed for `mysqldump`/`mysql` CLI tools) |
