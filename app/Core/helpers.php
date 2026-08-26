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
