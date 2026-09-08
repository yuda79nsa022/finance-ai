<?php

require dirname(__DIR__) . '/vendor/autoload_fallback.php'; // simple PSR-4 autoloader (no Composer required to run)
require dirname(__DIR__) . '/app/Core/helpers.php';

use App\Core\Router;
use App\Controllers\DashboardController;
use App\Controllers\MonthController;
use App\Controllers\WizardController;
use App\Controllers\AdminController;
use App\Controllers\ReportController;
use App\Controllers\AuthController;
use App\Controllers\AdvisorController;

$appConfig = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Started once, globally, before routing: every page (including the login
// form) needs a session available to read/generate its CSRF token, and
// Router::dispatch() needs $_SESSION available to verify one on every POST.
\App\Core\Auth::start();

// Security response headers, set on every response (including 404s/errors)
// since this happens before routing. script-src/style-src need 'unsafe-inline'
// because the app's views use plain inline <script> blocks and inline
// style="" attributes (no build step / nonce plumbing exists) — everything
// else is locked down to same-origin plus the one CDN host the layout
// actually loads from (cdnjs.cloudflare.com, for Bootstrap/Chart.js/icons).
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
    . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
    . "font-src 'self' https://cdnjs.cloudflare.com; "
    . "img-src 'self' data:; "
    . "connect-src 'self'; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none';");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), payment=(), usb=()');

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/account', [AuthController::class, 'showAccount']);
$router->post('/account/password', [AuthController::class, 'changePassword']);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);

// Month page + CRUD
$router->get('/month/{id}', [MonthController::class, 'show']);
$router->post('/month/{id}/income', [MonthController::class, 'addIncome']);
$router->post('/month/{id}/income/{entryId}/update', [MonthController::class, 'updateIncome']);
$router->post('/month/{id}/income/{entryId}/delete', [MonthController::class, 'deleteIncome']);
$router->post('/month/{id}/fixed-cost', [MonthController::class, 'addFixedCost']);
$router->post('/month/{id}/fixed-cost/{entryId}/update', [MonthController::class, 'updateFixedCost']);
$router->post('/month/{id}/fixed-cost/{entryId}/delete', [MonthController::class, 'deleteFixedCost']);
$router->post('/month/{id}/expense', [MonthController::class, 'addExpense']);
$router->post('/month/{id}/expense/{entryId}/update', [MonthController::class, 'updateExpense']);
$router->post('/month/{id}/expense/{entryId}/delete', [MonthController::class, 'deleteExpense']);
$router->post('/month/{id}/expense/scan', [MonthController::class, 'scanReceipt']);
$router->get('/month/{id}/expense/{entryId}/receipt', [MonthController::class, 'receiptImage']);
$router->post('/month/{id}/expense/import', [MonthController::class, 'importStatement']);
$router->post('/month/{id}/expense/import/confirm', [MonthController::class, 'confirmImportStatement']);
$router->post('/month/{id}/loan', [MonthController::class, 'addLoan']);
$router->post('/month/{id}/loan/{lenderId}/update', [MonthController::class, 'updateLoan']);
$router->post('/month/{id}/loan/{lenderId}/delete', [MonthController::class, 'deleteLoan']);
$router->post('/month/{id}/lock', [MonthController::class, 'lock']);
$router->post('/month/{id}/unlock', [MonthController::class, 'unlock']);
$router->post('/month/{id}/duplicate', [MonthController::class, 'duplicate']);

// Wizard
$router->get('/wizard/start', [WizardController::class, 'start']);
$router->post('/wizard/answer', [WizardController::class, 'answer']);

// Admin
$router->get('/admin', [AdminController::class, 'index']);
$router->post('/admin/category', [AdminController::class, 'addCategory']);
$router->post('/admin/category/{id}/update', [AdminController::class, 'updateCategory']);
$router->post('/admin/category/{id}/deactivate', [AdminController::class, 'deactivateCategory']);
$router->post('/admin/category/{id}/activate', [AdminController::class, 'activateCategory']);
$router->post('/admin/payment-method', [AdminController::class, 'addPaymentMethod']);
$router->post('/admin/payment-method/{id}/delete', [AdminController::class, 'deletePaymentMethod']);
$router->post('/admin/lender', [AdminController::class, 'addLender']);
$router->post('/admin/lender/{id}/deactivate', [AdminController::class, 'deactivateLender']);
$router->post('/admin/lender/{id}/activate', [AdminController::class, 'activateLender']);
$router->post('/admin/financial-year', [AdminController::class, 'createFinancialYear']);
$router->post('/admin/financial-year/{id}/activate', [AdminController::class, 'activateFinancialYear']);
$router->get('/admin/backup', [AdminController::class, 'backup']);
$router->post('/admin/restore', [AdminController::class, 'restore']);
$router->post('/admin/user', [AdminController::class, 'addUser']);
$router->post('/admin/user/{id}/deactivate', [AdminController::class, 'deactivateUser']);
$router->post('/admin/user/{id}/activate', [AdminController::class, 'activateUser']);
$router->post('/admin/user/{id}/reset-password', [AdminController::class, 'resetUserPassword']);
$router->post('/admin/ai-settings', [AdminController::class, 'updateAiSettings']);

// AI Advisor
$router->get('/advisor', [AdvisorController::class, 'index']);
$router->get('/advisor/{conversationId}', [AdvisorController::class, 'index']);
$router->post('/advisor/ask', [AdvisorController::class, 'ask']);

// Reports
$router->get('/reports/month/{id}/pdf', [ReportController::class, 'monthlyPdf']);
$router->get('/reports/month/{id}/csv', [ReportController::class, 'monthCsv']);
$router->get('/reports/annual/{id}/pdf', [ReportController::class, 'annualPdf']);
$router->get('/reports/annual/{id}/xlsx', [ReportController::class, 'annualXlsx']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
