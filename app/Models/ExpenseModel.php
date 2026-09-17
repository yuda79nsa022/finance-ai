<?php

namespace App\Models;

use App\Core\Database;

/**
 * "4. VARIABLE EXPENSES LOG" — mirrors the Expenses Excel Table (A29:F41).
 * The sheet's "Month" column (=TEXT([Date],"mmm yyyy")) is derived here via
 * DATE_FORMAT rather than stored, since it is a pure function of expense_date.
 */
class ExpenseModel
{
    public static function forMonth(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT e.*, DATE_FORMAT(e.expense_date, '%b %Y') AS month_label,
                    c.name AS category_name, pm.name AS payment_method_name
             FROM expenses e
             JOIN categories c ON c.id = e.category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             WHERE e.month_id = ?
             ORDER BY e.expense_date, e.id"
        );
        $stmt->execute([$monthId]);
        return $stmt->fetchAll();
    }

    /** =SUM(Expenses[Amount (KD)]) for the month. */
    public static function totalForMonth(int $monthId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE month_id = ?"
        );
        $stmt->execute([$monthId]);
        return (float) $stmt->fetchColumn();
    }

    /** =SUMIF(Expenses[Category],$H5,Expenses[Amount (KD)]) for every category, in one query. */
    public static function totalsByCategory(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT category_id, COALESCE(SUM(amount),0) total
             FROM expenses WHERE month_id = ? GROUP BY category_id"
        );
        $stmt->execute([$monthId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['category_id']] = (float) $row['total'];
        }
        return $out;
    }

    /** Scoped to $monthId so one user can never load another user's expense row by guessing/tampering an id — the caller must have already verified $monthId belongs to the requesting user. Used to serve a receipt image only to its owner. */
    public static function find(int $id, int $monthId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM expenses WHERE id = ? AND month_id = ?");
        $stmt->execute([$id, $monthId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Same date + same amount, already logged this month — used by
     * StatementImporter to flag a likely-duplicate row (e.g. one already
     * entered manually, then it also shows up in an imported statement)
     * for the user to review rather than silently re-importing it. A
     * heuristic, not a guarantee: two unrelated purchases on the same day
     * for the same amount would also match — that's exactly why it's
     * surfaced for review instead of auto-skipped.
     */
    public static function findDuplicate(int $monthId, string $expenseDate, float $amount): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM expenses WHERE month_id = ? AND expense_date = ? AND amount = ? LIMIT 1"
        );
        $stmt->execute([$monthId, $expenseDate, $amount]);
        return $stmt->fetch() ?: null;
    }

    public static function add(int $monthId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO expenses (month_id, expense_date, amount, category_id, description, payment_method_id, receipt_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $monthId,
            $data['expense_date'],
            $data['amount'],
            $data['category_id'],
            $data['description'] ?? null,
            $data['payment_method_id'] ?? null,
            $data['receipt_path'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** Scoped to $monthId — see find(). Does not touch receipt_path (only set at add() time via the scan flow). */
    public static function update(int $id, array $data, int $monthId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE expenses SET expense_date=?, amount=?, category_id=?, description=?, payment_method_id=? WHERE id=? AND month_id=?"
        );
        $stmt->execute([
            $data['expense_date'],
            $data['amount'],
            $data['category_id'],
            $data['description'] ?? null,
            $data['payment_method_id'] ?? null,
            $id,
            $monthId,
        ]);
    }

    /** Scoped to $monthId — see find(). */
    public static function delete(int $id, int $monthId): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM expenses WHERE id = ? AND month_id = ?");
        $stmt->execute([$id, $monthId]);
    }
}
