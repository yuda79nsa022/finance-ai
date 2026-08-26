<?php

namespace App\Core;

use App\Models\Category;
use App\Models\MonthModel;

/**
 * Reproduces the "Year Summary" worksheet in full:
 *   - the 12-row month table (B4:J15) + Total/Average row (B16:J16)
 *   - KEY FIGURES — FULL YEAR (A19:B36)
 *   - SPENDING BY CATEGORY — FULL YEAR (D19:F34)
 */
class YearSummaryCalculator
{
    public static function build(int $financialYearId): array
    {
        $months = MonthModel::allForYear($financialYearId);

        $monthRows = [];
        foreach ($months as $month) {
            $s = MonthCalculator::summary($month['id']);
            $monthRows[] = [
                'month_id'             => $month['id'],
                'label'                => $month['label'],
                'salary'               => $s['salary'],
                'fixed_costs'          => $s['fixed_total'],
                'budget_for_variable'  => $s['budget_for_variable'],
                'variable_spent'       => $s['variable_total'],
                'remaining'            => $s['remaining_to_spend'],
                'percent_salary_spent' => $s['percent_salary_spent'],
                'loan_paid'            => $s['loan_paid_total'],
                'loan_balance_end'     => $s['loan_remaining_total'],
                'savings_plus_travel'  => MonthCalculator::savingsPlusTravel($month['id']),
            ];
        }

        $totals = self::totalsRow($monthRows);
        $keyFigures = self::keyFigures($monthRows, $totals, $months);
        $categorySpend = self::categorySpend($months);
        $savingsDebt = self::savingsAndDebt($monthRows, $totals, $months);

        return compact('monthRows', 'totals', 'keyFigures', 'categorySpend', 'savingsDebt');
    }

    /** B16:J16 — Total / Average row. */
    private static function totalsRow(array $monthRows): array
    {
        $sum = fn($k) => array_sum(array_column($monthRows, $k));

        $salary   = $sum('salary');
        $fixed    = $sum('fixed_costs');
        $budget   = $sum('budget_for_variable');
        $variable = $sum('variable_spent');
        $remaining = $sum('remaining');
        $loanPaid = $sum('loan_paid');
        $savings  = $sum('savings_plus_travel');

        $last = end($monthRows);

        return [
            'salary'               => $salary,
            'fixed_costs'          => $fixed,
            'budget_for_variable'  => $budget,
            'variable_spent'       => $variable,
            'remaining'            => $remaining,
            'percent_salary_spent' => $salary != 0.0 ? ($fixed + $variable) / $salary : null,
            'loan_paid'            => $loanPaid,
            'loan_balance_end'     => $last ? $last['loan_balance_end'] : 0.0, // I16 = I15
            'savings_plus_travel'  => $savings,
        ];
    }

    /** A19:B36 KEY FIGURES — FULL YEAR. */
    private static function keyFigures(array $monthRows, array $totals, array $months): array
    {
        $totalSalary   = $totals['salary'];
        $totalFixed    = $totals['fixed_costs'];
        $totalVariable = $totals['variable_spent'];
        $totalSpent    = $totalFixed + $totalVariable;
        $leftOver      = $totalSalary - $totalSpent;
        $count         = max(count($monthRows), 1);
        $avgSpend      = $totalSpent / 12;
        $avgLeftOver   = $count ? array_sum(array_column($monthRows, 'remaining')) / $count : 0.0;

        $spendPerMonth = array_map(fn($r) => $r['fixed_costs'] + $r['variable_spent'], $monthRows);
        $highestIdx = null;
        $lowestIdx  = null;
        if ($spendPerMonth) {
            $highestIdx = array_keys($spendPerMonth, max($spendPerMonth))[0];
            $lowestIdx  = array_keys($spendPerMonth, min($spendPerMonth))[0];
        }

        return [
            'total_salary'          => $totalSalary,
            'total_fixed_costs'     => $totalFixed,
            'total_variable_spent'  => $totalVariable,
            'total_spent'           => $totalSpent,
            'total_left_over'       => $leftOver,
            'average_monthly_spend' => $avgSpend,
            'average_monthly_left_over' => $avgLeftOver,
            'highest_spending_month' => $highestIdx !== null ? $monthRows[$highestIdx]['label'] : null,
            'lowest_spending_month'  => $lowestIdx !== null ? $monthRows[$lowestIdx]['label'] : null,
        ];
    }

    /** A29:B36 SAVINGS & DEBT. */
    private static function savingsAndDebt(array $monthRows, array $totals, array $months): array
    {
        $totalSavings = $totals['savings_plus_travel'];
        $totalSalary  = $totals['salary'];

        $firstMonthLoanOwed = null;
        $lastMonthLoanBalance = 0.0;
        if ($months) {
            $first = MonthCalculator::summary($months[0]['id']);
            $firstMonthLoanOwed = $first['loan_owed_total'];
            $last = MonthCalculator::summary(end($months)['id']);
            $lastMonthLoanBalance = $last['loan_remaining_total'];
        }

        $monthsCarryingDebt = count(array_filter($monthRows, fn($r) => $r['loan_balance_end'] > 0));

        $debtFreeFrom = 'Not within this year';
        if ($lastMonthLoanBalance <= 0) {
            foreach ($monthRows as $r) {
                if ($r['loan_balance_end'] <= 0) {
                    $debtFreeFrom = $r['label'];
                    break;
                }
            }
        }

        return [
            'total_savings_travel'   => $totalSavings,
            'savings_rate'           => $totalSalary != 0.0 ? $totalSavings / $totalSalary : null,
            'loan_owed_at_start'     => $firstMonthLoanOwed,
            'total_loan_repaid'      => $totals['loan_paid'],
            'loan_still_owed'        => $lastMonthLoanBalance,
            'months_carrying_debt'   => $monthsCarryingDebt,
            'debt_free_from'         => $debtFreeFrom,
        ];
    }

    /** D19:F34 SPENDING BY CATEGORY — FULL YEAR: SUM('Aug 2026:Jul 2027'!K<row>) per category. */
    private static function categorySpend(array $months): array
    {
        $categories = Category::all();

        // Pre-total each category once per month, then sum across months —
        // avoids recomputing the whole category summary once per category.
        $runningTotals = array_fill_keys(array_column($categories, 'id'), 0.0);
        foreach ($months as $month) {
            $summary = MonthCalculator::categorySummary($month['id']);
            foreach ($summary['rows'] as $row) {
                $runningTotals[$row['category_id']] += $row['total'];
            }
        }

        $yearTotals = [];
        $grandTotal = 0.0;
        foreach ($categories as $cat) {
            $total = $runningTotals[$cat['id']];
            $yearTotals[] = ['category' => $cat['name'], 'total' => $total];
            $grandTotal += $total;
        }

        foreach ($yearTotals as &$row) {
            $row['percent'] = $grandTotal != 0.0 ? $row['total'] / $grandTotal : 0.0;
        }

        return ['rows' => $yearTotals, 'grand_total' => $grandTotal];
    }
}
