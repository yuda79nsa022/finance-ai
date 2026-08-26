<?php

namespace App\Core;

use App\Models\Category;
use App\Models\ExpenseModel;
use App\Models\FixedCostModel;
use App\Models\IncomeModel;
use App\Models\LoanLedgerModel;
use App\Models\MonthModel;

/**
 * Reproduces every formula on a monthly sheet:
 *   C4  Monthly Salary                = SUM(income_entries)
 *   C19 Total Fixed / Month           = SUBTOTAL(109, FixedCosts[Amount])
 *   C20 Total Fixed / Year            = C19 * 12
 *   C23 Budget for Variable Expenses  = C4 - C19
 *   C24 Less: Variable Expenses Logged= -SUM(Expenses[Amount])
 *   C25 Remaining To Spend            = C23 + C24
 *   C26 % of Salary Spent             = (C19 - C24) / C4   [blank if C4 = 0]
 *   H4:K19 Summary by Category        = SUMIF(FixedCosts), SUMIF(Expenses), sum
 */
class MonthCalculator
{
    public static function summary(int $monthId): array
    {
        $salary   = IncomeModel::total($monthId);
        $fixed    = FixedCostModel::totalForMonth($monthId);
        $variable = ExpenseModel::totalForMonth($monthId);

        $budgetForVariable = $salary - $fixed;                 // C23
        $lessVariable       = -$variable;                       // C24 (negative, matches sheet)
        $remainingToSpend    = $budgetForVariable + $lessVariable; // C25
        $percentSalarySpent  = $salary != 0.0 ? ($fixed + $variable) / $salary : null; // C26

        $loanTotals = LoanLedgerModel::totalsForMonth($monthId);

        return [
            'salary'               => $salary,
            'fixed_total'          => $fixed,
            'fixed_total_year'     => $fixed * 12,             // C20
            'variable_total'       => $variable,
            'budget_for_variable'  => $budgetForVariable,
            'less_variable'        => $lessVariable,
            'remaining_to_spend'   => $remainingToSpend,
            'percent_salary_spent' => $percentSalarySpent,
            'loan_paid_total'      => (float) $loanTotals['paid'],
            'loan_owed_total'      => (float) $loanTotals['opening'],
            'loan_remaining_total' => (float) $loanTotals['remaining'],
        ];
    }

    /** H4:K19 "Summary by Category" table: one row per category with Fixed / Variable / Total. */
    public static function categorySummary(int $monthId): array
    {
        $categories = Category::all();
        $fixedByCat = FixedCostModel::totalsByCategory($monthId);
        $varByCat   = ExpenseModel::totalsByCategory($monthId);

        $rows = [];
        $totals = ['fixed' => 0.0, 'variable' => 0.0, 'total' => 0.0];

        foreach ($categories as $cat) {
            $fixed = $fixedByCat[$cat['id']] ?? 0.0;
            $var   = $varByCat[$cat['id']] ?? 0.0;
            $rows[] = [
                'category_id'   => $cat['id'],
                'category_name' => $cat['name'],
                'fixed'         => $fixed,
                'variable'      => $var,
                'total'         => $fixed + $var,
            ];
            $totals['fixed']    += $fixed;
            $totals['variable'] += $var;
            $totals['total']    += $fixed + $var;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * "Savings + Travel" figure used on the Year Summary (K14 + K15 —
     * the Savings and Saving Travel categories' fixed+variable totals).
     */
    public static function savingsPlusTravel(int $monthId): float
    {
        $summary = self::categorySummary($monthId);
        $total = 0.0;
        foreach ($summary['rows'] as $row) {
            if (in_array($row['category_name'], ['Savings', 'Saving Travel'], true)) {
                $total += $row['total'];
            }
        }
        return $total;
    }
}
