<?php

namespace App\Controllers;

use App\Core\AIClient;
use App\Core\Controller;
use App\Core\Database;
use App\Models\AiSettings;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\Lender;
use App\Models\LoanLedgerModel;
use App\Models\MonthModel;
use App\Models\PaymentMethod;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->requireAdmin();
    }

    public function index(): void
    {
        // Admin has their own separate tracker exactly like any other user
        // (no built-in visibility into other users' financial data) — so
        // the Lenders and Financial Years tabs below show only the admin
        // account's own lenders/years, scoped by the admin's own user id.
        $this->view('admin/index', [
            'categories'     => Category::all(false),
            'paymentMethods' => PaymentMethod::all(),
            'lenders'        => Lender::all(false, $this->currentUserId()),
            'financialYears' => FinancialYear::allForUser($this->currentUserId()),
            'users'          => \App\Models\User::all(),
            'aiSettings'     => AiSettings::get(),
            'aiProviders'    => AIClient::PROVIDER_LABELS,
            'error'          => $this->input('error'),
        ]);
    }

    // ---- AI Advisor --------------------------------------------------
    /**
     * Saves the one app-wide provider + API key + model + prompt template.
     * Leaving the API key field blank on submit keeps whatever key is
     * already saved (so switching provider or model doesn't force
     * re-pasting the key, and the key never has to round-trip back into
     * the form's HTML). Leaving the prompt blank resets it to the
     * built-in default rather than saving an empty prompt.
     */
    public function updateAiSettings(): void
    {
        $provider = $this->input('provider', 'anthropic');
        if (!array_key_exists($provider, AIClient::PROVIDER_LABELS)) {
            $provider = 'anthropic';
        }
        $model = trim((string) $this->input('model', ''));
        $prompt = trim((string) $this->input('prompt', ''));

        $newKey = trim((string) $this->input('api_key', ''));
        $apiKey = $newKey !== '' ? $newKey : AiSettings::get()['api_key'];

        AiSettings::update($provider, $apiKey, $model, $prompt);
        $this->redirect('/admin#ai-advisor');
    }

    // ---- Users ------------------------------------------------------------
    public function addUser(): void
    {
        $name = trim((string) $this->input('name', ''));
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $role = $this->input('role', 'user') === 'admin' ? 'admin' : 'user';

        if ($name === '' || $email === '') {
            $this->redirect('/admin?error=' . urlencode('Name and email are required.') . '#users');
            return;
        }
        // Deliberately more permissive than FILTER_VALIDATE_EMAIL: this app's own
        // installer seeds "admin@localhost" (no TLD), a normal pattern for a
        // self-hosted install without real email/DNS — FILTER_VALIDATE_EMAIL
        // rejects that, so it would block re-creating exactly that kind of
        // account. Still catches the actually malformed cases (no @, blank
        // local/domain part, embedded whitespace).
        if (!preg_match('/^\S+@\S+$/', $email)) {
            $this->redirect('/admin?error=' . urlencode('That doesn\'t look like a valid email address.') . '#users');
            return;
        }
        if (strlen($password) < 8) {
            $this->redirect('/admin?error=' . urlencode('Password must be at least 8 characters.') . '#users');
            return;
        }

        try {
            \App\Models\User::create($name, $email, $password, $role);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                $this->redirect('/admin?error=' . urlencode('A user with that email already exists.') . '#users');
                return;
            }
            throw $e;
        }

        $this->redirect('/admin#users');
    }

    public function deactivateUser(string $id): void
    {
        $targetId = (int) $id;

        if ($targetId === $this->currentUserId()) {
            $this->redirect('/admin?error=' . urlencode('You can\'t deactivate your own account while logged in as it.') . '#users');
            return;
        }

        $target = \App\Models\User::find($targetId);
        if ($target && $target['role'] === 'admin' && (bool) $target['is_active'] && \App\Models\User::countActiveAdmins() <= 1) {
            $this->redirect('/admin?error=' . urlencode('Can\'t deactivate the last remaining active administrator — promote another user to admin first.') . '#users');
            return;
        }

        \App\Models\User::setActive($targetId, false);
        $this->redirect('/admin#users');
    }

    public function activateUser(string $id): void
    {
        \App\Models\User::setActive((int) $id, true);
        $this->redirect('/admin#users');
    }

    /**
     * Lets an admin set a fresh password for an existing user without
     * deleting/recreating the account — useful both for a forgotten
     * password and for fixing an account that was created with a stray
     * space or wrong casing in the email (now normalized in User::create(),
     * but an account added before that fix can still be stuck with the
     * old, unmatched email — resetting the password here doesn't fix that;
     * deactivate the old row and re-add the user instead in that case).
     */
    public function resetUserPassword(string $id): void
    {
        $password = (string) $this->input('password', '');
        if (strlen($password) < 8) {
            $this->redirect('/admin?error=' . urlencode('New password must be at least 8 characters.') . '#users');
            return;
        }
        \App\Models\User::updatePassword((int) $id, $password);
        $this->redirect('/admin#users');
    }

    // ---- Categories --------------------------------------------------
    public function addCategory(): void
    {
        Category::create($this->input('name'), $this->input('type', 'expense'));
        $this->redirect('/admin#categories');
    }

    public function updateCategory(string $id): void
    {
        Category::update((int) $id, $this->input('name'), $this->input('type', 'expense'));
        $this->redirect('/admin#categories');
    }

    public function deactivateCategory(string $id): void
    {
        Category::deactivate((int) $id);
        $this->redirect('/admin#categories');
    }

    public function activateCategory(string $id): void
    {
        Category::activate((int) $id);
        $this->redirect('/admin#categories');
    }

    // ---- Payment methods ----------------------------------------------
    public function addPaymentMethod(): void
    {
        PaymentMethod::create($this->input('name'));
        $this->redirect('/admin#payment-methods');
    }

    public function deletePaymentMethod(string $id): void
    {
        try {
            PaymentMethod::delete((int) $id);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                $this->redirect('/admin?error=' . urlencode('That payment method is still used by existing fixed costs or expenses — remove or reassign those first.') . '#payment-methods');
                return;
            }
            throw $e;
        }
        $this->redirect('/admin#payment-methods');
    }

    // ---- Lenders --------------------------------------------------------
    public function addLender(): void
    {
        Lender::create($this->currentUserId(), $this->input('name'), (float) $this->input('initial_balance', 0), $this->input('type', 'person'), 0);
        $this->redirect('/admin#lenders');
    }

    /**
     * Retire a lender — typically used once their loan hits a zero balance.
     * Optionally pass month_id (the month the "paid off" prompt was shown on)
     * so the ledger row for that month, and every month after it, is removed
     * too — otherwise the lender just stops appearing starting next time the
     * ledger recalculates. Pass `return` to come back to the page that
     * triggered this (e.g. the month page) instead of /admin.
     */
    public function deactivateLender(string $id): void
    {
        $userId = $this->currentUserId();
        Lender::deactivate((int) $id, $userId);

        $monthId = $this->input('month_id');
        if ($monthId) {
            $month = MonthModel::findOwned((int) $monthId, $userId);
            if ($month) {
                LoanLedgerModel::removeLenderFromMonthOnward((int) $id, $userId, $month['month_date']);
            }
        }

        $return = $this->input('return');
        $this->redirect($return && str_starts_with($return, '/') ? $return : '/admin#lenders');
    }

    public function activateLender(string $id): void
    {
        Lender::activate((int) $id, $this->currentUserId());
        $this->redirect('/admin#lenders');
    }

    // ---- Financial years -------------------------------------------------
    public function createFinancialYear(): void
    {
        $userId = $this->currentUserId();
        $label = $this->input('label');
        $start = $this->input('start_month'); // YYYY-MM-01
        $end   = $this->input('end_month');
        $yearId = FinancialYear::create($userId, $label, $start, $end);

        // Auto-generate the 12 monthly records (equivalent of the 12 duplicated sheets)
        $cursor = new \DateTime($start);
        $endDate = new \DateTime($end);
        $firstMonthId = null;
        while ($cursor <= $endDate) {
            $id = MonthModel::create($userId, $yearId, $cursor->format('Y-m-01'), $cursor->format('M Y'));
            $firstMonthId ??= $id;
            $cursor->modify('+1 month');
        }

        // Seed the loan ledger starting at this year's first month. If any
        // lender still has a balance left from the month right before this
        // one (e.g. the last month of the previous financial year), that
        // balance becomes this month's opening balance and cascades forward
        // through the rest of the new year automatically.
        if ($firstMonthId !== null) {
            LoanLedgerModel::recalculateMonth($firstMonthId);
        }

        $this->redirect('/admin#financial-years');
    }

    // ---- Backup / restore --------------------------------------------------
    public function backup(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $filename = 'backup_' . date('Y-m-d_His') . '.sql';
        $path = dirname(__DIR__, 2) . '/storage/backups/' . $filename;

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s %s > %s 2>&1',
            escapeshellarg($cfg['host']),
            escapeshellarg($cfg['port']),
            escapeshellarg($cfg['username']),
            $cfg['password'] !== '' ? '--password=' . escapeshellarg($cfg['password']) : '',
            escapeshellarg($cfg['database']),
            escapeshellarg($path)
        );
        exec($cmd, $output, $code);

        if ($code !== 0 || !file_exists($path)) {
            $this->json(['error' => 'Backup failed. Ensure mysqldump is on PATH (XAMPP: mysql/bin).'], 500);
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($path);
        exit;
    }

    public function restore(): void
    {
        if (empty($_FILES['backup_file']['tmp_name'])) {
            $this->redirect('/admin?error=no-file');
        }

        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $tmp = $_FILES['backup_file']['tmp_name'];

        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s %s %s < %s 2>&1',
            escapeshellarg($cfg['host']),
            escapeshellarg($cfg['port']),
            escapeshellarg($cfg['username']),
            $cfg['password'] !== '' ? '--password=' . escapeshellarg($cfg['password']) : '',
            escapeshellarg($cfg['database']),
            escapeshellarg($tmp)
        );
        exec($cmd, $output, $code);

        $this->redirect('/admin?restored=' . ($code === 0 ? '1' : '0'));
    }
}
