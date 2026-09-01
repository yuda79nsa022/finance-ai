<?php

namespace App\Core;

use PDO;

/**
 * Pure-PHP database export/import — no shelling out to the mysqldump/mysql
 * CLI binaries. The previous Backup/Restore implementation used exec(), which
 * many shared/managed PHP hosts disable outright for security, silently
 * breaking this feature outside a XAMPP-style local install with full shell
 * access. This talks to MySQL only through the same PDO connection the rest
 * of the app already uses.
 *
 * The exported file wraps everything in SET FOREIGN_KEY_CHECKS=0/1 so table
 * order never matters for DROP/CREATE/INSERT — deliberately simpler than
 * mysqldump's dependency-ordering, and safe here because a restore always
 * replaces the whole schema, never a partial one.
 */
class DatabaseBackup
{
    private const INSERT_BATCH_SIZE = 200;

    /** Streams a full logical dump (schema + data, every table) to $handle. */
    public static function export($handle): void
    {
        $pdo = Database::connection();

        fwrite($handle, "-- Personal Finance Tracker database export\n");
        fwrite($handle, "-- Generated " . date('c') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach (self::tables($pdo) as $table) {
            $createSql = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];

            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createSql . ";\n\n");

            self::exportRows($pdo, $table, $handle);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * Runs an exported (or hand-written, compatible) .sql file against the
     * configured database in one multi-statement call — PDO_MYSQL supports
     * this natively via MYSQL_ATTR_MULTI_STATEMENTS, so no fragile splitting
     * on ";\n" is needed (see install.php's own installer for the fragile
     * version of that approach, which this deliberately avoids).
     */
    public static function import(string $filePath): void
    {
        $sql = file_get_contents($filePath);
        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('The uploaded file is empty or unreadable.');
        }

        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']);
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
        $pdo->exec($sql);
    }

    private static function tables(PDO $pdo): array
    {
        return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function exportRows(PDO $pdo, string $table, $handle): void
    {
        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        $columns = null;
        $batch = [];

        $flush = function () use (&$batch, $table, $handle, &$columns) {
            if (!$batch) {
                return;
            }
            $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
            fwrite($handle, "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        };

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns ??= array_keys($row);
            $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) >= self::INSERT_BATCH_SIZE) {
                $flush();
            }
        }
        $flush();

        if ($columns !== null) {
            fwrite($handle, "\n");
        }
    }
}
