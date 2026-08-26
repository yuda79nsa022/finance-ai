<?php

namespace App\Models;

use App\Core\Database;

/**
 * "5. LOAN TRACKER — Bu Easa & Ali" (H21:K25).
 *
 * Reproduces exactly:
 *   Opening Balance (I) : first month this lender is ever tracked = lenders.initial_balance;
 *                          every later month = previous month's Remaining Balance
 *                          for the same lender (the workbook's ='Aug 2026'!K23 chain).
 *                          This chain is NOT reset at a financial-year boundary — if a loan
 *                          still has a balance left at the last month of one financial year,
 *                          the first month of the next financial year opens with that same
 *                          balance, not with lenders.initial_balance again. A loan only ever
 *                          "restarts" from initial_balance for a lender that has no earlier
 *                          month at all (i.e. this genuinely is their first tracked month).
 *   Paid This Month (J)  : =MIN(<lender's Personal-Loan fixed-cost row this month>, OpeningBalance)
 *   Remaining Balance (K): =MAX(0, OpeningBalance - PaidThisMonth)
 */
class LoanLedgerModel
{
    /**
     * Recompute and persist the loan ledger row for every active lender for
     * this month. Must be called (a) whenever a month is created, and
     * (b) whenever that month's Personal-Loan fixed-cost amount changes,
     * because Paid This Month depends on it. Downstream months are also
     * recalculated since their Opening Balance depends on this one.
     */
    public static function recalculateMonth(int $monthId): void
    {
        $month = MonthModel::find($monthId);
        if (!$month) {
            return;
        }

        $userId = (int) $month['user_id'];

        // Pick up any loan payment someone typed straight into Fixed Costs
        // (any category with type='loan' — not just Personal Loan/Bank Loan)
        // without ever using the Loan Tracker's own "Add Loan" form. Without
        // this, that row's amount just sits in Fixed Costs and never shows
        // up here, because the ledger is keyed off lenders, not fixed costs.
        self::syncLendersFromFixedCosts($monthId, $userId);

        $previous = MonthModel::previous($month);

        foreach (Lender::all(true, $userId) as $lender) {
            $opening = $previous
                ? self::remainingBalance($previous['id'], $lender['id']) ?? (float) $lender['initial_balance']
                : (float) $lender['initial_balance'];

            $installment = FixedCostModel::loanInstallmentAmount($monthId, $lender['name']);
            $paid        = min($installment, $opening);
            $remaining   = max(0, $opening - $paid);

            self::upsert($monthId, $lender['id'], $opening, $paid, $remaining);
        }

        // Cascade: the next month's opening balance depends on this month's
        // remaining balance, so propagate forward one step (chain reaction).
        // This deliberately crosses financial-year boundaries — an unpaid
        // loan balance at the end of one year must open the next year's
        // first month at the same balance, not reset to zero/initial.
        $next = self::nextMonth($month);
        if ($next) {
            self::recalculateMonth($next['id']);
        }
    }

    /**
     * Auto-creates a Lender for any item this month's Fixed Costs list under
     * a loan-type category that doesn't already have one — this is what lets
     * the Loan Tracker "just work" for a loan entered directly into Fixed
     * Costs, matching purely on the category being type='loan' (containing
     * the word "Loan" in spirit — Car Loan, Mortgage, Credit Card, etc. all
     * qualify, not only Personal Loan/Bank Loan). The new lender starts with
     * an opening balance of 0 — there's no way to infer the true amount
     * still owed from a Fixed Costs row, which only records the payment —
     * so the ledger will show 0 until the user opens Edit on that row and
     * fills in the real opening balance. A lender that was previously
     * removed (deactivated) and reappears in Fixed Costs is reactivated
     * rather than duplicated, keeping whatever balance/category it already had.
     */
    private static function syncLendersFromFixedCosts(int $monthId, int $userId): void
    {
        foreach (FixedCostModel::loanItemsForMonth($monthId) as $row) {
            $name = $row['item'];
            if ($name === '' || $name === null) {
                continue;
            }
            $lender = Lender::findByNameAny($name, $userId);
            if (!$lender) {
                $type = stripos($row['category_name'], 'bank') !== false ? 'bank' : 'person';
                Lender::create($userId, $name, 0, $type, 0, (int) $row['category_id']);
            } elseif (!$lender['is_active']) {
                Lender::activate((int) $lender['id'], $userId);
            }
        }
    }

    /** Scoped to $month's own user_id so the cross-year chain never picks up another user's month. */
    private static function nextMonth(array $month): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM months WHERE user_id = ? AND month_date > ? ORDER BY month_date ASC LIMIT 1"
        );
        $stmt->execute([$month['user_id'], $month['month_date']]);
        return $stmt->fetch() ?: null;
    }

    private static function remainingBalance(int $monthId, int $lenderId): ?float
    {
        $stmt = Database::connection()->prepare(
            "SELECT remaining_balance FROM loan_ledger WHERE month_id = ? AND lender_id = ?"
        );
        $stmt->execute([$monthId, $lenderId]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (float) $val;
    }

    private static function upsert(int $monthId, int $lenderId, float $opening, float $paid, float $remaining): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO loan_ledger (month_id, lender_id, opening_balance, paid_this_month, remaining_balance)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE opening_balance=VALUES(opening_balance),
                                     paid_this_month=VALUES(paid_this_month),
                                     remaining_balance=VALUES(remaining_balance)"
        );
        $stmt->execute([$monthId, $lenderId, $opening, $paid, $remaining]);
    }

    /** Full ledger rows for a month, joined with lender names, in display order. */
    public static function forMonth(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ll.*, l.name AS lender_name, l.type AS lender_type, l.initial_balance AS lender_initial_balance,
                    l.category_id AS lender_category_id, lc.name AS lender_category_name
             FROM loan_ledger ll
             JOIN lenders l ON l.id = ll.lender_id
             LEFT JOIN categories lc ON lc.id = l.category_id
             WHERE ll.month_id = ? ORDER BY l.sort_order"
        );
        $stmt->execute([$monthId]);
        return $stmt->fetchAll();
    }

    /**
     * Drops this lender's loan-ledger rows for the given month and every
     * month after it (chronologically, across financial years). Used when a
     * loan is paid off and the user confirms "remove it from the tracker" —
     * history before that point is left untouched, only the forward-looking
     * rows disappear. Combine with Lender::deactivate() so future months
     * (not yet generated) never get a row for this lender either.
     */
    public static function removeLenderFromMonthOnward(int $lenderId, int $userId, string $fromMonthDate): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE ll FROM loan_ledger ll
             JOIN months m ON m.id = ll.month_id
             WHERE ll.lender_id = ? AND m.user_id = ? AND m.month_date >= ?"
        );
        $stmt->execute([$lenderId, $userId, $fromMonthDate]);
    }

    /** H25/I25/J25/K25 "Total Owed" row for a month. */
    public static function totalsForMonth(int $monthId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(opening_balance),0) opening,
                    COALESCE(SUM(paid_this_month),0) paid,
                    COALESCE(SUM(remaining_balance),0) remaining
             FROM loan_ledger WHERE month_id = ?"
        );
        $stmt->execute([$monthId]);
        return $stmt->fetch();
    }
}
