<?php

namespace App\Models;

use App\Core\Database;

/**
 * Every financial year belongs to exactly one user (user_id) — each user
 * has their own completely separate tracker, not a single shared one.
 * find() and every lookup below take the requesting user's id and filter
 * on it, so one user can never load, edit, or see another user's years
 * (or anything under them) even by guessing an id.
 */
class FinancialYear
{
    public static function allForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM financial_years WHERE user_id = ? ORDER BY start_month DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM financial_years WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function active(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM financial_years WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, string $label, string $startMonth, string $endMonth, string $currency = 'KD'): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO financial_years (user_id, label, start_month, end_month, currency_code) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $label, $startMonth, $endMonth, $currency]);
        return (int) Database::connection()->lastInsertId();
    }
}
