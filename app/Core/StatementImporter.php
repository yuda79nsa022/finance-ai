<?php

namespace App\Core;

use App\Models\Category;
use App\Models\ExpenseModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parses a bank statement export (CSV/XLS/XLSX) into candidate Variable
 * Expense rows for the "Import Bank Statement" review-before-save flow —
 * this never saves anything itself, same reasoning as ReceiptScanner: a
 * bulk import is exactly the kind of operation where a parsing mistake
 * (wrong column, wrong sign convention) must be easy to catch before it
 * becomes dozens of wrong expense rows, not after.
 *
 * Deliberately CSV/Excel-only (via PhpSpreadsheet, already a dependency
 * for report exports) rather than PDF or a photo of a printed statement —
 * every bank's online banking lets you export transactions this way, and
 * it's a deterministic column-based parse with no AI call, no per-request
 * cost, and far higher accuracy than trying to read a statement's layout
 * as an image.
 */
class StatementImporter
{
    private const MAX_HEADER_SCAN_ROWS = 15;

    /**
     * @param string $extension The uploaded file's original extension ("csv", "xlsx", "xls") —
     *   needed because $filePath is normally PHP's tmp upload path (e.g. "/tmp/phpXXXXXX"),
     *   which carries no extension of its own to detect the format from.
     * @return array{ok: bool, rows?: array, error?: string}
     * Each row: ['expense_date' => 'Y-m-d', 'amount' => float, 'description' => ?string,
     *            'category_id' => ?int, 'category_name' => ?string,
     *            'is_duplicate' => bool, 'duplicate_of' => ?array]
     */
    public static function parse(string $filePath, int $monthId, string $extension = 'csv'): array
    {
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
            $categoryId = $description !== '' ? Category::guessFromText($description) : self::defaultOther($categories);
            $categoryName = self::categoryName($categories, $categoryId);

            $duplicate = ExpenseModel::findDuplicate($monthId, $date, $amount);

            $rows[] = [
                'expense_date'  => $date,
                'amount'        => round($amount, 3),
                'description'   => $description !== '' ? $description : null,
                'category_id'   => $categoryId,
                'category_name' => $categoryName,
                'is_duplicate'  => $duplicate !== null,
                'duplicate_of'  => $duplicate ? ['description' => $duplicate['description'], 'amount' => (float) $duplicate['amount']] : null,
            ];
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
}
