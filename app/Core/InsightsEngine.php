<?php

namespace App\Core;

use App\Models\LoanLedgerModel;
use App\Models\MonthModel;

/**
 * Computed, deterministic financial insights — no AI call involved. Every
 * number here comes straight out of MonthCalculator/YearSummaryCalculator
 * (the same math that drives the rest of the app), turned into plain-
 * language flags. This exists specifically so the AI Advisor (see
 * AIClient/AdvisorController) never has to invent numbers: it's
 * handed these pre-computed facts as grounding context and only reasons
 * over them, rather than reasoning "from memory" about the user's finances.
 *
 * Each insight is:
 *   ['severity' => 'critical'|'warning'|'positive'|'info',
 *    'title'    => short label,
 *    'message'  => one or two sentences, plain language]
 */
class InsightsEngine
{
    /** Insights for a single month — shown on the Month page and fed to the Advisor when the conversation is about "this month". */
    public static function forMonth(int $monthId): array
    {
        $summary = MonthCalculator::summary($monthId);
        $insights = [];

        // Spending more than earning this month.
        if ($summary['salary'] > 0 && $summary['percent_salary_spent'] !== null && $summary['percent_salary_spent'] > 1.0) {
            $over = ($summary['percent_salary_spent'] - 1.0) * 100;
            $insights[] = [
                'severity' => 'critical',
                'title'    => 'Spending more than you earned this month',
                'message'  => sprintf(
                    'Fixed costs and variable expenses together are %.0f%% over your salary this month. That means you dipped into savings or debt to cover it.',
                    $over
                ),
            ];
        } elseif ($summary['remaining_to_spend'] < 0) {
            $insights[] = [
                'severity' => 'warning',
                'title'    => 'Over budget for variable spending',
                'message'  => sprintf(
                    'You\'ve spent %s more than your budget for variable expenses this month.',
                    fmt_kd(abs($summary['remaining_to_spend']))
                ),
            ];
        }

        // Debt-to-income ratio this month (loan payments only, not all fixed costs).
        if ($summary['salary'] > 0) {
            $dti = $summary['loan_paid_total'] / $summary['salary'];
            if ($dti > 0.4) {
                $insights[] = [
                    'severity' => 'warning',
                    'title'    => 'High loan-to-income ratio this month',
                    'message'  => sprintf(
                        'Loan payments are %.0f%% of your salary this month. Above ~40%% is generally considered a heavy debt load — lenders typically look for well under that.',
                        $dti * 100
                    ),
                ];
            }
        }

        // Loan payoff pace per lender: remaining balance / this month's payment.
        foreach (LoanLedgerModel::forMonth($monthId) as $row) {
            $remaining = (float) $row['remaining_balance'];
            $paid = (float) $row['paid_this_month'];
            if ($remaining > 0.0005 && $paid > 0.0005) {
                $monthsLeft = (int) ceil($remaining / $paid);
                if ($monthsLeft >= 24) {
                    $insights[] = [
                        'severity' => 'info',
                        'title'    => $row['lender_name'] . ' will take a while at this pace',
                        'message'  => sprintf(
                            'At %s/month, %s (%s owed) is about %d months from being paid off. Increasing the payment would shorten that considerably.',
                            fmt_kd($paid), $row['lender_name'], fmt_kd($remaining), $monthsLeft
                        ),
                    ];
                } elseif ($monthsLeft <= 2) {
                    $insights[] = [
                        'severity' => 'positive',
                        'title'    => $row['lender_name'] . ' is almost paid off',
                        'message'  => sprintf('Only %s left — at this pace it\'ll be fully paid off within %d month%s.', fmt_kd($remaining), $monthsLeft, $monthsLeft === 1 ? '' : 's'),
                    ];
                }
            }
        }

        return $insights;
    }

    /** Insights across the whole financial year — shown on the Dashboard and fed to the Advisor for year-level questions. */
    public static function forYear(int $financialYearId): array
    {
        $data = YearSummaryCalculator::build($financialYearId);
        $insights = [];

        $savingsRate = $data['savingsDebt']['savings_rate'];
        if ($savingsRate !== null) {
            if ($savingsRate < 0.10) {
                $insights[] = [
                    'severity' => 'warning',
                    'title'    => 'Savings rate is low',
                    'message'  => sprintf(
                        'You\'re saving about %.0f%% of your income this year. A common target is 15–20%% — worth looking at where that gap could close.',
                        $savingsRate * 100
                    ),
                ];
            } elseif ($savingsRate >= 0.20) {
                $insights[] = [
                    'severity' => 'positive',
                    'title'    => 'Strong savings rate',
                    'message'  => sprintf('You\'re saving about %.0f%% of your income this year — that\'s a healthy pace.', $savingsRate * 100),
                ];
            }
        }

        if ($data['savingsDebt']['months_carrying_debt'] >= 10) {
            $insights[] = [
                'severity' => 'info',
                'title'    => 'Debt has been present most of the year',
                'message'  => sprintf(
                    '%d of the months tracked this year still had a loan balance outstanding. Still owed as of the latest month: %s.',
                    $data['savingsDebt']['months_carrying_debt'],
                    fmt_kd($data['savingsDebt']['loan_still_owed'])
                ),
            ];
        }

        // Category creep: any category whose year total is a large share of total spend.
        $grand = $data['categorySpend']['grand_total'];
        if ($grand > 0) {
            foreach ($data['categorySpend']['rows'] as $row) {
                if ($row['percent'] >= 0.30 && $row['total'] > 0) {
                    $insights[] = [
                        'severity' => 'info',
                        'title'    => $row['category'] . ' dominates this year\'s spending',
                        'message'  => sprintf('%s makes up %.0f%% of everything spent this year (%s).', $row['category'], $row['percent'] * 100, fmt_kd($row['total'])),
                    ];
                }
            }
        }

        return $insights;
    }

    /**
     * A compact plain-text summary of a user's current financial position,
     * built for feeding into the Advisor's prompt as grounding context —
     * NOT for display. Keeps the AI from having to be handed the full
     * nested arrays and lets it reason over a short, readable brief
     * instead. $monthId may be null if the user has no months yet.
     */
    public static function contextBrief(?int $monthId, ?int $financialYearId): string
    {
        $lines = [];

        if ($monthId) {
            $month = MonthModel::find($monthId);
            $s = MonthCalculator::summary($monthId);
            $lines[] = "Current month ({$month['label']}):";
            $lines[] = sprintf('- Salary/income: %s', fmt_kd($s['salary']));
            $lines[] = sprintf('- Fixed costs: %s', fmt_kd($s['fixed_total']));
            $lines[] = sprintf('- Variable expenses so far: %s', fmt_kd($s['variable_total']));
            $lines[] = sprintf('- Remaining to spend this month: %s', fmt_kd($s['remaining_to_spend']));
            $lines[] = sprintf('- Total loan payments this month: %s', fmt_kd($s['loan_paid_total']));
            $lines[] = sprintf('- Total still owed across all loans: %s', fmt_kd($s['loan_remaining_total']));

            $loanLines = [];
            foreach (LoanLedgerModel::forMonth($monthId) as $row) {
                if ((float) $row['remaining_balance'] > 0.0005) {
                    $loanLines[] = sprintf(
                        '  * %s: %s remaining, paying %s/month',
                        $row['lender_name'], fmt_kd((float) $row['remaining_balance']), fmt_kd((float) $row['paid_this_month'])
                    );
                }
            }
            if ($loanLines) {
                $lines[] = 'Active loans:';
                array_push($lines, ...$loanLines);
            }
        }

        if ($financialYearId) {
            $data = YearSummaryCalculator::build($financialYearId);
            $lines[] = '';
            $lines[] = 'This financial year so far:';
            $lines[] = sprintf('- Total income: %s', fmt_kd($data['keyFigures']['total_salary']));
            $lines[] = sprintf('- Total spent (fixed + variable): %s', fmt_kd($data['keyFigures']['total_spent']));
            $lines[] = sprintf('- Left over: %s', fmt_kd($data['keyFigures']['total_left_over']));
            if ($data['savingsDebt']['savings_rate'] !== null) {
                $lines[] = sprintf('- Savings rate: %.1f%%', $data['savingsDebt']['savings_rate'] * 100);
            }
            $lines[] = sprintf('- Loan balance remaining: %s', fmt_kd($data['savingsDebt']['loan_still_owed']));
        }

        return implode("\n", $lines) ?: 'No financial data has been entered yet.';
    }
}
