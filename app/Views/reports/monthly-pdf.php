<?php
/** @var array $month */ /** @var array $summary */ /** @var array $catSummary */ /** @var array $fixedCosts */ /** @var array $expenses */
if (!function_exists('fmt_kd')) { require dirname(__DIR__, 2) . '/Core/helpers.php'; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; }
  h1 { font-size: 18px; margin-bottom: 2px; }
  .muted { color: #777; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  th, td { padding: 4px 6px; border-bottom: 1px solid #ddd; text-align: left; }
  th { background: #f2f2f2; text-transform: uppercase; font-size: 9px; }
  .text-end { text-align: right; }
  .section { font-weight: bold; font-size: 13px; margin: 14px 0 6px; border-bottom: 2px solid #333; padding-bottom: 3px; }
</style>
</head>
<body>
  <h1>Monthly Report — <?= e($month['label']) ?></h1>
  <div class="muted">Generated <?= date('Y-m-d H:i') ?></div>

  <div class="section">Summary</div>
  <table>
    <tr><td>Salary</td><td class="text-end"><?= fmt_kd($summary['salary']) ?></td></tr>
    <tr><td>Total Fixed Costs</td><td class="text-end"><?= fmt_kd($summary['fixed_total']) ?></td></tr>
    <tr><td>Total Variable Spent</td><td class="text-end"><?= fmt_kd($summary['variable_total']) ?></td></tr>
    <tr><td>Remaining to Spend</td><td class="text-end"><?= fmt_kd($summary['remaining_to_spend']) ?></td></tr>
    <tr><td>% of Salary Spent</td><td class="text-end"><?= fmt_pct($summary['percent_salary_spent']) ?></td></tr>
  </table>

  <div class="section">Fixed Costs</div>
  <table>
    <tr><th>Item</th><th>Category</th><th class="text-end">Amount</th><th>Due Day</th><th>Payment</th></tr>
    <?php foreach ($fixedCosts as $r): ?>
      <tr><td><?= e($r['item']) ?></td><td><?= e($r['category_name']) ?></td><td class="text-end"><?= fmt_kd($r['amount']) ?></td><td><?= e((string)($r['due_day'] ?? '')) ?></td><td><?= e($r['payment_method_name'] ?? '') ?></td></tr>
    <?php endforeach; ?>
  </table>

  <div class="section">Variable Expenses</div>
  <table>
    <tr><th>Date</th><th>Category</th><th class="text-end">Amount</th><th>Description</th></tr>
    <?php foreach ($expenses as $r): ?>
      <tr><td><?= e($r['expense_date']) ?></td><td><?= e($r['category_name']) ?></td><td class="text-end"><?= fmt_kd($r['amount']) ?></td><td><?= e($r['description'] ?? '') ?></td></tr>
    <?php endforeach; ?>
  </table>

  <div class="section">Summary by Category</div>
  <table>
    <tr><th>Category</th><th class="text-end">Fixed</th><th class="text-end">Variable</th><th class="text-end">Total</th></tr>
    <?php foreach ($catSummary['rows'] as $r): ?>
      <tr><td><?= e($r['category_name']) ?></td><td class="text-end"><?= fmt_kd($r['fixed']) ?></td><td class="text-end"><?= fmt_kd($r['variable']) ?></td><td class="text-end"><?= fmt_kd($r['total']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
