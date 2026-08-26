<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\MonthCalculator;
use App\Core\YearSummaryCalculator;
use App\Models\ExpenseModel;
use App\Models\FinancialYear;
use App\Models\FixedCostModel;
use App\Models\IncomeModel;
use App\Models\MonthModel;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    private function vendorReady(): bool
    {
        return file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php');
    }

    private function requireVendor(): void
    {
        if (!$this->vendorReady()) {
            http_response_code(500);
            echo 'Report libraries are not installed yet. Run <code>composer install</code> in the project root '
               . '(installs Dompdf and PhpSpreadsheet — see docs/INSTALL.md).';
            exit;
        }
        require dirname(__DIR__, 2) . '/vendor/autoload.php';
    }

    // ---- Monthly Report (PDF) --------------------------------------------
    public function monthlyPdf(string $monthId): void
    {
        $this->requireVendor();
        $month = $this->requireMonth((int) $monthId);
        $summary = MonthCalculator::summary($month['id']);
        $catSummary = MonthCalculator::categorySummary($month['id']);

        $html = $this->renderToString('reports/monthly-pdf', [
            'month' => $month,
            'summary' => $summary,
            'catSummary' => $catSummary,
            'fixedCosts' => FixedCostModel::forMonth($month['id']),
            'expenses'   => ExpenseModel::forMonth($month['id']),
        ]);

        $this->streamPdf($html, 'Monthly-Report-' . $month['label'] . '.pdf');
    }

    // ---- Annual Report (PDF) ----------------------------------------------
    public function annualPdf(string $yearId): void
    {
        $this->requireVendor();
        $year = $this->requireYear((int) $yearId);
        $data = YearSummaryCalculator::build($year['id']);

        $html = $this->renderToString('reports/annual-pdf', ['year' => $year, 'data' => $data]);
        $this->streamPdf($html, 'Annual-Report-' . $year['label'] . '.pdf');
    }

    // ---- Cash Flow / Expense / Income / Savings summaries (CSV) --------------
    public function monthCsv(string $monthId): void
    {
        $month = $this->requireMonth((int) $monthId);
        $rows = [['Type', 'Item', 'Category', 'Amount (KD)', 'Date/Due Day', 'Payment Method', 'Notes']];

        foreach (IncomeModel::forMonth($month['id']) as $r) {
            $rows[] = ['Income', $r['source'], '', $r['amount'], '', '', $r['notes']];
        }
        foreach (FixedCostModel::forMonth($month['id']) as $r) {
            $rows[] = ['Fixed Cost', $r['item'], $r['category_name'], $r['amount'], $r['due_day'], $r['payment_method_name'], $r['notes']];
        }
        foreach (ExpenseModel::forMonth($month['id']) as $r) {
            $rows[] = ['Variable Expense', $r['description'], $r['category_name'], $r['amount'], $r['expense_date'], $r['payment_method_name'], ''];
        }

        $this->streamCsv($rows, 'Cash-Flow-' . $month['label'] . '.csv');
    }

    public function annualXlsx(string $yearId): void
    {
        $this->requireVendor();
        $year = $this->requireYear((int) $yearId);
        $data = YearSummaryCalculator::build($year['id']);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Year Summary');

        $headers = ['Month', 'Salary', 'Fixed Costs', 'Budget for Variable', 'Variable Spent', 'Remaining', '% of Salary Spent', 'Loan Paid', 'Loan Balance End', 'Savings + Travel'];
        $sheet->fromArray($headers, null, 'A1');

        $r = 2;
        foreach ($data['monthRows'] as $row) {
            $sheet->fromArray([
                $row['label'], $row['salary'], $row['fixed_costs'], $row['budget_for_variable'],
                $row['variable_spent'], $row['remaining'], $row['percent_salary_spent'],
                $row['loan_paid'], $row['loan_balance_end'], $row['savings_plus_travel'],
            ], null, 'A' . $r);
            $r++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Annual-Summary-' . $year['label'] . '.xlsx"');
        $writer->save('php://output');
        exit;
    }

    // ---- helpers ------------------------------------------------------------

    /** Loads a month only if it belongs to the logged-in user — reports must never let one user pull another user's data by tampering the id in the URL. */
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

    /** Same ownership check as requireMonth(), for a financial year id. */
    private function requireYear(int $id): array
    {
        $year = FinancialYear::find($id, $this->currentUserId());
        if (!$year) {
            http_response_code(404);
            echo 'Financial year not found.';
            exit;
        }
        return $year;
    }

    private function renderToString(string $view, array $data): string
    {
        extract($data);
        ob_start();
        require dirname(__DIR__) . '/Views/' . $view . '.php';
        return ob_get_clean();
    }

    private function streamPdf(string $html, string $filename): void
    {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    private function streamCsv(array $rows, string $filename): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
