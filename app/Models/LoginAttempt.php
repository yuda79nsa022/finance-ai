<?php

namespace App\Models;

use App\Core\Database;

/** Backs the login throttle in Core/Auth::attempt() — see schema.sql for the table's own notes. */
class LoginAttempt
{
    /** Records one failed attempt for $email (already normalized by the caller), and prunes anything older than a day so this table never grows unbounded. */
    public static function record(string $email): void
    {
        $db = Database::connection();
        $db->prepare("INSERT INTO login_attempts (email) VALUES (?)")->execute([$email]);
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }

    /** How many failed attempts for $email (already normalized) in the last $windowSeconds. */
    public static function recentFailureCount(string $email, int $windowSeconds): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$email, $windowSeconds]);
        return (int) $stmt->fetchColumn();
    }

    /** Clears an email's attempt history on a successful login, so the counter doesn't carry over into a future legitimate lockout window. */
    public static function clear(string $email): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);
    }
}
