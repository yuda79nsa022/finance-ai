<?php /** @var array $year */ /** @var array $data */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0"><?= e($year['label']) ?></h3>
    <div class="text-muted">Year Summary — mirrors the workbook's "Year Summary" sheet</div>
  </div>
  <a href="<?= base_url('/reports/annual/' . $year['id'] . '/pdf') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-file-earmark-pdf"></i> Annual Report (PDF)
  </a>
</div>

<!-- KEY FIGURES -->
<div class="row g-3 mb-2">
  <div class="col-md-3 col-6"><div class="kpi-card kpi-blue"><h6>Total Salary</h6><div class="value"><?= fmt_kd($data['keyFigures']['total_salary']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="kpi-card kpi-red"><h6>Total Spent</h6><div class="value"><?= fmt_kd($data['keyFigures']['total_spent']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="kpi-card kpi-green"><h6>Left Over (Year)</h6><div class="value"><?= fmt_kd($data['keyFigures']['total_left_over']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="kpi-card kpi-purple"><h6>Savings Rate</h6><div class="value"><?= fmt_pct($data['savingsDebt']['savings_rate']) ?></div></div></div>
</div>
<div class="row g-3 mb-2">
  <div class="col-md-3 col-6"><div class="kpi-card kpi-orange"><h6>Avg Monthly Spend</h6><div class="value"><?= fmt_kd($data['keyFigures']['average_monthly_spend']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="kpi-card kpi-orange"><h6>Avg Monthly Left Over</h6><div class="value"><?= fmt_kd($data['keyFigures']['average_monthly_left_over']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="kpi-card kpi-red"><h6>Loan Still Owed</h6><div class="value"><?= fmt_kd($data['savingsDebt']['loan_still_owed']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="kpi-card kpi-green"><h6>Months Carrying Debt</h6><div class="value"><?= (int) $data['savingsDebt']['months_carrying_debt'] ?> / 12</div></div></div>
</div>
<div class="row g-3 mb-4 text-muted small">
  <div class="col-md-4">Highest spending month: <strong><?= e($data['keyFigures']['highest_spending_month'] ?? '—') ?></strong></div>
  <div class="col-md-4">Lowest spending month: <strong><?= e($data['keyFigures']['lowest_spending_month'] ?? '—') ?></strong></div>
  <div class="col-md-4">Debt-free from: <strong><?= e($data['savingsDebt']['debt_free_from']) ?></strong></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3">
      <div class="section-title mt-0">Salary vs Spend by Month</div>
      <canvas id="chartSalarySpend" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3">
      <div class="section-title mt-0">Spending by Category (Full Year)</div>
      <canvas id="chartCategory" height="110"></canvas>
    </div>
  </div>
</div>

<div class="card p-3 mt-3">
  <div class="section-title mt-0">Monthly Breakdown</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr>
        <th>Month</th><th class="text-end">Salary</th><th class="text-end">Fixed Costs</th>
        <th class="text-end">Budget for Variable</th><th class="text-end">Variable Spent</th>
        <th class="text-end">Remaining</th><th class="text-end">% of Salary Spent</th>
        <th class="text-end">Loan Paid</th><th class="text-end">Loan Balance End</th>
        <th class="text-end">Savings + Travel</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($data['monthRows'] as $row): ?>
        <tr>
          <td><a href="<?= base_url('/month/' . $row['month_id']) ?>"><?= e($row['label']) ?></a></td>
          <td class="text-end"><?= fmt_kd($row['salary']) ?></td>
          <td class="text-end"><?= fmt_kd($row['fixed_costs']) ?></td>
          <td class="text-end"><?= fmt_kd($row['budget_for_variable']) ?></td>
          <td class="text-end"><?= fmt_kd($row['variable_spent']) ?></td>
          <td class="text-end <?= $row['remaining'] < 0 ? 'amount-neg' : 'amount-pos' ?>"><?= fmt_kd($row['remaining']) ?></td>
          <td class="text-end"><?= fmt_pct($row['percent_salary_spent']) ?></td>
          <td class="text-end"><?= fmt_kd($row['loan_paid']) ?></td>
          <td class="text-end"><?= fmt_kd($row['loan_balance_end']) ?></td>
          <td class="text-end"><?= fmt_kd($row['savings_plus_travel']) ?></td>
          <td><a href="<?= base_url('/month/' . $row['month_id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-bold">
        <tr>
          <td>Total / Average</td>
          <td class="text-end"><?= fmt_kd($data['totals']['salary']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['fixed_costs']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['budget_for_variable']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['variable_spent']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['remaining']) ?></td>
          <td class="text-end"><?= fmt_pct($data['totals']['percent_salary_spent']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['loan_paid']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['loan_balance_end']) ?></td>
          <td class="text-end"><?= fmt_kd($data['totals']['savings_plus_travel']) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card p-3 mt-3">
  <div class="section-title mt-0">Spending by Category — Full Year</div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Category</th><th class="text-end">Year Total</th><th class="text-end">% of Total</th></tr></thead>
      <tbody>
      <?php foreach ($data['categorySpend']['rows'] as $row): ?>
        <tr><td><?= e($row['category']) ?></td><td class="text-end"><?= fmt_kd($row['total']) ?></td><td class="text-end"><?= fmt_pct($row['percent']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-bold">
        <tr><td>Total</td><td class="text-end"><?= fmt_kd($data['categorySpend']['grand_total']) ?></td><td class="text-end">100.0%</td></tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
const months = <?= json_encode(array_column($data['monthRows'], 'label')) ?>;
const salary = <?= json_encode(array_map(fn($r) => round($r['salary'],3), $data['monthRows'])) ?>;
const spend  = <?= json_encode(array_map(fn($r) => round($r['fixed_costs'] + $r['variable_spent'],3), $data['monthRows'])) ?>;

new Chart(document.getElementById('chartSalarySpend'), {
  type: 'bar',
  data: { labels: months, datasets: [
    { label: 'Salary', data: salary, backgroundColor: '#0d6efd' },
    { label: 'Total Spend', data: spend, backgroundColor: '#dc3545' }
  ]},
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

const catLabels = <?= json_encode(array_column($data['categorySpend']['rows'], 'category')) ?>;
const catTotals = <?= json_encode(array_map(fn($r) => round($r['total'],3), $data['categorySpend']['rows'])) ?>;
new Chart(document.getElementById('chartCategory'), {
  type: 'doughnut',
  data: { labels: catLabels, datasets: [{ data: catTotals,
    backgroundColor: ['#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#20c997','#e83e8c','#6c757d','#ffc107','#0dcaf0','#adb5bd','#795548','#3b82f6','#22c55e'] }] },
  options: { responsive: true, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } } } }
});
</script>
