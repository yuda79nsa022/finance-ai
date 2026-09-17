<?php

namespace App\Core;

use App\Models\Category;
use App\Models\ExpenseModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parses a bank statement export (CSV/XLS/XLSX, or a text-based PDF) into
 * candidate Variable Expense rows for the "Import Bank Statement"
 * review-before-save flow — this never saves anything itself, same
 * reasoning as ReceiptScanner: a bulk import is exactly the kind of
 * operation where a parsing mistake (wrong column, wrong sign convention)
 * must be easy to catch before it becomes dozens of wrong expense rows,
 * not after.
 *
 * CSV/Excel is the primary, most reliable path (via PhpSpreadsheet,
 * already a dependency for report exports) — a deterministic column-based
 * parse with no AI call and no per-request cost. PDF is a second path for
 * banks (like NBK) that only offer a PDF statement: real bank-statement
 * PDFs are almost always genuinely text-based (not a scanned image), just
 * often encrypted against editing/copying — pure-PHP PDF libraries
 * (tried smalot/pdfparser) can't extract text from those at all, so this
 * shells out to the system `pdftotext` tool (poppler-utils) instead, with
 * a clear error if it isn't installed rather than a silent failure. A
 * photo of a printed statement is deliberately still out of scope: it's
 * the least reliable of the three for correctly segmenting many
 * transaction rows out of one AI vision call.
 */
class StatementImporter
{
    private const MAX_HEADER_SCAN_ROWS = 15;

    /**
     * @param string $extension The uploaded file's original extension ("csv", "xlsx", "xls", "pdf") —
     *   needed because $filePath is normally PHP's tmp upload path (e.g. "/tmp/phpXXXXXX"),
     *   which carries no extension of its own to detect the format from.
     * @return array{ok: bool, rows?: array, error?: string}
     * Each row: ['expense_date' => 'Y-m-d', 'amount' => float, 'description' => ?string,
     *            'category_id' => ?int, 'category_name' => ?string,
     *            'is_duplicate' => bool, 'duplicate_of' => ?array]
     */
    public static function parse(string $filePath, int $monthId, string $extension = 'csv'): array
    {
        if (strtolower($extension) === 'pdf') {
            return self::parsePdf($filePath, $monthId);
        }

        try {
            if (strtolower($extension) === 'csv') {
                // A plain IOFactory::load() auto-detects the CSV delimiter by
                // sampling the file, which guesses wrong on real bank exports
                // that start with a few comma-free metadata lines (account
                // number, statement period) before the actual header row —
                // it can settle on a space or semicolon instead of a comma.
                // Force comma explicitly rather than trust that heuristic.
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $spreadsheet = $reader->load($filePath);
            } else {
                $spreadsheet = IOFactory::load($filePath);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Could not read that file — make sure it\'s a CSV or Excel export from your bank.'];
        }

        // formatData=false: keep raw cell values (a date stays the Excel serial
        // number, not a locale-formatted string) so parseDate() has one
        // consistent numeric path instead of guessing at display formatting.
        $grid = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        if (!$grid) {
            return ['ok' => false, 'error' => 'That file appears to be empty.'];
        }

        $headerIndex = self::findHeaderRow($grid);
        if ($headerIndex === null) {
            return ['ok' => false, 'error' => 'Couldn\'t find a Date column and an Amount (or Debit) column — check the file has header names like "Date" and "Amount"/"Debit" somewhere in its first few rows.'];
        }

        $columns = self::detectColumns($grid[$headerIndex]);
        $dataRows = array_slice($grid, $headerIndex + 1);
        $signConvention = $columns['amount'] !== null ? self::detectSignConvention($dataRows, $columns['amount']) : null;

        $categories = Category::all();
        $rows = [];
        foreach ($dataRows as $raw) {
            $amount = self::extractAmount($raw, $columns, $signConvention);
            if ($amount === null || $amount <= 0) {
                continue; // not a debit on this row (a credit, a blank row, a running-balance column, ...)
            }

            $date = $columns['date'] !== null ? self::parseDate($raw[$columns['date']] ?? null) : null;
            if ($date === null) {
                continue; // can't file an expense without a real date
            }

            $description = $columns['description'] !== null ? trim((string) ($raw[$columns['description']] ?? '')) : '';
            $rows[] = self::buildRow($monthId, $date, $amount, $description, $categories);
        }

        if (!$rows) {
            return ['ok' => false, 'error' => 'No transactions could be read from that file — check it has a Date column and an Amount (or Debit) column with real values in the rows below the header.'];
        }

        return ['ok' => true, 'rows' => $rows];
    }

    /**
     * Real bank exports often have a few metadata lines (account number,
     * statement period) before the real header row — scans forward for the
     * first row that looks like a real header (has both a date-ish and an
     * amount-ish column name) instead of assuming row 1 always is it.
     */
    private static function findHeaderRow(array $grid): ?int
    {
        $limit = min(count($grid), self::MAX_HEADER_SCAN_ROWS);
        for ($i = 0; $i < $limit; $i++) {
            $columns = self::detectColumns($grid[$i]);
            if ($columns['date'] !== null && ($columns['amount'] !== null || $columns['debit'] !== null)) {
                return $i;
            }
        }
        return null;
    }

    private static function detectColumns(array $headerRow): array
    {
        $map = ['date' => null, 'description' => null, 'amount' => null, 'debit' => null, 'credit' => null];
        foreach ($headerRow as $idx => $cell) {
            $h = strtolower(trim((string) $cell));
            if ($h === '') {
                continue;
            }
            if ($map['date'] === null && str_contains($h, 'date')) {
                $map['date'] = $idx;
            } elseif ($map['description'] === null && self::containsAny($h, ['description', 'narrative', 'details', 'memo', 'payee', 'merchant', 'particulars'])) {
                $map['description'] = $idx;
            } elseif ($map['debit'] === null && self::containsAny($h, ['debit', 'withdrawal', 'paid out', 'money out'])) {
                $map['debit'] = $idx;
            } elseif ($map['credit'] === null && self::containsAny($h, ['credit', 'deposit', 'paid in', 'money in'])) {
                $map['credit'] = $idx;
            } elseif ($map['amount'] === null && str_contains($h, 'amount')) {
                $map['amount'] = $idx;
            }
        }
        return $map;
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A single "Amount" column's sign convention varies by bank — some show
     * spending as negative (the common case this defaults to), others only
     * ever export debits as plain positive numbers with no credits at all
     * in the file. Decided once from the whole file rather than per row: if
     * any negative value exists anywhere in the column, negative rows are
     * the expenses; if none does, every row with a real amount is treated
     * as an expense, since there's no other signal to go on.
     */
    private static function detectSignConvention(array $dataRows, int $amountCol): string
    {
        foreach ($dataRows as $row) {
            $value = self::toFloat($row[$amountCol] ?? null);
            if ($value !== null && $value < 0) {
                return 'negative_is_expense';
            }
        }
        return 'all_positive_are_expenses';
    }

    private static function extractAmount(array $row, array $columns, ?string $signConvention): ?float
    {
        if ($columns['debit'] !== null) {
            $value = self::toFloat($row[$columns['debit']] ?? null);
            return $value !== null && $value > 0 ? $value : null;
        }
        if ($columns['amount'] !== null) {
            $value = self::toFloat($row[$columns['amount']] ?? null);
            if ($value === null || $value == 0.0) {
                return null;
            }
            if ($signConvention === 'negative_is_expense') {
                return $value < 0 ? abs($value) : null;
            }
            return abs($value);
        }
        return null;
    }

    /** Strips currency symbols/thousands separators and reads "(12.50)"-style parenthesized negatives some statement exports use. */
    private static function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $text = trim((string) $value);
        $negative = false;
        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $negative = true;
            $text = substr($text, 1, -1);
        }
        $text = preg_replace('/[^0-9.\-]/', '', $text);
        if ($text === null || $text === '' || $text === '-' || $text === '.') {
            return null;
        }
        $parsed = (float) $text;
        return $negative ? -abs($parsed) : $parsed;
    }

    private static function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd M Y', 'M d, Y', 'd-M-y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $text);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }
        $timestamp = strtotime($text);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private static function defaultOther(array $categories): ?int
    {
        foreach ($categories as $cat) {
            if (strcasecmp($cat['name'], 'Other') === 0) {
                return (int) $cat['id'];
            }
        }
        return null;
    }

    private static function categoryName(array $categories, ?int $id): ?string
    {
        foreach ($categories as $cat) {
            if ((int) $cat['id'] === $id) {
                return $cat['name'];
            }
        }
        return null;
    }

    /** Shared by every input format (CSV/XLSX/PDF): turns one already-extracted (date, positive amount, description) into the row shape the review UI expects, guessing a category and flagging a duplicate the same way regardless of where the row came from. */
    private static function buildRow(int $monthId, string $date, float $amount, string $description, array $categories): array
    {
        $categoryId = $description !== '' ? Category::guessFromText($description) : self::defaultOther($categories);
        $categoryName = self::categoryName($categories, $categoryId);
        $duplicate = ExpenseModel::findDuplicate($monthId, $date, $amount);

        return [
            'expense_date'  => $date,
            'amount'        => round($amount, 3),
            'description'   => $description !== '' ? $description : null,
            'category_id'   => $categoryId,
            'category_name' => $categoryName,
            'is_duplicate'  => $duplicate !== null,
            'duplicate_of'  => $duplicate ? ['description' => $duplicate['description'], 'amount' => (float) $duplicate['amount']] : null,
        ];
    }

    // ---- PDF path ---------------------------------------------------------

    /**
     * A transaction row's whole first line, as pdftotext -layout emits a
     * table row, in the common bank-statement shape this targets:
     *   <date>  <branch/ref code>  <description...>  <posting date>  <amount>  <balance>
     * Wrapped description continuation lines (further-indented, no leading
     * date) carry no additional numbers and are intentionally not
     * appended — the first line alone already has a usable description,
     * and appending the wrapped lines would mostly add noise (merchant
     * IDs, transfer reference numbers) with little benefit to the review
     * table. Captures: 1=date, 2=code, 3=description, 4=posting date,
     * 5=amount (signed), 6=balance.
     */
    private const PDF_LINE_STRICT = '/^\s*(\d{1,2}\/\d{1,2}\/\d{4})\s+(\S+)\s+(.+?)\s+(\d{1,2}\/\d{1,2}\/\d{4})\s+(-?[\d,]+\.\d{2,3})\s+(-?[\d,]+\.\d{2,3})\s*$/';

    /**
     * Looser fallback for a statement layout that doesn't have a separate
     * branch/reference-code column or a second (posting) date column —
     * just date, description, amount, balance. Tried only when the strict
     * pattern above doesn't match a given line, so a layout matching the
     * strict pattern always gets the cleaner description.
     * Captures: 1=date, 2=description, 3=amount (signed), 4=balance.
     */
    private const PDF_LINE_FALLBACK = '/^\s*(\d{1,2}\/\d{1,2}\/\d{4})\s+(.+?)\s+(-?[\d,]+\.\d{2,3})\s+(-?[\d,]+\.\d{2,3})\s*$/';

    private static function parsePdf(string $filePath, int $monthId): array
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return ['ok' => false, 'error' => 'PDF statement import needs the server to allow running an external program (exec), which is disabled here. Use a CSV/Excel export instead, if your bank offers one.'];
        }

        $extraction = self::extractPdfText($filePath);
        if (!$extraction['ok']) {
            return $extraction;
        }

        $transactions = [];
        foreach ($extraction['lines'] as $line) {
            if (preg_match(self::PDF_LINE_STRICT, $line, $m)) {
                $transactions[] = ['date' => $m[1], 'description' => trim($m[3]), 'amount' => $m[5]];
            } elseif (preg_match(self::PDF_LINE_FALLBACK, $line, $m)) {
                $transactions[] = ['date' => $m[1], 'description' => trim($m[2]), 'amount' => $m[3]];
            }
        }

        if (!$transactions) {
            return ['ok' => false, 'error' => 'No transaction rows could be recognized in that PDF — this statement\'s layout may not be one this app knows how to read. A CSV/Excel export, if your bank offers one, is more reliable.'];
        }

        $amounts = array_map(fn($t) => self::toFloat($t['amount']), $transactions);
        $signConvention = in_array(true, array_map(fn($v) => $v !== null && $v < 0, $amounts), true)
            ? 'negative_is_expense'
            : 'all_positive_are_expenses';

        $categories = Category::all();
        $rows = [];
        foreach ($transactions as $i => $t) {
            $value = $amounts[$i];
            if ($value === null || $value == 0.0) {
                continue;
            }
            $amount = $signConvention === 'negative_is_expense' ? ($value < 0 ? abs($value) : null) : abs($value);
            if ($amount === null) {
                continue; // a credit row under the negative-is-expense convention
            }
            $date = self::parseDate($t['date']);
            if ($date === null) {
                continue;
            }
            $rows[] = self::buildRow($monthId, $date, $amount, $t['description'], $categories);
        }

        if (!$rows) {
            return ['ok' => false, 'error' => 'Found transaction-shaped rows in that PDF, but none looked like real spending (only credits, or no valid amounts) — double check it\'s the right statement.'];
        }

        return ['ok' => true, 'rows' => $rows];
    }

    /**
     * Runs the uploaded PDF through the system `pdftotext` tool
     * (poppler-utils) with -layout, which preserves column spacing well
     * enough for the line-pattern matching above — no pure-PHP library
     * tested could extract text from a real, encrypted (but not
     * password-protected) bank-statement PDF; this is why PDF import
     * isn't available if pdftotext isn't installed.
     *
     * Interprets the ONE real attempt rather than doing a separate
     * speculative "-v" pre-check first: a missing command still returns
     * successfully from exec() with the shell's own "not found" text
     * captured as output (exit code 127 on a POSIX shell) rather than
     * exec() itself failing, so a naive "did we get any output back"
     * check reads that as success. Checked here instead so the right
     * error (missing pdftotext vs. an unreadable/corrupt PDF) reaches
     * the user, not a generic one.
     *
     * @return array{ok: bool, lines?: array, error?: string}
     */
    private static function extractPdfText(string $filePath): array
    {
        $cmd = 'pdftotext -layout ' . escapeshellarg($filePath) . ' - 2>&1';
        $output = [];
        $exitCode = null;
        @exec($cmd, $output, $exitCode);

        $combined = strtolower(implode(' ', $output));
        $commandMissing = $exitCode === 127
            || str_contains($combined, 'not found')
            || str_contains($combined, 'is not recognized');
        if ($commandMissing) {
            return ['ok' => false, 'error' => 'PDF statement import needs "pdftotext" (from poppler-utils) installed on this server, which wasn\'t found. Install poppler-utils, or use a CSV/Excel export instead if your bank offers one.'];
        }
        if ($exitCode !== 0 || !$output) {
            return ['ok' => false, 'error' => 'Could not read that PDF — it may be corrupted, password-protected in a way this can\'t open, or not actually a PDF file.'];
        }

        return ['ok' => true, 'lines' => $output];
    }
}
