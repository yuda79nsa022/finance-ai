<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Represents one monthly sheet ("Aug 2026", "Sep 2026", ...).
 */
class MonthModel
{
    public static function allForYear(int $financialYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM months WHERE financial_year_id = ? ORDER BY month_date"
        );
        $stmt->execute([$financialYearId]);
        return $stmt->fetchAll();
    }

    /**
     * Raw, ownership-unchecked lookup by id — for internal/trusted use only
     * (e.g. cascading models that already only ever operate on ids sourced
     * from a previously-verified record). Any controller action reached
     * directly from a request MUST use findOwned() instead, or one user
     * could load/edit another user's month by guessing/tampering an id.
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM months WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Same lookup as find(), but only returns the month if it belongs to $userId — use this for every request-facing controller action. */
    public static function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM months WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * The month immediately before this one, chronologically — across financial
     * years, not just within the current one. This is what lets a loan that's
     * still being paid off carry its balance from the last month of one
     * financial year into the first month of the next (see LoanLedgerModel).
     * Scoped to $month's own user_id, so this chronology never crosses
     * between two different users' trackers. Returns null if this is the
     * very first month that exists for that user.
     */
    public static function previous(array $month): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM months WHERE user_id = ? AND month_date < ? ORDER BY month_date DESC LIMIT 1"
        );
        $stmt->execute([$month['user_id'], $month['month_date']]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, int $financialYearId, string $monthDate, string $label, float $salary = 0): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO months (user_id, financial_year_id, month_date, label, monthly_salary) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $financialYearId, $monthDate, $label, $salary]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Find the month row for a given first-of-month date within $userId's
     * own tracker, creating both the month and (if needed) its financial
     * year on the fly. Used by the wizard so a brand-new install can go
     * straight from "what month?" to data entry with no manual Financial
     * Year setup step required. The active financial year's date range
     * grows automatically to cover whatever month is requested.
     */
    public static function findOrCreateForDate(int $userId, string $monthDate): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM months WHERE user_id = ? AND month_date = ?");
        $stmt->execute([$userId, $monthDate]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        $year = FinancialYear::active($userId);
        if (!$year) {
            $end = (new \DateTime($monthDate))->modify('+11 months')->format('Y-m-01');
            $label = (new \DateTime($monthDate))->format('M Y') . ' to ' . (new \DateTime($end))->format('M Y');
            $yearId = FinancialYear::create($userId, $label, $monthDate, $end);
        } else {
            $yearId = $year['id'];
            if ($monthDate < $year['start_month']) {
                Database::connection()->prepare("UPDATE financial_years SET start_month = ? WHERE id = ?")->execute([$monthDate, $yearId]);
            }
            if ($monthDate > $year['end_month']) {
                Database::connection()->prepare("UPDATE financial_years SET end_month = ? WHERE id = ?")->execute([$monthDate, $yearId]);
            }
        }

        $label = (new \DateTime($monthDate))->format('M Y');
        $id = self::create($userId, $yearId, $monthDate, $label);

        // Seed this month's loan ledger right away so the opening balance for
        // any existing lender (including one still being paid off from a
        // previous financial year) shows up before a fixed cost is even added.
        LoanLedgerModel::recalculateMonth($id);

        return self::find($id);
    }

    public static function updateSalary(int $monthId, float $salary): void
    {
        $stmt = Database::connection()->prepare("UPDATE months SET monthly_salary = ? WHERE id = ?");
        $stmt->execute([$salary, $monthId]);
    }

    public static function setLocked(int $monthId, bool $locked): void
    {
        $stmt = Database::connection()->prepare("UPDATE months SET is_locked = ? WHERE id = ?");
        $stmt->execute([$locked ? 1 : 0, $monthId]);
    }

    /**
     * Duplicate a month's Fixed Costs and Income lines into a new target month
     * (Monthly Pages > Duplicate). Variable expenses are intentionally NOT
     * copied — they are a fresh log each month in the workbook.
     */
    public static function duplicateInto(int $sourceMonthId, int $targetMonthId): void
    {
        $db = Database::connection();

        $db->prepare(
            "INSERT INTO income_entries (month_id, source, amount, notes)
             SELECT ?, source, amount, notes FROM income_entries WHERE month_id = ?"
        )->execute([$targetMonthId, $sourceMonthId]);

        $db->prepare(
            "INSERT INTO fixed_costs (month_id, item, category_id, amount, due_day, payment_method_id, notes, sort_order)
             SELECT ?, item, category_id, amount, due_day, payment_method_id, notes, sort_order
             FROM fixed_costs WHERE month_id = ?"
        )->execute([$targetMonthId, $sourceMonthId]);

        $source = self::find($sourceMonthId);
        self::updateSalary($targetMonthId, (float) $source['monthly_salary']);
    }
}
