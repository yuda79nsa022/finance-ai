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

    public static function add(int $monthId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO expenses (month_id, expense_date, amount, category_id, description, payment_method_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $monthId,
            $data['expense_date'],
            $data['amount'],
            $data['category_id'],
            $data['description'] ?? null,
            $data['payment_method_id'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE expenses SET expense_date=?, amount=?, category_id=?, description=?, payment_method_id=? WHERE id=?"
        );
        $stmt->execute([
            $data['expense_date'],
            $data['amount'],
            $data['category_id'],
            $data['description'] ?? null,
            $data['payment_method_id'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
    }
}
