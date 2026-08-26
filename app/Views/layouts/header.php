<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Personal Finance Tracker (AI)</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
  <a class="navbar-brand" href="<?= base_url('/') ?>"><i class="bi bi-wallet2"></i> Finance Tracker</a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="nav">
    <ul class="navbar-nav me-auto">
      <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>">Dashboard</a></li>
      <?php if (\App\Core\Auth::check()): ?>
      <li class="nav-item"><a class="nav-link" href="<?= base_url('/wizard/start') ?>"><i class="bi bi-chat-dots"></i> New Entry</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= base_url('/advisor') ?>"><i class="bi bi-stars"></i> AI Advisor</a></li>
      <?php endif; ?>
      <?php if (\App\Core\Auth::isAdmin()): ?>
      <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin') ?>">Settings</a></li>
      <?php endif; ?>
    </ul>
    <?php if (\App\Core\Auth::check()): ?>
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" href="<?= base_url('/account') ?>"><i class="bi bi-person-circle"></i> <?= e(\App\Core\Auth::user()['name']) ?></a></li>
      <li class="nav-item"><a class="nav-link" href="<?= base_url('/logout') ?>">Logout</a></li>
    </ul>
    <?php endif; ?>
  </div>
</nav>
<main class="container-fluid py-4">
