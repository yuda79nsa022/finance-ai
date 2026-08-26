<?php /** @var string|null $error */ /** @var string $return */ ?>
<div class="d-flex justify-content-center">
  <div class="card p-4" style="max-width: 380px; width: 100%;">
    <h4 class="text-center mb-3"><i class="bi bi-wallet2"></i> Finance Tracker</h4>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= base_url('/login') ?>">
      <input type="hidden" name="return" value="<?= e($return) ?>">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="email" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" required>
      </div>
      <button class="btn btn-primary w-100">Sign In</button>
    </form>
    <p class="text-muted small text-center mt-3 mb-0">
      Default admin login is set up by the installer — see docs/INSTALL.md.
    </p>
  </div>
</div>
