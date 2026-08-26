<?php
/** @var array $year */ /** @var array $data */
if (!function_exists('fmt_kd')) { require dirname(__DIR__, 2) . '/Core/helpers.php'; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 10.5px; color: #222; }
  h1 { font-size: 18px; margin-bottom: 2px; }
  .muted { color: #777; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  th, td { padding: 4px 6px; border-bottom: 1px solid #ddd; text-align: left; }
  th { background: #f2f2f2; text-transform: uppercase; font-size: 8.5px; }
  .text-end { text-align: right; }
  .section { font-weight: bold; font-size: 13px; margin: 14px 0 6px; border-bottom: 2px solid #333; padding-bottom: 3px; }
</style>
</head>
<body>
  <h1>Annual Report — <?= e($year['label']) ?></h1>
  <div class="muted">Generated <?= date('Y-m-d H:i') ?></div>

  <div class="section">Monthly Breakdown</div>
  <table>
    <tr><th>Month</th><th class="text-end">Salary</th><th class="text-end">Fixed</th><th class="text-end">Variable</th><th class="text-end">Remaining</th><th class="text-end">Loan Paid</th><th class="text-end">Loan Balance</th></tr>
    <?php foreach ($data['monthRows'] as $r): ?>
      <tr>
        <td><?= e($r['label']) ?></td>
        <td class="text-end"><?= fmt_kd($r['salary']) ?></td>
        <td class="text-end"><?= fmt_kd($r['fixed_costs']) ?></td>
        <td class="text-end"><?= fmt_kd($r['variable_spent']) ?></td>
        <td class="text-end"><?= fmt_kd($r['remaining']) ?></td>
        <td class="text-end"><?= fmt_kd($r['loan_paid']) ?></td>
        <td class="text-end"><?= fmt_kd($r['loan_balance_end']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="section">Key Figures</div>
  <table>
    <tr><td>Total Salary</td><td class="text-end"><?= fmt_kd($data['keyFigures']['total_salary']) ?></td></tr>
    <tr><td>Total Spent</td><td class="text-end"><?= fmt_kd($data['keyFigures']['total_spent']) ?></td></tr>
    <tr><td>Total Left Over</td><td class="text-end"><?= fmt_kd($data['keyFigures']['total_left_over']) ?></td></tr>
    <tr><td>Average Monthly Spend</td><td class="text-end"><?= fmt_kd($data['keyFigures']['average_monthly_spend']) ?></td></tr>
    <tr><td>Highest Spending Month</td><td class="text-end"><?= e($data['keyFigures']['highest_spending_month'] ?? '—') ?></td></tr>
    <tr><td>Lowest Spending Month</td><td class="text-end"><?= e($data['keyFigures']['lowest_spending_month'] ?? '—') ?></td></tr>
  </table>

  <div class="section">Savings &amp; Debt</div>
  <table>
    <tr><td>Total Put Into Savings + Travel</td><td class="text-end"><?= fmt_kd($data['savingsDebt']['total_savings_travel']) ?></td></tr>
    <tr><td>Savings Rate</td><td class="text-end"><?= fmt_pct($data['savingsDebt']['savings_rate']) ?></td></tr>
    <tr><td>Loan Owed at Start</td><td class="text-end"><?= fmt_kd($data['savingsDebt']['loan_owed_at_start']) ?></td></tr>
    <tr><td>Total Loan Repaid</td><td class="text-end"><?= fmt_kd($data['savingsDebt']['total_loan_repaid']) ?></td></tr>
    <tr><td>Loan Still Owed</td><td class="text-end"><?= fmt_kd($data['savingsDebt']['loan_still_owed']) ?></td></tr>
    <tr><td>Debt-Free From</td><td class="text-end"><?= e($data['savingsDebt']['debt_free_from']) ?></td></tr>
  </table>

  <div class="section">Spending by Category</div>
  <table>
    <tr><th>Category</th><th class="text-end">Year Total</th><th class="text-end">% of Total</th></tr>
    <?php foreach ($data['categorySpend']['rows'] as $r): ?>
      <tr><td><?= e($r['category']) ?></td><td class="text-end"><?= fmt_kd($r['total']) ?></td><td class="text-end"><?= fmt_pct($r['percent']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
