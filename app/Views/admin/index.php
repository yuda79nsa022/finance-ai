<?php /** @var array $categories */ /** @var array $paymentMethods */ /** @var array $lenders */ /** @var array $financialYears */ ?>
<h3 class="mb-3">Settings / Administration</h3>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<ul class="nav nav-tabs" id="adminTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#categories">Categories</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#payment-methods">Payment Methods</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#lenders">Lenders</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#financial-years">Financial Years</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#backup">Backup &amp; Restore</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#users">Users</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#ai-advisor"><i class="bi bi-stars"></i> AI Advisor</a></li>
</ul>

<div class="tab-content pt-3">

  <div class="tab-pane fade show active" id="categories">
    <div class="card p-3">
      <table class="table table-sm">
        <thead><tr><th>Name</th><th>Type</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td><?= e($c['name']) ?></td>
            <td><span class="badge text-bg-light"><?= e($c['type']) ?></span></td>
            <td><?= $c['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editCategory<?= $c['id'] ?>">Edit</button>
              <?php if ($c['is_active']): ?>
              <form method="post" action="<?= base_url('/admin/category/' . $c['id'] . '/deactivate') ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">Deactivate</button>
              </form>
              <?php else: ?>
              <form method="post" action="<?= base_url('/admin/category/' . $c['id'] . '/activate') ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-success">Activate</button>
              </form>
              <?php endif; ?>

              <div class="modal fade" id="editCategory<?= $c['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post" action="<?= base_url('/admin/category/' . $c['id'] . '/update') ?>" class="modal-content"><?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Edit Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($c['name']) ?>" required></div>
                      <div class="mb-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                          <option value="expense" <?= $c['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                          <option value="savings" <?= $c['type'] === 'savings' ? 'selected' : '' ?>>Savings</option>
                          <option value="loan" <?= $c['type'] === 'loan' ? 'selected' : '' ?>>Loan</option>
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
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="<?= base_url('/admin/category') ?>" class="row g-2 mt-2"><?= csrf_field() ?>
        <div class="col-md-5"><input class="form-control" name="name" placeholder="New category name" required></div>
        <div class="col-md-4">
          <select class="form-select" name="type">
            <option value="expense">Expense</option>
            <option value="savings">Savings</option>
            <option value="loan">Loan</option>
          </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Add Category</button></div>
      </form>
    </div>
  </div>

  <div class="tab-pane fade" id="payment-methods">
    <div class="card p-3">
      <table class="table table-sm">
        <thead><tr><th>Name</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($paymentMethods as $p): ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td>
              <form method="post" action="<?= base_url('/admin/payment-method/' . $p['id'] . '/delete') ?>" onsubmit="return confirm('Delete this payment method?')"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="<?= base_url('/admin/payment-method') ?>" class="row g-2 mt-2"><?= csrf_field() ?>
        <div class="col-md-9"><input class="form-control" name="name" placeholder="New payment method" required></div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Add</button></div>
      </form>
    </div>
  </div>

  <div class="tab-pane fade" id="lenders">
    <div class="card p-3">
      <table class="table table-sm">
        <thead><tr><th>Name</th><th>Type</th><th class="text-end">Initial Balance</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lenders as $l): ?>
          <tr>
            <td><?= e($l['name']) ?></td>
            <td><span class="badge text-bg-light"><?= e(ucfirst($l['type'])) ?></span></td>
            <td class="text-end"><?= fmt_kd($l['initial_balance']) ?></td>
            <td><?= $l['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
              <?php if ($l['is_active']): ?>
              <form method="post" action="<?= base_url('/admin/lender/' . $l['id'] . '/deactivate') ?>" onsubmit="return confirm('Remove ' + <?= json_encode($l['name']) ?> + ' from the Loan Tracker going forward? Past months keep their history.')"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
              <?php else: ?>
              <form method="post" action="<?= base_url('/admin/lender/' . $l['id'] . '/activate') ?>"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-success">Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="<?= base_url('/admin/lender') ?>" class="row g-2 mt-2"><?= csrf_field() ?>
        <div class="col-md-4"><input class="form-control" name="name" placeholder="New lender name" required></div>
        <div class="col-md-3">
          <select class="form-select" name="type">
            <option value="person">Person</option>
            <option value="bank">Bank</option>
          </select>
        </div>
        <div class="col-md-3"><input class="form-control" name="initial_balance" type="number" step="0.001" placeholder="Amount owed" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
      </form>
    </div>
  </div>

  <div class="tab-pane fade" id="financial-years">
    <div class="card p-3">
      <p class="text-muted small">The active year is what the Dashboard, "New Entry" wizard, and reports default to — switch it here to work in or view a different year.</p>
      <table class="table table-sm">
        <thead><tr><th>Label</th><th>Start</th><th>End</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($financialYears as $y): ?>
          <tr>
            <td><?= e($y['label']) ?></td>
            <td><?= e($y['start_month']) ?></td>
            <td><?= e($y['end_month']) ?></td>
            <td><?= $y['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '' ?></td>
            <td>
              <?php if (!$y['is_active']): ?>
              <form method="post" action="<?= base_url('/admin/financial-year/' . $y['id'] . '/activate') ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-primary">Switch to this year</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="<?= base_url('/admin/financial-year') ?>" class="row g-2 mt-2"><?= csrf_field() ?>
        <div class="col-md-4"><input class="form-control" name="label" placeholder="Label e.g. Aug 2026 to Jul 2027" required></div>
        <div class="col-md-3"><input class="form-control" name="start_month" type="date" required></div>
        <div class="col-md-3"><input class="form-control" name="end_month" type="date" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Create + Generate Months</button></div>
      </form>
    </div>
  </div>

  <div class="tab-pane fade" id="backup">
    <div class="card p-3">
      <h6>Backup</h6>
      <a class="btn btn-outline-primary mb-3" href="<?= base_url('/admin/backup') ?>">Download Database Backup (.sql)</a>
      <h6>Restore</h6>
      <form method="post" action="<?= base_url('/admin/restore') ?>" enctype="multipart/form-data" class="row g-2"><?= csrf_field() ?>
        <div class="col-md-8"><input class="form-control" type="file" name="backup_file" accept=".sql" required></div>
        <div class="col-md-4"><button class="btn btn-warning w-100" onclick="return confirm('This will overwrite existing data. Continue?')">Restore</button></div>
      </form>
    </div>
  </div>

  <div class="tab-pane fade" id="users">
    <div class="card p-3">
      <table class="table table-sm">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge text-bg-light"><?= e($u['role']) ?></span></td>
            <td><?= $u['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
              <?php if ($u['is_active']): ?>
              <form method="post" action="<?= base_url('/admin/user/' . $u['id'] . '/deactivate') ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">Deactivate</button>
              </form>
              <?php else: ?>
              <form method="post" action="<?= base_url('/admin/user/' . $u['id'] . '/activate') ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-success">Activate</button>
              </form>
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPassword<?= $u['id'] ?>">Reset Password</button>

              <div class="modal fade" id="resetPassword<?= $u['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post" action="<?= base_url('/admin/user/' . $u['id'] . '/reset-password') ?>" class="modal-content"><?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Reset Password — <?= e($u['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <label class="form-label">New Password</label>
                      <input class="form-control" name="password" type="password" minlength="8" required>
                      <div class="form-text">At least 8 characters. Tell <?= e($u['name']) ?> the new password directly — this doesn't email it to them.</div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="<?= base_url('/admin/user') ?>" class="row g-2 mt-2"><?= csrf_field() ?>
        <div class="col-md-3"><input class="form-control" name="name" placeholder="Name" required></div>
        <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
        <div class="col-md-2"><input class="form-control" name="password" type="password" placeholder="Password" required></div>
        <div class="col-md-2">
          <select class="form-select" name="role">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Add User</button></div>
      </form>
    </div>
  </div>

  <div class="tab-pane fade" id="ai-advisor">
    <div class="card p-3">
      <h6>Provider</h6>
      <p class="text-muted small">
        One provider and API key for the whole app — used by everyone's AI Advisor chat (Settings &gt; AI Advisor
        doesn't change the computed insight cards, which don't call any API). Get a key from the provider's own
        console: <a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a> for Claude,
        <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a> for ChatGPT,
        or <a href="https://platform.deepseek.com" target="_blank" rel="noopener">platform.deepseek.com</a> for DeepSeek.
      </p>
      <form method="post" action="<?= base_url('/admin/ai-settings') ?>" class="row g-2" style="max-width: 640px"><?= csrf_field() ?>
        <div class="col-md-12">
          <label class="form-label">Provider</label>
          <select class="form-select" name="provider">
            <?php foreach ($aiProviders as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $aiSettings['provider'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">API Key</label>
          <input class="form-control" type="password" name="api_key" placeholder="<?= $aiSettings['api_key'] !== '' ? 'Currently set — leave blank to keep it' : 'Not set yet' ?>" autocomplete="off">
          <div class="form-text">Stored in the database, not shown again once saved. Leave blank when saving to keep the current key (e.g. if you're only switching provider).</div>
        </div>
        <div class="col-md-12">
          <label class="form-label">Model <span class="text-muted">(optional)</span></label>
          <input class="form-control" name="model" value="<?= e($aiSettings['model']) ?>" placeholder="Leave blank to use the provider's default model">
        </div>
        <div class="col-md-12">
          <label class="form-label">System Prompt <span class="text-muted">(optional)</span></label>
          <textarea class="form-control" name="prompt" rows="10" style="font-family: monospace; font-size: 0.85rem;"><?= e($aiSettings['prompt']) ?></textarea>
          <div class="form-text">
            This is the instruction the AI is given before every chat message — it controls its tone, rules, and what it's told not to do.
            Keep the <code>{{context}}</code> placeholder somewhere in the text — it gets replaced with the user's real numbers
            (income, spending, loan balances) each time they ask a question. Leave this blank and save to reset it to the built-in default.
          </div>
        </div>
        <div class="col-md-12 mt-2"><button class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>

</div>
