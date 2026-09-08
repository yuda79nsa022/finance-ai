<?php
/** @var array $month */ /** @var array $income */ /** @var array $fixedCosts */ /** @var array $expenses */
/** @var array $loanLedger */ /** @var array $loanTotals */ /** @var array $summary */ /** @var array $catSummary */
/** @var array $categories */ /** @var array $paymentMethods */ /** @var bool $printMode */ /** @var ?string $error */
$locked = (bool) $month['is_locked'];
?>
<?php if (!empty($error)): ?>
  <div class="alert alert-warning no-print"><?= e($error) ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-start mb-3 no-print flex-wrap gap-2">
  <div>
    <h3 class="mb-0">Expense Tracker — <?= e($month['label']) ?></h3>
    <?php if ($locked): ?><span class="badge text-bg-secondary"><i class="bi bi-lock-fill"></i> Locked</span><?php endif; ?>
  </div>
  <div class="btn-group">
    <a href="<?= base_url('/wizard/start?month_id=' . $month['id']) ?>" class="btn btn-primary"><i class="bi bi-chat-dots"></i> Guided Entry</a>
    <a href="<?= base_url('/month/' . $month['id'] . '?print=1') ?>" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Print</a>
    <a href="<?= base_url('/reports/month/' . $month['id'] . '/pdf') ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a href="<?= base_url('/reports/month/' . $month['id'] . '/csv') ?>" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> CSV</a>
    <form method="post" action="<?= base_url('/month/' . $month['id'] . '/duplicate') ?>" class="d-inline"><?= csrf_field() ?>
      <button class="btn btn-outline-secondary"><i class="bi bi-files"></i> Duplicate to Next Month</button>
    </form>
    <?php if ($locked): ?>
      <form method="post" action="<?= base_url('/month/' . $month['id'] . '/unlock') ?>" class="d-inline"><?= csrf_field() ?>
        <button class="btn btn-outline-warning"><i class="bi bi-unlock"></i> Unlock</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= base_url('/month/' . $month['id'] . '/lock') ?>" class="d-inline"><?= csrf_field() ?>
        <button class="btn btn-outline-warning"><i class="bi bi-lock"></i> Lock</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <!-- LEFT COLUMN: Income, Fixed Costs, Loan Tracker, Variable Expenses -->
  <div class="col-lg-8">

    <!-- 1. INCOME -->
    <div class="card p-3">
      <div class="section-title mt-0">1. Income</div>
      <table class="table table-sm">
        <thead><tr><th>Source</th><th class="text-end">Amount</th><th>Notes</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($income as $row): ?>
          <tr>
            <td><?= e($row['source']) ?></td>
            <td class="text-end"><?= fmt_kd($row['amount']) ?></td>
            <td><?= e($row['notes'] ?? '') ?></td>
            <td class="no-print">
              <?php if (!$locked): ?>
              <button type="button" class="btn btn-sm btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#editIncome<?= $row['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" action="<?= base_url('/month/' . $month['id'] . '/income/' . $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Remove this income line?')"><?= csrf_field() ?>
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
              </form>

              <div class="modal fade" id="editIncome<?= $row['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post" action="<?= base_url('/month/' . $month['id'] . '/income/' . $row['id'] . '/update') ?>" class="modal-content"><?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Edit Income</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-2"><label class="form-label">Source</label><input class="form-control" name="source" value="<?= e($row['source']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Amount</label><input class="form-control" name="amount" type="number" step="0.001" value="<?= e((string) $row['amount']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes" value="<?= e($row['notes'] ?? '') ?>"></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold"><tr><td>Total Salary</td><td class="text-end"><?= fmt_kd($summary['salary']) ?></td><td colspan="2"></td></tr></tfoot>
      </table>
      <?php if (!$locked): ?>
      <form method="post" action="<?= base_url('/month/' . $month['id'] . '/income') ?>" class="row g-2 no-print"><?= csrf_field() ?>
        <div class="col-4"><input class="form-control form-control-sm" name="source" placeholder="Source" required></div>
        <div class="col-3"><input class="form-control form-control-sm" name="amount" type="number" step="0.001" placeholder="Amount" required></div>
        <div class="col-3"><input class="form-control form-control-sm" name="notes" placeholder="Notes"></div>
        <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
      </form>
      <?php endif; ?>
    </div>

    <!-- 2. FIXED MONTHLY COSTS -->
    <div class="card p-3 mt-3">
      <div class="section-title mt-0">2. Fixed Monthly Costs</div>
      <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Item</th><th>Category</th><th class="text-end">Amount</th><th>Due Day</th><th>Payment</th><th>Notes</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($fixedCosts as $row): ?>
          <tr>
            <td><?= e($row['item']) ?></td>
            <td><?= e($row['category_name']) ?></td>
            <td class="text-end"><?= fmt_kd($row['amount']) ?></td>
            <td><?= e((string)($row['due_day'] ?? '')) ?></td>
            <td><?= e($row['payment_method_name'] ?? '') ?></td>
            <td><?= e($row['notes'] ?? '') ?></td>
            <td class="no-print">
              <?php if (!$locked): ?>
              <button type="button" class="btn btn-sm btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#editFixedCost<?= $row['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" action="<?= base_url('/month/' . $month['id'] . '/fixed-cost/' . $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Remove this fixed cost?')"><?= csrf_field() ?>
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
              </form>

              <div class="modal fade" id="editFixedCost<?= $row['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post" action="<?= base_url('/month/' . $month['id'] . '/fixed-cost/' . $row['id'] . '/update') ?>" class="modal-content"><?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Edit Fixed Cost</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-2"><label class="form-label">Item</label><input class="form-control" name="item" value="<?= e($row['item']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Category</label>
                        <select class="form-select fc-category-select" name="category_id" data-loan-ids="<?= e(json_encode(array_map(fn($lc) => (int) $lc['id'], $loanCategories))) ?>" required>
                          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $c['id'] === (int) $row['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-2"><label class="form-label">Amount</label><input class="form-control" name="amount" type="number" step="0.001" value="<?= e((string) $row['amount']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Due Day</label><input class="form-control" name="due_day" type="number" min="1" max="31" value="<?= e((string)($row['due_day'] ?? '')) ?>"></div>
                      <div class="mb-2"><label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method_id">
                          <option value="">Payment...</option>
                          <?php foreach ($paymentMethods as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $p['id'] === (int) ($row['payment_method_id'] ?? 0) ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-2 fc-opening-balance-wrap" style="display:none">
                        <label class="form-label">Opening Balance</label>
                        <input class="form-control" name="opening_balance" type="number" step="0.001" value="<?= e($row['lender_initial_balance'] !== null ? (string) $row['lender_initial_balance'] : '') ?>">
                        <div class="form-text">How much is still owed on this loan as of its first tracked month. Only shown for loan-type categories.</div>
                      </div>
                      <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes" value="<?= e($row['notes'] ?? '') ?>"></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold">
          <tr><td colspan="2">Total Fixed / Month</td><td class="text-end"><?= fmt_kd($summary['fixed_total']) ?></td><td colspan="4"></td></tr>
          <tr class="text-muted"><td colspan="2">Total Fixed / Year</td><td class="text-end"><?= fmt_kd($summary['fixed_total_year']) ?></td><td colspan="4"></td></tr>
        </tfoot>
      </table>
      </div>
      <?php if (!$locked): ?>
      <form method="post" action="<?= base_url('/month/' . $month['id'] . '/fixed-cost') ?>" class="row g-2 no-print"><?= csrf_field() ?>
        <div class="col-md-3"><input class="form-control form-control-sm" name="item" placeholder="Item" required></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm fc-category-select" name="category_id" data-loan-ids="<?= e(json_encode(array_map(fn($lc) => (int) $lc['id'], $loanCategories))) ?>" required>
            <option value="">Category...</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="amount" type="number" step="0.001" placeholder="Amount" required></div>
        <div class="col-md-1"><input class="form-control form-control-sm" name="due_day" type="number" min="1" max="31" placeholder="Day"></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="payment_method_id">
            <option value="">Payment...</option>
            <?php foreach ($paymentMethods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 fc-opening-balance-wrap" style="display:none">
          <input class="form-control form-control-sm" name="opening_balance" type="number" step="0.001" placeholder="Opening Balance">
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
      </form>
      <?php endif; ?>
    </div>

    <!-- 5. LOAN TRACKER -->
    <?php
      // A lender is "paid off" once this month's remaining balance hits zero —
      // but only worth flagging if there was ever a real balance to begin
      // with (lender_initial_balance > 0), so a lender added with nothing
      // owed doesn't trigger the prompt.
      $paidOffLoans = array_filter($loanLedger, fn($row) =>
          (float) $row['remaining_balance'] < 0.0005 && (float) $row['lender_initial_balance'] > 0.0005
      );
      // A loan auto-detected from a Fixed Cost row (rather than added
      // through this section's own "Add" form) starts with an opening
      // balance of 0 — there's no way to infer the true amount still owed
      // from a payment row alone. Flag it so it doesn't sit there silently
      // showing 0 KD owed for a loan that's clearly still being paid.
      $needsOpeningBalance = array_filter($loanLedger, fn($row) =>
          (float) $row['lender_initial_balance'] < 0.0005 && (float) $row['installment_amount'] > 0.0005
      );
    ?>
    <div class="card p-3 mt-3">
      <div class="section-title mt-0">5. Loan Tracker</div>

      <?php foreach ($needsOpeningBalance as $row): ?>
        <div class="alert alert-warning alert-dismissible fade show no-print" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <strong><?= e($row['lender_name']) ?></strong> was picked up from Fixed Costs but has no opening balance set —
          it currently shows 0 KD owed. Click the pencil icon on its row below and enter how much is actually still owed.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>

      <?php foreach ($paidOffLoans as $row): ?>
        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
          <i class="bi bi-check-circle-fill"></i>
          <strong><?= e($row['lender_name']) ?>'s loan has been paid off!</strong>
          Its balance is fully paid off. Do you want to remove it from the Loan Tracker?
          <?php if (!$locked): ?>
            <form method="post" action="<?= base_url('/month/' . $month['id'] . '/loan/' . $row['lender_id'] . '/delete') ?>" class="d-inline ms-2" onsubmit="return confirm('Remove ' + <?= json_encode($row['lender_name']) ?> + ' from the Loan Tracker? Past months keep their history — this only stops it appearing going forward.')"><?= csrf_field() ?>
              <button class="btn btn-sm btn-success">Yes, remove it</button>
            </form>
          <?php endif; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>

      <table class="table table-sm">
        <thead><tr><th>Lender</th><th>Who</th><th>Loan Type</th><th class="text-end">Opening Balance</th><th class="text-end">Paid This Month</th><th class="text-end">Remaining</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($loanLedger as $row): ?>
          <tr>
            <td>
              <?= e($row['lender_name']) ?>
              <?php if ((float) $row['remaining_balance'] < 0.0005 && (float) $row['lender_initial_balance'] > 0.0005): ?>
                <span class="badge text-bg-success ms-1">Paid Off</span>
              <?php endif; ?>
            </td>
            <td><span class="badge text-bg-light"><?= e(ucfirst($row['lender_type'])) ?></span></td>
            <td><span class="badge text-bg-info-subtle"><?= e($row['lender_category_name'] ?? ($row['lender_type'] === 'bank' ? 'Bank Loan' : 'Personal Loan')) ?></span></td>
            <td class="text-end"><?= fmt_kd($row['opening_balance']) ?></td>
            <td class="text-end"><?= fmt_kd($row['paid_this_month']) ?></td>
            <td class="text-end"><?= fmt_kd($row['remaining_balance']) ?></td>
            <td class="no-print">
              <?php if (!$locked): ?>
              <button type="button" class="btn btn-sm btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#editLoan<?= $row['lender_id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" action="<?= base_url('/month/' . $month['id'] . '/loan/' . $row['lender_id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Remove ' + <?= json_encode($row['lender_name']) ?> + ' from the Loan Tracker starting this month? Past months keep their history.')"><?= csrf_field() ?>
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
              </form>

              <div class="modal fade" id="editLoan<?= $row['lender_id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post" action="<?= base_url('/month/' . $month['id'] . '/loan/' . $row['lender_id'] . '/update') ?>" class="modal-content"><?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Edit Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-2"><label class="form-label">Lender Name</label><input class="form-control" name="name" value="<?= e($row['lender_name']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Who</label>
                        <select class="form-select" name="type">
                          <option value="person" <?= $row['lender_type'] === 'person' ? 'selected' : '' ?>>Person</option>
                          <option value="bank" <?= $row['lender_type'] === 'bank' ? 'selected' : '' ?>>Bank</option>
                        </select>
                      </div>
                      <div class="mb-2"><label class="form-label">Loan Type</label>
                        <select class="form-select" name="category_id">
                          <?php foreach ($loanCategories as $lc): ?><option value="<?= $lc['id'] ?>" <?= (int) $lc['id'] === (int) ($row['lender_category_id'] ?? 0) ? 'selected' : '' ?>><?= e($lc['name']) ?></option><?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Opening Balance</label>
                        <input class="form-control" name="initial_balance" type="number" step="0.001" value="<?= e((string) $row['lender_initial_balance']) ?>" required>
                        <div class="form-text">Only changes this lender's balance for their very first tracked month. Every later month's opening balance still comes from the previous month's remaining balance.</div>
                      </div>
                      <div class="mb-2"><label class="form-label">Payment This Month (<?= e($month['label']) ?>)</label><input class="form-control" name="payment" type="number" step="0.001" value="<?= e((string) $row['installment_amount']) ?>"></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold">
          <tr><td colspan="3">Total Owed</td><td class="text-end"><?= fmt_kd($loanTotals['opening']) ?></td><td class="text-end"><?= fmt_kd($loanTotals['paid']) ?></td><td class="text-end"><?= fmt_kd($loanTotals['remaining']) ?></td><td class="no-print"></td></tr>
        </tfoot>
      </table>

      <?php if (!$locked): ?>
      <form method="post" action="<?= base_url('/month/' . $month['id'] . '/loan') ?>" class="row g-2 no-print"><?= csrf_field() ?>
        <div class="col-md-2"><input class="form-control form-control-sm" name="name" placeholder="Lender name" required></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="category_id">
            <?php foreach ($loanCategories as $lc): ?><option value="<?= $lc['id'] ?>"><?= e($lc['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="type">
            <option value="person">Person</option>
            <option value="bank">Bank</option>
          </select>
        </div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="initial_balance" type="number" step="0.001" placeholder="Opening balance owed" required></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="payment" type="number" step="0.001" placeholder="Payment this month"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
      </form>
      <?php endif; ?>

      <p class="text-muted small mb-0 mt-2">Paid = installment amount from Fixed Costs (matched by lender name and loan category), capped at the opening balance. Next month's opening balance = this month's remaining balance.</p>
    </div>

    <!-- 4. VARIABLE EXPENSES LOG -->
    <div class="card p-3 mt-3">
      <div class="section-title mt-0">4. Variable Expenses Log</div>
      <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Date</th><th>Month</th><th class="text-end">Amount</th><th>Category</th><th>Description</th><th>Payment</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($expenses as $row): ?>
          <tr>
            <td><?= e($row['expense_date']) ?></td>
            <td><?= e($row['month_label']) ?></td>
            <td class="text-end"><?= fmt_kd($row['amount']) ?></td>
            <td><?= e($row['category_name']) ?></td>
            <td><?= e($row['description'] ?? '') ?></td>
            <td><?= e($row['payment_method_name'] ?? '') ?></td>
            <td class="no-print">
              <?php if (!empty($row['receipt_path'])): ?>
              <a href="<?= base_url('/month/' . $month['id'] . '/expense/' . $row['id'] . '/receipt') ?>" target="_blank" class="btn btn-sm btn-link p-0 me-2" title="View receipt"><i class="bi bi-image"></i></a>
              <?php endif; ?>
              <?php if (!$locked): ?>
              <button type="button" class="btn btn-sm btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#editExpense<?= $row['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" action="<?= base_url('/month/' . $month['id'] . '/expense/' . $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Remove this expense?')"><?= csrf_field() ?>
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
              </form>

              <div class="modal fade" id="editExpense<?= $row['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post" action="<?= base_url('/month/' . $month['id'] . '/expense/' . $row['id'] . '/update') ?>" class="modal-content"><?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Edit Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-2"><label class="form-label">Date</label><input class="form-control" name="expense_date" type="date" value="<?= e($row['expense_date']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Amount</label><input class="form-control" name="amount" type="number" step="0.001" value="<?= e((string) $row['amount']) ?>" required></div>
                      <div class="mb-2"><label class="form-label">Category</label>
                        <select class="form-select" name="category_id" required>
                          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $c['id'] === (int) $row['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($row['description'] ?? '') ?>"></div>
                      <div class="mb-2"><label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method_id">
                          <option value="">Payment...</option>
                          <?php foreach ($paymentMethods as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $p['id'] === (int) ($row['payment_method_id'] ?? 0) ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php if (!$locked): ?>
      <div class="d-flex align-items-center gap-2 mb-2 no-print">
        <label class="btn btn-sm btn-outline-primary mb-0">
          <i class="bi bi-camera"></i> Scan Receipt
          <input type="file" id="receiptInput" accept="image/*" capture="environment" class="d-none">
        </label>
        <span id="receiptStatus" class="text-muted small"></span>
      </div>
      <div class="d-flex align-items-center gap-2 mb-2 no-print">
        <label class="btn btn-sm btn-outline-secondary mb-0">
          <i class="bi bi-file-earmark-spreadsheet"></i> Import Bank Statement
          <input type="file" id="statementInput" accept=".csv,.xlsx,.xls,.pdf" class="d-none">
        </label>
        <span id="statementStatus" class="text-muted small"></span>
      </div>
      <form method="post" action="<?= base_url('/month/' . $month['id'] . '/expense') ?>" class="row g-2 no-print" id="addExpenseForm"><?= csrf_field() ?>
        <input type="hidden" name="receipt_path">
        <div class="col-md-2"><input class="form-control form-control-sm" name="expense_date" type="date" required></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="amount" type="number" step="0.001" placeholder="Amount" required></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="category_id" required>
            <option value="">Category...</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="description" placeholder="Description"></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="payment_method_id">
            <option value="">Payment...</option>
            <?php foreach ($paymentMethods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Statement import review — populated by JS after a file is parsed; nothing is saved until "Import Selected" is clicked. -->
  <div class="modal fade no-print" id="statementImportModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Review Imported Transactions</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-2">Uncheck anything that shouldn't be added — rows flagged <span class="badge text-bg-warning">possible duplicate</span> already match an existing entry this month and start unchecked. Categories are a best guess; change any that look wrong.</p>
          <table class="table table-sm align-middle">
            <thead><tr><th></th><th>Date</th><th class="text-end">Amount</th><th>Description</th><th>Category</th></tr></thead>
            <tbody id="statementImportRows"></tbody>
          </table>
        </div>
        <div class="modal-footer">
          <span id="statementImportResult" class="text-muted small me-auto"></span>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="statementImportConfirm">Import Selected</button>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT COLUMN: Category Summary + Monthly Summary -->
  <div class="col-lg-4">
    <div class="card p-3">
      <div class="section-title mt-0">Summary by Category</div>
      <table class="table table-sm">
        <thead><tr><th>Category</th><th class="text-end">Fixed</th><th class="text-end">Variable</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($catSummary['rows'] as $row): ?>
          <tr><td><?= e($row['category_name']) ?></td><td class="text-end"><?= fmt_kd($row['fixed']) ?></td><td class="text-end"><?= fmt_kd($row['variable']) ?></td><td class="text-end"><?= fmt_kd($row['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold">
          <tr><td>Total</td><td class="text-end"><?= fmt_kd($catSummary['totals']['fixed']) ?></td><td class="text-end"><?= fmt_kd($catSummary['totals']['variable']) ?></td><td class="text-end"><?= fmt_kd($catSummary['totals']['total']) ?></td></tr>
        </tfoot>
      </table>
    </div>

    <div class="card p-3 mt-3">
      <div class="section-title mt-0">3. What's Left for This Month</div>
      <table class="table table-sm mb-0">
        <tbody>
          <tr><td>Salary less Fixed Costs = Budget for Variable</td><td class="text-end"><?= fmt_kd($summary['budget_for_variable']) ?></td></tr>
          <tr><td>Less: Variable Expenses Logged</td><td class="text-end"><?= fmt_kd($summary['less_variable']) ?></td></tr>
          <tr class="fw-bold table-light"><td>Remaining to Spend</td><td class="text-end <?= $summary['remaining_to_spend'] < 0 ? 'amount-neg' : 'amount-pos' ?>"><?= fmt_kd($summary['remaining_to_spend']) ?></td></tr>
          <tr><td>% of Salary Spent</td><td class="text-end"><?= fmt_pct($summary['percent_salary_spent']) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

<script>
// Shows/hides the "Opening Balance" field on the Fixed Cost Add form and
// every Edit Fixed Cost modal, depending on whether the selected category
// is a loan-type category (Personal Loan, Bank Loan, or any custom loan
// category) — set on each <select class="fc-category-select"> via its
// data-loan-ids attribute (see MonthController::show()/$loanCategories).
(function () {
  function toggle(select) {
    var loanIds = JSON.parse(select.getAttribute('data-loan-ids') || '[]');
    var wrap = select.closest('.modal-body, form').querySelector('.fc-opening-balance-wrap');
    if (!wrap) { return; }
    var isLoan = loanIds.indexOf(parseInt(select.value, 10)) !== -1;
    wrap.style.display = isLoan ? '' : 'none';
  }
  document.querySelectorAll('.fc-category-select').forEach(function (select) {
    toggle(select);
    select.addEventListener('change', function () { toggle(select); });
  });
})();

// "Scan Receipt": uploads the chosen/captured photo, asks the server to read
// it (Core/ReceiptScanner, via the configured AI provider), and pre-fills the
// Add Expense form below with what it found. Nothing is saved automatically —
// the user still reviews the fields and clicks Add, same as typing them in by
// hand, so a misread amount/category is easy to catch or correct first.
(function () {
  var input = document.getElementById('receiptInput');
  if (!input) { return; }
  var status = document.getElementById('receiptStatus');
  var form = document.getElementById('addExpenseForm');
  var scanUrl = '<?= base_url('/month/' . $month['id'] . '/expense/scan') ?>';
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  input.addEventListener('change', function () {
    var file = input.files[0];
    if (!file) { return; }

    status.textContent = 'Reading receipt…';
    var body = new FormData();
    body.append('receipt', file);
    body.append('_token', csrfToken);

    fetch(scanUrl, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.receipt_path) {
          form.querySelector('[name="receipt_path"]').value = data.receipt_path;
        }
        if (!data.ok) {
          status.textContent = (data.error || 'Could not read that receipt — enter it manually below.');
          return;
        }
        if (data.expense_date) { form.querySelector('[name="expense_date"]').value = data.expense_date; }
        if (data.amount) { form.querySelector('[name="amount"]').value = data.amount; }
        if (data.category_id) { form.querySelector('[name="category_id"]').value = data.category_id; }
        if (data.description) { form.querySelector('[name="description"]').value = data.description; }
        status.textContent = 'Got it — review the fields below, then click Add.';
        form.querySelector('[name="amount"]').focus();
      })
      .catch(function () {
        status.textContent = 'Something went wrong reading that receipt — enter it manually below.';
      })
      .finally(function () {
        input.value = '';
      });
  });
})();

// "Import Bank Statement": uploads a CSV/Excel export or a PDF statement
// (Core/StatementImporter), shows every parsed row in an editable review
// table — nothing is saved until
// "Import Selected" is clicked. Rows flagged as a likely duplicate (same date
// + amount as something already logged this month) start unchecked so a
// re-imported statement doesn't silently double-count spending.
(function () {
  var input = document.getElementById('statementInput');
  if (!input) { return; }
  var status = document.getElementById('statementStatus');
  var importUrl = '<?= base_url('/month/' . $month['id'] . '/expense/import') ?>';
  var confirmUrl = '<?= base_url('/month/' . $month['id'] . '/expense/import/confirm') ?>';
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  var categories = <?= json_encode(array_map(fn($c) => ['id' => (int) $c['id'], 'name' => $c['name']], $categories)) ?>;
  var modalEl = document.getElementById('statementImportModal');
  var modal = null; // created lazily on first successful parse (see below) — never at load time, so a
                     // Bootstrap load failure can't silently stop the file-input listener from ever being attached
  var rowsBody = document.getElementById('statementImportRows');
  var resultEl = document.getElementById('statementImportResult');
  var confirmBtn = document.getElementById('statementImportConfirm');

  function categorySelect(row) {
    var select = document.createElement('select');
    select.className = 'form-select form-select-sm';
    categories.forEach(function (c) {
      var opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      if (row.category_id && c.id === row.category_id) { opt.selected = true; }
      select.appendChild(opt);
    });
    return select;
  }

  function renderRows(rows) {
    rowsBody.innerHTML = '';
    rows.forEach(function (row) {
      var tr = document.createElement('tr');

      var checkTd = document.createElement('td');
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'form-check-input';
      checkbox.checked = !row.is_duplicate;
      checkTd.appendChild(checkbox);
      tr.appendChild(checkTd);

      var dateTd = document.createElement('td');
      dateTd.textContent = row.expense_date;
      dateTd.className = 'text-nowrap';
      tr.appendChild(dateTd);

      var amountTd = document.createElement('td');
      amountTd.textContent = row.amount.toFixed(3);
      amountTd.className = 'text-end text-nowrap';
      tr.appendChild(amountTd);

      var descTd = document.createElement('td');
      descTd.textContent = row.description || '';
      if (row.is_duplicate) {
        var badge = document.createElement('span');
        badge.className = 'badge text-bg-warning ms-1';
        badge.textContent = 'possible duplicate';
        badge.title = row.duplicate_of ? ('Matches: ' + (row.duplicate_of.description || '(no description)') + ' — ' + row.duplicate_of.amount.toFixed(3)) : '';
        descTd.appendChild(document.createElement('br'));
        descTd.appendChild(badge);
      }
      tr.appendChild(descTd);

      var catTd = document.createElement('td');
      var select = categorySelect(row);
      catTd.appendChild(select);
      tr.appendChild(catTd);

      tr._rowData = { checkbox: checkbox, dateText: row.expense_date, amount: row.amount, descriptionText: row.description, categorySelect: select };
      rowsBody.appendChild(tr);
    });
  }

  input.addEventListener('change', function () {
    var file = input.files[0];
    if (!file) { return; }

    status.textContent = 'Reading statement…';
    var body = new FormData();
    body.append('statement', file);
    body.append('_token', csrfToken);

    fetch(importUrl, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        status.textContent = '';
        if (!data.ok) {
          status.textContent = data.error || 'Could not read that file.';
          return;
        }
        resultEl.textContent = data.rows.length + ' transaction' + (data.rows.length === 1 ? '' : 's') + ' found.';
        renderRows(data.rows);
        if (typeof bootstrap === 'undefined') {
          status.textContent = 'Read ' + data.rows.length + ' transaction(s), but the popup library failed to load — try refreshing the page.';
          return;
        }
        if (!modal) { modal = new bootstrap.Modal(modalEl); }
        modal.show();
      })
      .catch(function () {
        status.textContent = 'Something went wrong reading that file.';
      })
      .finally(function () {
        input.value = '';
      });
  });

  confirmBtn.addEventListener('click', function () {
    var selected = [];
    rowsBody.querySelectorAll('tr').forEach(function (tr) {
      var d = tr._rowData;
      if (d.checkbox.checked) {
        selected.push({
          expense_date: d.dateText,
          amount: d.amount,
          category_id: parseInt(d.categorySelect.value, 10),
          description: d.descriptionText,
        });
      }
    });
    if (selected.length === 0) {
      resultEl.textContent = 'Nothing selected.';
      return;
    }

    confirmBtn.disabled = true;
    resultEl.textContent = 'Importing…';
    fetch(confirmUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ rows: selected }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          resultEl.textContent = data.error || 'Import failed.';
          confirmBtn.disabled = false;
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        resultEl.textContent = 'Something went wrong — try again.';
        confirmBtn.disabled = false;
      });
  });
})();
</script>
</div>
