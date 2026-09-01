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

    /**
     * Every new year is created active, and — this is the important part —
     * every other year of this user's is explicitly deactivated at the same
     * time. Without that second step, is_active would default to 1 (see
     * schema.sql) on every row ever created and active() would only look
     * correct by coincidence of its ORDER BY id DESC tie-break, while the
     * data underneath had every year simultaneously "active" and no way to
     * switch back to an older one (see activate()).
     */
    public static function create(int $userId, string $label, string $startMonth, string $endMonth, string $currency = 'KD'): int
    {
        $db = Database::connection();
        $db->prepare("INSERT INTO financial_years (user_id, label, start_month, end_month, currency_code) VALUES (?, ?, ?, ?, ?)")
            ->execute([$userId, $label, $startMonth, $endMonth, $currency]);
        $id = (int) $db->lastInsertId();

        $db->prepare("UPDATE financial_years SET is_active = 0 WHERE user_id = ? AND id != ?")
            ->execute([$userId, $id]);

        return $id;
    }

    /** Switches which of $userId's financial years is active — the one true "current" year the dashboard, wizard, and reports default to. Scoped to $userId so one user can never activate (or even discover the id of) another user's year. */
    public static function activate(int $id, int $userId): bool
    {
        $db = Database::connection();
        $target = self::find($id, $userId);
        if (!$target) {
            return false;
        }
        $db->prepare("UPDATE financial_years SET is_active = 0 WHERE user_id = ?")->execute([$userId]);
        $db->prepare("UPDATE financial_years SET is_active = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        return true;
    }
}
