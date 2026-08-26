<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\YearSummaryCalculator;
use App\Models\FinancialYear;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $year = FinancialYear::active($this->currentUserId());
        if (!$year) {
            $this->view('dashboard/no-year', []);
            return;
        }

        $data = YearSummaryCalculator::build($year['id']);
        $this->view('dashboard/index', ['year' => $year, 'data' => $data]);
    }
}
