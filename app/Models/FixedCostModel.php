<?php

namespace App\Models;

use App\Core\Database;

/**
 * "2. FIXED MONTHLY COSTS" — mirrors the FixedCosts Excel Table (A7:F19).
 */
class FixedCostModel
{
    public static function forMonth(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT fc.*, c.name AS category_name, c.type AS category_type, pm.name AS payment_method_name
             FROM fixed_costs fc
             JOIN categories c ON c.id = fc.category_id
             LEFT JOIN payment_methods pm ON pm.id = fc.payment_method_id
             WHERE fc.month_id = ?
             ORDER BY fc.sort_order, fc.id"
        );
        $stmt->execute([$monthId]);
        return $stmt->fetchAll();
    }

    /** =SUBTOTAL(109,FixedCosts[Amount (KD)]) — sum of all fixed cost amounts for the month. */
    public static function totalForMonth(int $monthId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount),0) t FROM fixed_costs WHERE month_id = ?"
        );
        $stmt->execute([$monthId]);
        return (float) $stmt->fetchColumn();
    }

    /** =SUMIF(FixedCosts[Category],$H5,FixedCosts[Amount (KD)]) for every category, in one query. */
    public static function totalsByCategory(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT category_id, COALESCE(SUM(amount),0) total
             FROM fixed_costs WHERE month_id = ? GROUP BY category_id"
        );
        $stmt->execute([$monthId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['category_id']] = (float) $row['total'];
        }
        return $out;
    }

    /** The fixed-cost amount for a specific lender's loan installment row this month (Personal Loan or Bank Loan category — used by the loan ledger). */
    public static function loanInstallmentAmount(int $monthId, string $lenderItemName): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(fc.amount),0)
             FROM fixed_costs fc
             JOIN categories c ON c.id = fc.category_id
             WHERE fc.month_id = ? AND c.type = 'loan' AND fc.item = ?"
        );
        $stmt->execute([$monthId, $lenderItemName]);
        return (float) $stmt->fetchColumn();
    }

    /** The single fixed-cost row (if any) that IS this lender's installment payment for a month — same match rule as loanInstallmentAmount(), but returns the row so it can be edited in place. */
    public static function findLoanRow(int $monthId, string $lenderItemName): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT fc.* FROM fixed_costs fc
             JOIN categories c ON c.id = fc.category_id
             WHERE fc.month_id = ? AND c.type = 'loan' AND fc.item = ?
             LIMIT 1"
        );
        $stmt->execute([$monthId, $lenderItemName]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Every distinct item name that appears this month under any loan-type
     * category (type='loan' — Personal Loan, Bank Loan, Car Loan, Mortgage,
     * Credit Card, Student Loan, or any custom loan category), with which
     * category each one used. Used to auto-detect a loan payment someone
     * typed straight into Fixed Costs without going through the Loan
     * Tracker's own "Add Loan" form — see LoanLedgerModel::recalculateMonth().
     */
    public static function loanItemsForMonth(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT fc.item, fc.category_id, c.name AS category_name
             FROM fixed_costs fc
             JOIN categories c ON c.id = fc.category_id
             WHERE fc.month_id = ? AND c.type = 'loan'"
        );
        $stmt->execute([$monthId]);
        return $stmt->fetchAll();
    }

    /**
     * Renames every loan-type fixed-cost row's item text from the old lender
     * name to the new one, across every month belonging to $userId. Must be
     * called whenever a lender is renamed — the loan ledger matches a
     * month's installment to a lender purely by fc.item === lender.name, so
     * leaving old rows with the old name would silently break "Paid This
     * Month" for every past and future month that lender appears in.
     * Scoped to $userId's own months so renaming one user's lender can
     * never touch a different user's fixed-cost rows that happen to share
     * the same item text (e.g. both users have a lender called "Ali").
     */
    public static function renameLoanItem(string $oldName, string $newName, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE fixed_costs fc
             JOIN categories c ON c.id = fc.category_id
             JOIN months m ON m.id = fc.month_id
             SET fc.item = ?
             WHERE c.type = 'loan' AND fc.item = ? AND m.user_id = ?"
        );
        $stmt->execute([$newName, $oldName, $userId]);
    }

    public static function add(int $monthId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO fixed_costs (month_id, item, category_id, amount, due_day, payment_method_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $monthId,
            $data['item'],
            $data['category_id'],
            $data['amount'],
            $data['due_day'] ?? null,
            $data['payment_method_id'] ?? null,
            $data['notes'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE fixed_costs SET item=?, category_id=?, amount=?, due_day=?, payment_method_id=?, notes=? WHERE id=?"
        );
        $stmt->execute([
            $data['item'],
            $data['category_id'],
            $data['amount'],
            $data['due_day'] ?? null,
            $data['payment_method_id'] ?? null,
            $data['notes'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM fixed_costs WHERE id = ?");
        $stmt->execute([$id]);
    }
}
