<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\MonthCalculator;
use App\Models\Category;
use App\Models\ExpenseModel;
use App\Models\FixedCostModel;
use App\Models\IncomeModel;
use App\Models\Lender;
use App\Models\LoanLedgerModel;
use App\Models\MonthModel;
use App\Models\PaymentMethod;

/**
 * Conversational wizard: asks exactly one question at a time and decides
 * the next question from the answers already given. Can start from
 * absolute zero — no month needs to exist yet, the very first question
 * ("What month would you like to work on?") creates it.
 *
 * Two flexibility features on top of the plain guided path:
 *  - Fixed costs: the user can say how many they have up front and the
 *    wizard counts them down, or say "not sure" and add them one at a
 *    time until they say they're done.
 *  - Variable expenses: at any point in that section the user can just
 *    describe the expense in one line ("add 15 for groceries") instead
 *    of answering separate date/amount/category/description questions.
 */
class WizardController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function start(): void
    {
        $monthIdParam = $this->input('month_id');

        if ($monthIdParam) {
            $month = MonthModel::findOwned((int) $monthIdParam, $this->currentUserId());
            if (!$month) {
                http_response_code(404);
                echo 'Month not found.';
                return;
            }
            $_SESSION['wizard'] = ['month_id' => $month['id'], 'step' => 'salary', 'buffer' => []];
            $firstQuestion = $this->ask("What is your monthly salary (in {$this->currency()})?", 'number');
            $this->view('wizard/index', ['month' => $month, 'firstQuestion' => $firstQuestion]);
            return;
        }

        // No month specified — start completely from scratch.
        $_SESSION['wizard'] = ['month_id' => null, 'step' => 'month_select', 'buffer' => []];
        $firstQuestion = $this->ask("Hi! Let's set up your finances. What month would you like to work on? (e.g. \"August 2026\")", 'text');
        $this->view('wizard/index', ['month' => null, 'firstQuestion' => $firstQuestion]);
    }

    public function answer(): void
    {
        if (empty($_SESSION['wizard'])) {
            $this->json(['error' => 'No active wizard session. Reload the page.'], 400);
        }

        $state = &$_SESSION['wizard'];
        $answer = trim((string) $this->input('answer', ''));

        $result = $this->advance($state, $answer);
        $this->json($result);
    }

    private function advance(array &$state, string $answer): array
    {
        // The very first step doesn't have a month yet.
        if ($state['step'] === 'month_select') {
            $date = $this->parseMonth($answer);
            if (!$date) {
                return $this->ask("Sorry, I couldn't understand that month — try something like \"August 2026\" or \"2026-08\".", 'text');
            }
            $month = MonthModel::findOrCreateForDate($this->currentUserId(), $date);
            $state['month_id'] = $month['id'];
            $state['step'] = 'salary';
            $verb = (float) $month['monthly_salary'] > 0 || IncomeModel::total($month['id']) > 0 ? "Welcome back to" : "Great, let's set up";
            return $this->ask("{$verb} {$month['label']}. What is your monthly salary (in {$this->currency()})?", 'number');
        }

        $monthId = $state['month_id'];

        switch ($state['step']) {

            case 'salary':
                if ($answer !== '') {
                    IncomeModel::add($monthId, 'Monthly Salary', (float) $answer);
                }
                $state['step'] = 'additional_income_yn';
                return $this->ask("Do you have any additional income this month?", 'confirm');

            case 'additional_income_yn':
                if ($this->isYes($answer)) {
                    $state['step'] = 'additional_income_source';
                    return $this->ask("What's the source of this additional income?", 'text');
                }
                $state['step'] = 'fixed_costs_count';
                return $this->ask("How many fixed monthly costs would you like to enter (rent, utilities, etc.)? Enter a number, or say \"not sure\".", 'text');

            case 'additional_income_source':
                $state['buffer']['income_source'] = $answer;
                $state['step'] = 'additional_income_amount';
                return $this->ask("How much is that income (in {$this->currency()})?", 'number');

            case 'additional_income_amount':
                IncomeModel::add($monthId, $state['buffer']['income_source'], (float) $answer);
                $state['buffer'] = [];
                $state['step'] = 'additional_income_yn';
                return $this->ask("Any other additional income to add?", 'confirm');

            // ---- Fixed costs: counted loop or open-ended, user's choice ----
            case 'fixed_costs_count':
                $trimmed = strtolower($answer);
                if ($trimmed === '' || in_array($trimmed, ['no', 'none', '0', 'n'], true)) {
                    $state['step'] = 'loans_yn';
                    return $this->ask("Do you have any loan payments to record this month?", 'confirm');
                }
                if (is_numeric($trimmed) && (int) $trimmed > 0) {
                    $state['fixed_remaining'] = (int) $trimmed;
                    $state['step'] = 'fixed_item';
                    return $this->ask("Let's add them one by one. What's fixed expense #1 called?", 'text');
                }
                // "not sure" or anything else — open-ended loop
                $state['fixed_remaining'] = null;
                $state['step'] = 'fixed_item';
                return $this->ask("No problem, let's add them one at a time. What's the first one called?", 'text');

            case 'fixed_item':
                $state['buffer'] = ['item' => $answer];
                $state['step'] = 'fixed_category';
                return $this->ask("Which category is that under?", 'select', $this->categoryOptions());

            case 'fixed_category':
                $state['buffer']['category_id'] = (int) $answer;
                $state['step'] = 'fixed_amount';
                return $this->ask("How much is it (in {$this->currency()})?", 'number');

            case 'fixed_amount':
                $state['buffer']['amount'] = (float) $answer;
                $state['step'] = 'fixed_due_day';
                return $this->ask("What day of the month is it due? (1-31, or leave blank)", 'number', null, true);

            case 'fixed_due_day':
                $state['buffer']['due_day'] = $answer !== '' ? (int) $answer : null;
                $state['step'] = 'fixed_payment_method';
                return $this->ask("How do you usually pay this?", 'select', $this->paymentMethodOptions(), true);

            case 'fixed_payment_method':
                $state['buffer']['payment_method_id'] = $answer !== '' ? (int) $answer : null;
                FixedCostModel::add($monthId, $state['buffer']);
                LoanLedgerModel::recalculateMonth($monthId);
                $state['buffer'] = [];
                return $this->afterFixedCost($state);

            case 'fixed_costs_more_yn':
                if ($this->isYes($answer)) {
                    $state['step'] = 'fixed_item';
                    return $this->ask("What's the next fixed expense called?", 'text');
                }
                $state['step'] = 'loans_yn';
                return $this->ask("Do you have any loan payments to record this month?", 'confirm');

            // ---- Loans (creates the lender on the fly if new) ----
            case 'loans_yn':
                if ($this->isYes($answer)) {
                    $state['step'] = 'loan_lender';
                    return $this->ask("Who is the loan payment to (lender name)?", 'text');
                }
                $state['step'] = 'savings_yn';
                return $this->ask("Would you like to log any savings contributions this month?", 'confirm');

            case 'loan_lender':
                $lenderName = $answer;
                $lender = Lender::findByName($lenderName, $this->currentUserId());
                $state['buffer'] = ['item' => $lenderName];
                if (!$lender) {
                    $state['buffer']['is_new_lender'] = true;
                    $state['step'] = 'loan_lender_type';
                    return $this->ask("Is {$lenderName} a bank or a person?", 'select', [
                        ['id' => 'bank', 'name' => 'Bank'],
                        ['id' => 'person', 'name' => 'Person'],
                    ]);
                }
                $state['buffer']['category_id'] = $this->categoryIdForLenderType($lender['type']);
                $state['step'] = 'loan_amount';
                return $this->ask("How much are you paying {$lenderName} this month?", 'number');

            case 'loan_lender_type':
                $type = $answer === 'bank' ? 'bank' : 'person';
                $state['buffer']['lender_type'] = $type;
                $state['buffer']['category_id'] = $this->categoryIdForLenderType($type);
                $state['step'] = 'loan_total_owed';
                return $this->ask("Got it. What's the total amount you currently owe them?", 'number');

            case 'loan_total_owed':
                $state['buffer']['initial_balance'] = (float) $answer;
                $state['step'] = 'loan_amount';
                return $this->ask("And how much are you paying them this month?", 'number');

            case 'loan_amount':
                $state['buffer']['amount'] = (float) $answer;
                if (!empty($state['buffer']['is_new_lender'])) {
                    Lender::create($this->currentUserId(), $state['buffer']['item'], $state['buffer']['initial_balance'] ?? 0, $state['buffer']['lender_type'] ?? 'person');
                }
                unset($state['buffer']['is_new_lender'], $state['buffer']['initial_balance'], $state['buffer']['lender_type']);
                FixedCostModel::add($monthId, $state['buffer']);
                LoanLedgerModel::recalculateMonth($monthId);
                $state['buffer'] = [];
                $state['step'] = 'loans_yn';
                return $this->ask("Any other loan payment to add?", 'confirm');

            // ---- Savings ----
            case 'savings_yn':
                if ($this->isYes($answer)) {
                    $state['step'] = 'savings_category';
                    return $this->ask("Is this regular Savings or Saving Travel?", 'select', [
                        ['id' => $this->categoryIdByName('Savings'), 'name' => 'Savings'],
                        ['id' => $this->categoryIdByName('Saving Travel'), 'name' => 'Saving Travel'],
                    ]);
                }
                $state['step'] = 'variable_yn';
                return $this->ask("Would you like to log any variable expenses? You can say something like \"add 15 for groceries\", or Yes/No.", 'confirm', null, false, true);

            case 'savings_category':
                $state['buffer'] = ['item' => 'Savings contribution', 'category_id' => (int) $answer];
                $state['step'] = 'savings_amount';
                return $this->ask("How much are you putting into savings this month?", 'number');

            case 'savings_amount':
                $state['buffer']['amount'] = (float) $answer;
                FixedCostModel::add($monthId, $state['buffer']);
                $state['buffer'] = [];
                $state['step'] = 'savings_yn';
                return $this->ask("Any other savings contribution to add?", 'confirm');

            // ---- Variable expenses: natural-language shortcut or guided ----
            case 'variable_yn':
                if ($this->isNo($answer)) {
                    $state['step'] = 'done';
                    return $this->finish($monthId);
                }
                if ($this->isYes($answer)) {
                    $state['step'] = 'variable_date';
                    return $this->ask("What date was the expense? (YYYY-MM-DD, or leave blank for today)", 'date', null, true);
                }
                // Not a plain yes/no — try to parse it as a direct expense statement.
                $parsed = $this->parseExpenseFreeText($answer);
                if ($parsed) {
                    ExpenseModel::add($monthId, $parsed);
                    $catName = $this->categoryNameById($parsed['category_id']);
                    return $this->ask(
                        "Added " . number_format($parsed['amount'], 3) . " {$this->currency()} under {$catName}. Any other expense to log?",
                        'confirm', null, false, true
                    );
                }
                return $this->ask("I didn't quite catch that — you can say \"add 15 for groceries\", or answer Yes/No.", 'confirm', null, false, true);

            case 'variable_date':
                $state['buffer'] = ['expense_date' => $answer !== '' ? $answer : date('Y-m-d')];
                $state['step'] = 'variable_amount';
                return $this->ask("How much did you spend?", 'number');

            case 'variable_amount':
                $state['buffer']['amount'] = (float) $answer;
                $state['step'] = 'variable_category';
                return $this->ask("Which category was it?", 'select', $this->categoryOptions());

            case 'variable_category':
                $state['buffer']['category_id'] = (int) $answer;
                $state['step'] = 'variable_description';
                return $this->ask("Add a short description (optional)", 'text', null, true);

            case 'variable_description':
                $state['buffer']['description'] = $answer ?: null;
                $state['step'] = 'variable_payment_method';
                return $this->ask("Payment method?", 'select', $this->paymentMethodOptions(), true);

            case 'variable_payment_method':
                $state['buffer']['payment_method_id'] = $answer !== '' ? (int) $answer : null;
                ExpenseModel::add($monthId, $state['buffer']);
                $state['buffer'] = [];
                $state['step'] = 'variable_yn';
                return $this->ask("Any other expense to log?", 'confirm', null, false, true);

            default:
                return $this->finish($monthId);
        }
    }

    /** After saving a fixed cost, either continue the counted loop or fall back to the open-ended one. */
    private function afterFixedCost(array &$state): array
    {
        if (array_key_exists('fixed_remaining', $state) && $state['fixed_remaining'] !== null) {
            $state['fixed_remaining']--;
            if ($state['fixed_remaining'] <= 0) {
                $state['step'] = 'loans_yn';
                return $this->ask("That's all your fixed costs. Do you have any loan payments to record this month?", 'confirm');
            }
            $left = $state['fixed_remaining'];
            $state['step'] = 'fixed_item';
            return $this->ask("What's the next fixed expense called? ({$left} more after this)", 'text');
        }

        $state['step'] = 'fixed_costs_more_yn';
        return $this->ask("Any other fixed cost to add?", 'confirm');
    }

    private function finish(int $monthId): array
    {
        $summary = MonthCalculator::summary($monthId);
        return [
            'done'    => true,
            'message' => "All set! Here's where you stand this month:",
            'summary' => [
                'salary'             => fmt_kd($summary['salary']),
                'fixed_total'        => fmt_kd($summary['fixed_total']),
                'variable_total'     => fmt_kd($summary['variable_total']),
                'remaining_to_spend' => fmt_kd($summary['remaining_to_spend']),
            ],
            'redirect' => base_url('/month/' . $monthId),
        ];
    }

    private function ask(string $question, string $type, ?array $options = null, bool $optional = false, bool $quickAdd = false): array
    {
        return [
            'done'      => false,
            'question'  => $question,
            'type'      => $type,
            'options'   => $options,
            'optional'  => $optional,
            'quick_add' => $quickAdd,
        ];
    }

    private function isYes(string $answer): bool
    {
        return in_array(strtolower(trim($answer)), ['yes', 'y', 'yeah', 'yep', 'true', '1'], true);
    }

    private function isNo(string $answer): bool
    {
        return in_array(strtolower(trim($answer)), ['no', 'n', 'nope', 'false', '0', ''], true);
    }

    /**
     * Attempts to parse a free-text statement like "add 15 for groceries" or
     * "please log 74.99 KD for the internet bill" into an expense. Returns
     * null if no amount can be found (meaning: this wasn't an expense
     * statement, treat it as a normal answer instead).
     */
    private function parseExpenseFreeText(string $text): ?array
    {
        if (!preg_match('/(\d+(?:\.\d+)?)/', $text, $m)) {
            return null;
        }
        $amount = (float) $m[1];
        if ($amount <= 0) {
            return null;
        }

        $categoryId = null;
        $matchedName = null;
        foreach (Category::all() as $cat) {
            if (stripos($text, $cat['name']) !== false) {
                $categoryId = $cat['id'];
                $matchedName = $cat['name'];
                break;
            }
        }
        if ($categoryId === null) {
            $categoryId = $this->categoryIdByName('Other');
        }

        // Build a light description: strip the amount, matched category name,
        // and common filler words, then use what's left (or fall back to the category name).
        $desc = $text;
        $desc = str_replace($m[1], '', $desc);
        if ($matchedName) {
            $desc = str_ireplace($matchedName, '', $desc);
        }
        $filler = ['/\bplease\b/i', '/\badd\b/i', '/\bas a variable cost\b/i', '/\bas variable\b/i', '/\bkd\b/i', '/\bfor\b/i', '/\bthe\b/i', '/^[\s,\-]+|[\s,\-]+$/'];
        $desc = trim(preg_replace($filler, ' ', $desc));
        $desc = trim(preg_replace('/\s+/', ' ', $desc));

        return [
            'expense_date'      => date('Y-m-d'),
            'amount'            => $amount,
            'category_id'       => $categoryId,
            'description'       => $desc !== '' ? $desc : null,
            'payment_method_id' => null,
        ];
    }

    /**
     * Parses a spoken month like "August 2026", "Aug 2026", "2026-08", or
     * "08/2026" into a first-of-month date string. Returns null if nothing
     * recognizable was found.
     */
    private function parseMonth(string $text): ?string
    {
        $text = trim($text);
        foreach (['F Y', 'M Y', 'Y-m', 'm/Y', 'Y-m-d'] as $format) {
            $dt = \DateTime::createFromFormat($format, $text);
            if ($dt !== false) {
                return $dt->format('Y-m-01');
            }
        }
        $timestamp = strtotime($text);
        if ($timestamp !== false) {
            return date('Y-m-01', $timestamp);
        }
        return null;
    }

    private function categoryOptions(): array
    {
        return array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], Category::all());
    }

    private function paymentMethodOptions(): array
    {
        return array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], PaymentMethod::all());
    }

    private function categoryIdForLenderType(string $type): ?int
    {
        return $this->categoryIdByName($type === 'bank' ? 'Bank Loan' : 'Personal Loan');
    }

    private function categoryIdByName(string $name): ?int
    {
        foreach (Category::all() as $c) {
            if ($c['name'] === $name) {
                return (int) $c['id'];
            }
        }
        return null;
    }

    private function categoryNameById(?int $id): string
    {
        foreach (Category::all() as $c) {
            if ((int) $c['id'] === $id) {
                return $c['name'];
            }
        }
        return 'Other';
    }

    private function currency(): string
    {
        return (require dirname(__DIR__, 2) . '/config/app.php')['currency_symbol'];
    }
}
