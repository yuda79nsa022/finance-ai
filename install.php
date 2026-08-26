<?php
/**
 * One-time installer.
 *  1. Creates the database + tables from database/schema.sql
 *     (categories and payment methods are seeded — they're the app's
 *     built-in taxonomy, not personal data — but no months, income,
 *     fixed costs, expenses, or lenders are created. The app starts
 *     completely empty.)
 *  2. Creates the first administrator account.
 *
 * Run this once from a browser: http://localhost/finance/install.php
 * (Re-running is safe — it checks whether data already exists first.)
 */

require __DIR__ . '/vendor/autoload_fallback.php';
require __DIR__ . '/app/Core/helpers.php';

use App\Core\Database;
use App\Models\User;

header('Content-Type: text/plain');

$cfg = require __DIR__ . '/config/database.php';

// --- Step 1: create database & load schema -------------------------------
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}",
        $cfg['username'],
        $cfg['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Cannot connect to MySQL. Check config/database.php and confirm MySQL is running in XAMPP.\n" . $e->getMessage());
}

$checkDb = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($cfg['database']))->fetch();
if ($checkDb) {
    $pdo->exec("USE `{$cfg['database']}`");
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tableCheck) {
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count > 0) {
            echo "Database already installed with $count user account(s). Nothing to do.\n";
            echo "To reinstall from scratch, drop the '{$cfg['database']}' database first.\n";
            exit;
        }
    }
}

echo "Creating database and tables...\n";
$sql = file_get_contents(__DIR__ . '/database/schema.sql');
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
    if ($statement === '') {
        continue;
    }
    try {
        $pdo->exec($statement);
    } catch (PDOException $e) {
        if (!str_contains($e->getMessage(), 'Duplicate entry')) {
            echo "Warning while running statement:\n$statement\n{$e->getMessage()}\n\n";
        }
    }
}
echo "Schema ready. Categories and payment methods are seeded; everything else starts empty.\n";

// --- Step 2: create the initial administrator account ---------------------
echo "\nCreating administrator account...\n";
$adminEmail = 'admin@localhost';
$adminPassword = bin2hex(random_bytes(6)); // 12-character random password, shown once below
User::create('Administrator', $adminEmail, $adminPassword, 'admin');

echo "\n================================================================\n";
echo " ADMIN LOGIN — copy these now, they will not be shown again\n";
echo "   Email:    $adminEmail\n";
echo "   Password: $adminPassword\n";
echo "================================================================\n";

echo "\nDone! Visit the app root (e.g. http://localhost/finance/public/) to log in.\n";
echo "You'll land on an empty dashboard — click 'New Entry' to start the guided\n";
echo "wizard, which will ask what month you'd like to work on and take it from there.\n";
echo "\nIMPORTANT: delete install.php from the server now that setup is complete.\n";
