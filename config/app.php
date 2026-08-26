<?php
return [
    'name'            => 'Personal Finance Tracker (AI)',
    'currency_symbol' => 'KD',
    'currency_format' => '%s KD',   // amounts render as "413.843 KD" to match the workbook's #,##0.000 "KD" format
    'decimals'        => 3,          // KD uses 3 decimal places (fils) — matches the workbook's number_format
    'timezone'        => 'Asia/Kuwait',
    'base_path' => '/finance-ai/public',
];
