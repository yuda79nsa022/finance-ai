<?php

namespace App\Models;

use App\Core\Database;

/**
 * "1. INCOME" — Monthly Salary (sheet cell C4) plus any additional income
 * lines collected by the wizard. total() reconciles to C4 in the base case
 * where only the salary row exists.
 */
class IncomeModel
{
    public static function forMonth(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM income_entries WHERE month_id = ? ORDER BY id"
        );
        $stmt->execute([$monthId]);
        return $stmt->fetchAll();
    }

    public static function total(int $monthId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount),0) t FROM income_entries WHERE month_id = ?"
        );
        $stmt->execute([$monthId]);
        return (float) $stmt->fetchColumn();
    }

    public static function add(int $monthId, string $source, float $amount, ?string $notes = null): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO income_entries (month_id, source, amount, notes) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$monthId, $source, $amount, $notes]);
        return (int) Database::connection()->lastInsertId();
    }

    /** Scoped to $monthId so one user can never edit another user's income row by guessing/tampering an id — the caller must have already verified $monthId belongs to the requesting user. */
    public static function update(int $id, int $monthId, string $source, float $amount, ?string $notes = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE income_entries SET source = ?, amount = ?, notes = ? WHERE id = ? AND month_id = ?"
        );
        $stmt->execute([$source, $amount, $notes, $id, $monthId]);
    }

    /** Scoped to $monthId — see update(). */
    public static function find(int $id, int $monthId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM income_entries WHERE id = ? AND month_id = ?");
        $stmt->execute([$id, $monthId]);
        return $stmt->fetch() ?: null;
    }

    /** Scoped to $monthId — see update(). */
    public static function delete(int $id, int $monthId): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM income_entries WHERE id = ? AND month_id = ?");
        $stmt->execute([$id, $monthId]);
    }
}
