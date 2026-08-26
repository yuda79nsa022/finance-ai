<?php /** @var string|null $error */ /** @var string|null $success */ ?>
<div class="d-flex justify-content-center">
  <div class="card p-4" style="max-width: 420px; width: 100%;">
    <h4 class="mb-3">My Account</h4>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success py-2">Password updated.</div><?php endif; ?>
    <form method="post" action="<?= base_url('/account/password') ?>">
      <div class="mb-3">
        <label class="form-label">Current Password</label>
        <input type="password" class="form-control" name="current_password" required>
      </div>
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" class="form-control" name="new_password" minlength="8" required>
        <div class="form-text">At least 8 characters.</div>
      </div>
      <button class="btn btn-primary w-100">Update Password</button>
    </form>
  </div>
</div>
