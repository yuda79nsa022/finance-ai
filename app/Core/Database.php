<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Thin PDO singleton. No ORM — the spec calls for faithful, transparent
 * SQL that mirrors the workbook's SUMIF / SUBTOTAL / SUM formulas, so
 * hiding that behind an ORM would obscure the very logic we must preserve.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );

            try {
                self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die('Database connection failed: ' . htmlspecialchars($e->getMessage()) .
                    '<br>Check config/database.php and confirm MySQL is running in XAMPP.');
            }
        }

        return self::$instance;
    }
}
