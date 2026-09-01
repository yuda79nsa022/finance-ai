<?php

namespace App\Controllers;

use App\Core\AIClient;
use App\Core\Controller;
use App\Core\MonthCalculator;
use App\Core\ReceiptScanner;
use App\Models\Category;
use App\Models\ExpenseModel;
use App\Models\FixedCostModel;
use App\Models\IncomeModel;
use App\Models\LoanLedgerModel;
use App\Models\Lender;
use App\Models\MonthModel;
use App\Models\PaymentMethod;

class MonthController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function show(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);

        // Self-heal: pick up any loan payment sitting in Fixed Costs under a
        // loan-type category that never got a matching Lender record (e.g.
        // typed directly into Fixed Costs instead of through "Add Loan").
        // Cheap for a personal-scale dataset, and makes the Loan Tracker
        // correct on every visit without requiring a re-save of that row.
        LoanLedgerModel::recalculateMonth($month['id']);

        // Each loan ledger row's "Paid This Month" is capped at the opening
        // balance (MIN(installment, opening)), so it can understate what was
        // actually entered — the Edit Loan form needs the real, uncapped
        // installment amount from the underlying Fixed Cost row, not the
        // capped ledger figure.
        $loanLedger = LoanLedgerModel::forMonth($month['id']);
        foreach ($loanLedger as &$loanRow) {
            $installmentRow = FixedCostModel::findLoanRow($month['id'], $loanRow['lender_name']);
            $loanRow['installment_amount'] = $installmentRow ? (float) $installmentRow['amount'] : 0.0;
        }
        unset($loanRow);

        // For any Fixed Cost row under a loan-type category, attach the
        // matching lender's current opening balance so the Fixed Cost
        // Edit modal can show/pre-fill an "Opening Balance" field without
        // the user having to separately open the Loan Tracker's own Edit
        // Loan modal.
        $fixedCosts = FixedCostModel::forMonth($month['id']);
        foreach ($fixedCosts as &$fcRow) {
            if (($fcRow['category_type'] ?? null) === 'loan') {
                $lender = Lender::findByNameAny($fcRow['item'], $this->currentUserId());
                $fcRow['lender_initial_balance'] = $lender ? (float) $lender['initial_balance'] : null;
            }
        }
        unset($fcRow);

        $this->view('month/show', [
            'month'       => $month,
            'income'      => IncomeModel::forMonth($month['id']),
            'fixedCosts'  => $fixedCosts,
            'expenses'    => ExpenseModel::forMonth($month['id']),
            'loanLedger'  => $loanLedger,
            'loanTotals'  => LoanLedgerModel::totalsForMonth($month['id']),
            'summary'     => MonthCalculator::summary($month['id']),
            'catSummary'  => MonthCalculator::categorySummary($month['id']),
            'categories'  => Category::all(),
            'loanCategories' => Category::allByType('loan'), // every loan-type category — Personal Loan, Bank Loan, and any custom ones (Car Loan, Mortgage, ...)
            'paymentMethods' => PaymentMethod::all(),
            'printMode'   => $this->input('print') === '1',
            'error'       => $this->input('error'),
        ]);
    }

    public function addIncome(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        IncomeModel::add($month['id'], $this->input('source'), (float) $this->input('amount'), $this->input('notes'));
        $this->redirect('/month/' . $month['id']);
    }

    public function updateIncome(string $monthId, string $id): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        IncomeModel::update((int) $id, $month['id'], $this->input('source'), (float) $this->input('amount'), $this->input('notes'));
        $this->redirect('/month/' . $month['id']);
    }

    public function deleteIncome(string $monthId, string $id): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        IncomeModel::delete((int) $id, $month['id']);
        $this->redirect('/month/' . $monthId);
    }

    public function addFixedCost(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        $item = $this->input('item');
        $categoryId = (int) $this->input('category_id');
        FixedCostModel::add($month['id'], [
            'item'              => $item,
            'category_id'       => $categoryId,
            'amount'            => (float) $this->input('amount'),
            'due_day'           => $this->input('due_day') ?: null,
            'payment_method_id' => $this->input('payment_method_id') ?: null,
            'notes'             => $this->input('notes'),
        ]);
        $this->applyLoanOpeningBalance($item, $categoryId, $this->input('opening_balance'), $this->currentUserId());
        LoanLedgerModel::recalculateMonth($month['id']); // Personal-Loan rows feed the loan ledger
        $this->redirect('/month/' . $month['id']);
    }

    public function updateFixedCost(string $monthId, string $id): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        $item = $this->input('item');
        $categoryId = (int) $this->input('category_id');
        FixedCostModel::update((int) $id, [
            'item'              => $item,
            'category_id'       => $categoryId,
            'amount'            => (float) $this->input('amount'),
            'due_day'           => $this->input('due_day') ?: null,
            'payment_method_id' => $this->input('payment_method_id') ?: null,
            'notes'             => $this->input('notes'),
        ], $month['id']);
        $this->applyLoanOpeningBalance($item, $categoryId, $this->input('opening_balance'), $this->currentUserId());
        LoanLedgerModel::recalculateMonth($month['id']); // amount may feed a lender's installment
        $this->redirect('/month/' . $month['id']);
    }

    /**
     * When a Fixed Cost is saved under a loan-type category and an Opening
     * Balance was submitted alongside it, upserts the matching Lender's
     * initial_balance/category_id — creating the Lender record if one
     * doesn't exist yet (e.g. this loan was only ever typed into Fixed
     * Costs and auto-detected by the ledger's own sync). Must run BEFORE
     * LoanLedgerModel::recalculateMonth(), which would otherwise
     * auto-create a fresh 0-balance placeholder for a brand-new lender
     * instead of using the balance the user just entered.
     */
    private function applyLoanOpeningBalance(string $item, int $categoryId, $openingBalanceInput, int $userId): void
    {
        $item = trim($item);
        if ($item === '' || $openingBalanceInput === null || $openingBalanceInput === '') {
            return;
        }

        $category = Category::find($categoryId);
        if (!$category || $category['type'] !== 'loan') {
            return;
        }

        $openingBalance = (float) $openingBalanceInput;
        $type = stripos($category['name'], 'bank') !== false ? 'bank' : 'person';

        $lender = Lender::findByNameAny($item, $userId);
        if (!$lender) {
            Lender::create($userId, $item, $openingBalance, $type, 0, $categoryId);
        } else {
            if (!$lender['is_active']) {
                Lender::activate((int) $lender['id'], $userId);
            }
            Lender::update((int) $lender['id'], $userId, $item, $lender['type'], $openingBalance, $categoryId);
        }
    }

    public function deleteFixedCost(string $monthId, string $id): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        FixedCostModel::delete((int) $id, $month['id']);
        LoanLedgerModel::recalculateMonth((int) $monthId);
        $this->redirect('/month/' . $monthId);
    }

    /** Adds a new loan directly from the month page — creates the lender (or reactivates/updates it if the name already exists) plus this month's installment as a Fixed Cost row, then recalculates the ledger. */
    public function addLoan(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);

        $name = trim((string) $this->input('name'));
        $type = $this->input('type', 'person') === 'bank' ? 'bank' : 'person';
        $categoryId = $this->categoryIdForLoan($this->input('category_id'), $type);
        $initialBalance = (float) $this->input('initial_balance', 0);
        $payment = (float) $this->input('payment', 0);

        if ($name === '') {
            $this->redirect('/month/' . $month['id']);
            return;
        }

        $userId = $this->currentUserId();
        $lender = Lender::findByNameAny($name, $userId);
        if (!$lender) {
            $lenderId = Lender::create($userId, $name, $initialBalance, $type, 0, $categoryId);
        } else {
            $lenderId = (int) $lender['id'];
            if (!$lender['is_active']) {
                Lender::activate($lenderId, $userId);
            }
            Lender::update($lenderId, $userId, $name, $type, $initialBalance, $categoryId);
        }

        if ($payment > 0) {
            FixedCostModel::add($month['id'], [
                'item'        => $name,
                'category_id' => $categoryId,
                'amount'      => $payment,
            ]);
        }

        LoanLedgerModel::recalculateMonth($month['id']);
        $this->redirect('/month/' . $month['id']);
    }

    /**
     * Edits a lender's name/type/opening balance and this month's installment.
     * Renaming cascades to every Fixed Cost row that represents this lender's
     * payments (across all months), since the loan ledger matches purely on
     * name — otherwise the rename would silently orphan past and future
     * installment rows from the lender they belong to.
     */
    public function updateLoan(string $monthId, string $lenderId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);

        $userId = $this->currentUserId();
        $lender = Lender::find((int) $lenderId, $userId);
        if (!$lender) {
            $this->redirect('/month/' . $month['id']);
            return;
        }

        $newName = trim((string) $this->input('name'));
        if ($newName === '') {
            $newName = $lender['name'];
        }
        $newType = $this->input('type', 'person') === 'bank' ? 'bank' : 'person';
        $categoryId = $this->categoryIdForLoan($this->input('category_id'), $newType);
        $initialBalance = (float) $this->input('initial_balance', 0);
        $payment = (float) $this->input('payment', 0);

        if ($newName !== $lender['name']) {
            FixedCostModel::renameLoanItem($lender['name'], $newName, $userId);
        }
        Lender::update((int) $lenderId, $userId, $newName, $newType, $initialBalance, $categoryId);

        $existingRow = FixedCostModel::findLoanRow($month['id'], $newName);
        if ($existingRow) {
            FixedCostModel::update((int) $existingRow['id'], [
                'item'              => $newName,
                'category_id'       => $categoryId,
                'amount'            => $payment,
                'due_day'           => $existingRow['due_day'],
                'payment_method_id' => $existingRow['payment_method_id'],
                'notes'             => $existingRow['notes'],
            ], $month['id']);
        } elseif ($payment > 0) {
            FixedCostModel::add($month['id'], [
                'item'        => $newName,
                'category_id' => $categoryId,
                'amount'      => $payment,
            ]);
        }

        LoanLedgerModel::recalculateMonth($month['id']);
        $this->redirect('/month/' . $month['id']);
    }

    /** Removes a lender from this month onward (history before this month is untouched) — same "remove from tracker" flow as the paid-off prompt, just reachable for any loan, not only a paid-off one. */
    public function deleteLoan(string $monthId, string $lenderId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);

        $userId = $this->currentUserId();
        Lender::deactivate((int) $lenderId, $userId);
        LoanLedgerModel::removeLenderFromMonthOnward((int) $lenderId, $userId, $month['month_date']);

        $this->redirect('/month/' . $month['id']);
    }

    public function addExpense(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        ExpenseModel::add($month['id'], [
            'expense_date'      => $this->input('expense_date'),
            'amount'            => (float) $this->input('amount'),
            'category_id'       => (int) $this->input('category_id'),
            'description'       => $this->input('description'),
            'payment_method_id' => $this->input('payment_method_id') ?: null,
            'receipt_path'      => $this->ownedReceiptPath($this->input('receipt_path')),
        ]);
        $this->redirect('/month/' . $month['id']);
    }

    /**
     * Uploads a receipt photo and asks the configured AI provider (Settings
     * > AI Advisor — Anthropic/OpenAI only, see AIClient::visionExtract())
     * to read it: date, amount, merchant, and a best-guess category from
     * this app's own category list. Returns the extracted fields as JSON
     * for the Add Expense form to pre-fill — nothing is saved to the
     * Variable Expenses Log here; the user still reviews and clicks Add,
     * same as manual entry, since a misread amount/category is exactly the
     * kind of mistake that must be easy to catch before it becomes real
     * financial data.
     */
    public function scanReceipt(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);

        if (empty($_FILES['receipt']['tmp_name']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No image was received — choose a photo and try again.']);
            return;
        }

        $tmpPath = $_FILES['receipt']['tmp_name'];
        if ((int) $_FILES['receipt']['size'] > 8 * 1024 * 1024) {
            $this->json(['ok' => false, 'error' => 'That image is too large (max 8MB) — try a smaller photo.']);
            return;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($tmpPath) : (string) ($_FILES['receipt']['type'] ?? '');
        $allowedExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];
        if (!isset($allowedExtensions[$mime])) {
            $this->json(['ok' => false, 'error' => 'Please upload a JPG, PNG, WEBP, or HEIC photo of the receipt.']);
            return;
        }

        if (!AIClient::isConfigured()) {
            $this->json(['ok' => false, 'error' => 'Receipt scanning needs the AI Advisor configured first — ask an admin to add a Claude or ChatGPT API key in Settings > AI Advisor. You can still add this expense manually.']);
            return;
        }

        $userId = $this->currentUserId();
        $dir = dirname(__DIR__, 2) . '/storage/receipts/' . $userId;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            $this->json(['ok' => false, 'error' => 'Could not save the uploaded image on the server.']);
            return;
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $allowedExtensions[$mime];
        if (!move_uploaded_file($tmpPath, $dir . '/' . $filename)) {
            $this->json(['ok' => false, 'error' => 'Could not save the uploaded image on the server.']);
            return;
        }

        $result = ReceiptScanner::extract($dir . '/' . $filename, $mime, Category::all());
        $result['receipt_path'] = $userId . '/' . $filename; // kept even on ok:false, so a failed read can still be attached manually
        $this->json($result);
    }

    /** Streams a receipt image back only to the user who owns the expense it's attached to — never served directly, always through this ownership check. */
    public function receiptImage(string $monthId, string $expenseId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $expense = ExpenseModel::find((int) $expenseId, $month['id']);
        if (!$expense || empty($expense['receipt_path'])) {
            http_response_code(404);
            echo 'Receipt not found.';
            exit;
        }

        $base = realpath(dirname(__DIR__, 2) . '/storage/receipts');
        $path = realpath($base . '/' . $expense['receipt_path']);
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            echo 'Receipt not found.';
            exit;
        }

        header('Content-Type: ' . (function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream'));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="receipt.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        readfile($path);
        exit;
    }

    /**
     * Only trusts a receipt_path that (a) is prefixed with this user's own
     * id (scanReceipt() always saves under storage/receipts/{userId}/, so a
     * legitimately-scanned path always starts that way) and (b) resolves to
     * a real file actually inside storage/receipts — rejects everything
     * else rather than trusting client input, since this value comes back
     * from a hidden form field the browser could have tampered with to
     * point at another user's uploaded receipt or an arbitrary path.
     */
    private function ownedReceiptPath($path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (!str_starts_with($path, $this->currentUserId() . '/')) {
            return null;
        }

        $base = realpath(dirname(__DIR__, 2) . '/storage/receipts');
        $resolved = realpath($base . '/' . $path);
        if ($base === false || $resolved === false || !str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    public function updateExpense(string $monthId, string $id): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        ExpenseModel::update((int) $id, [
            'expense_date'      => $this->input('expense_date'),
            'amount'            => (float) $this->input('amount'),
            'category_id'       => (int) $this->input('category_id'),
            'description'       => $this->input('description'),
            'payment_method_id' => $this->input('payment_method_id') ?: null,
        ], $month['id']);
        $this->redirect('/month/' . $month['id']);
    }

    public function deleteExpense(string $monthId, string $id): void
    {
        $month = $this->requireMonth((int) $monthId);
        $this->denyIfLocked($month);
        ExpenseModel::delete((int) $id, $month['id']);
        $this->redirect('/month/' . $monthId);
    }

    /** Ownership-checked like every other write in this controller — lock/unlock previously skipped this and let any logged-in user toggle any other user's month by id. */
    public function lock(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        MonthModel::setLocked($month['id'], true);
        $this->redirect('/month/' . $month['id']);
    }

    public function unlock(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        MonthModel::setLocked($month['id'], false);
        $this->redirect('/month/' . $month['id']);
    }

    /** Duplicate this month's Fixed Costs + Income into the next calendar month within the same financial year. */
    public function duplicate(string $monthId): void
    {
        $source = $this->requireMonth((int) $monthId);
        $next = MonthModel::allForYear($source['financial_year_id']);

        $target = null;
        foreach ($next as $m) {
            if ($m['month_date'] > $source['month_date']) {
                $target = $m;
                break;
            }
        }
        if (!$target) {
            $this->redirect('/month/' . $source['id'] . '?error=no-next-month');
            return;
        }

        MonthModel::duplicateInto($source['id'], $target['id']);
        LoanLedgerModel::recalculateMonth($target['id']);
        $this->redirect('/month/' . $target['id']);
    }

    /** Loads a month only if it belongs to the logged-in user — every user has their own separate tracker, so a month id that exists but belongs to someone else must 404 exactly like one that doesn't exist at all. */
    private function requireMonth(int $id): array
    {
        $month = MonthModel::findOwned($id, $this->currentUserId());
        if (!$month) {
            http_response_code(404);
            echo 'Month not found.';
            exit;
        }
        return $month;
    }

    /** Blocks any write to a locked month's Income / Fixed Costs / Expenses / Loans, mirroring the "Lock" button in the UI. */
    private function denyIfLocked(array $month): void
    {
        if ((bool) $month['is_locked']) {
            $this->redirect('/month/' . $month['id'] . '?error=' . urlencode('This month is locked. Unlock it first to make changes.'));
        }
    }

    /**
     * Resolves which loan-type category a lender's payments should be filed
     * under. If the form submitted a specific category (any category with
     * type='loan' — Personal Loan, Bank Loan, Car Loan, Mortgage, Credit
     * Card, or a custom one an admin added), that's used as-is — this is
     * what lets the Loan Tracker capture every loan type, not just the two
     * built-in ones. Falls back to the Bank Loan / Personal Loan mapping
     * (same one the wizard uses) only when no category was chosen.
     */
    private function categoryIdForLoan($submittedCategoryId, string $type): ?int
    {
        $submittedCategoryId = (int) $submittedCategoryId;
        if ($submittedCategoryId > 0) {
            foreach (Category::allByType('loan') as $c) {
                if ((int) $c['id'] === $submittedCategoryId) {
                    return $submittedCategoryId;
                }
            }
        }

        $target = $type === 'bank' ? 'Bank Loan' : 'Personal Loan';
        foreach (Category::all() as $c) {
            if ($c['name'] === $target) {
                return (int) $c['id'];
            }
        }
        return null;
    }
}
