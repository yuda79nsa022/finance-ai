<?php

/**
 * If `composer install` has been run, vendor/autoload.php exists and already
 * autoloads the App\ namespace (see composer.json) plus Dompdf/PhpSpreadsheet.
 * If not, this fallback registers a minimal PSR-4 autoloader for App\ only,
 * so the core app (dashboard, month pages, wizard, admin) still runs without
 * Composer. PDF/Excel export will report a friendly message until you run
 * `composer install` (see docs/INSTALL.md).
 */
$composerAutoload = __DIR__ . '/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
    return;
}

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});
