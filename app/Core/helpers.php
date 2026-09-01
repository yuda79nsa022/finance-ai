<?php

/**
 * Formats a number exactly like the workbook's custom format:
 *   #,##0.000 "KD";(#,##0.000) "KD";"-"
 * Zero renders as "-", negatives in parentheses.
 */
function fmt_kd($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $value = (float) $value;
    if (abs($value) < 0.0005) {
        return '-';
    }
    $formatted = number_format(abs($value), 3) . ' KD';
    return $value < 0 ? "($formatted)" : $formatted;
}

/** Formats a fraction as a percentage the way 0.0% number format does. Returns '' for null (blank cell). */
function fmt_pct($value): string
{
    if ($value === null) {
        return '';
    }
    return number_format(((float) $value) * 100, 1) . '%';
}

function base_url(string $path = ''): string
{
    $base = (require dirname(__DIR__, 2) . '/config/app.php')['base_path'];
    return $base . '/' . ltrim($path, '/');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** One CSRF token per session, generated on first use. Verified by Router::dispatch() on every POST. */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Hidden input carrying the CSRF token — drop this inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}
