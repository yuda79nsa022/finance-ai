<?php

namespace App\Models;

use App\Core\Database;

/**
 * Each lender belongs to exactly one user (user_id) — every lookup below
 * is scoped to the requesting user's own list, so e.g. two different
 * users can each have their own lender named "Ali" without colliding,
 * and one user can never see or modify another user's lenders even by
 * guessing/tampering an id.
 */
class Lender
{
    public static function all(bool $activeOnly = true, int $userId = 0): array
    {
        $sql = "SELECT * FROM lenders WHERE user_id = ?" . ($activeOnly ? " AND is_active = 1" : "") . " ORDER BY sort_order";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM lenders WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function findByName(string $name, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM lenders WHERE name = ? AND user_id = ? AND is_active = 1");
        $stmt->execute([$name, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Same lookup as findByName() but ignores is_active — used so re-adding a previously removed loan reactivates it instead of hitting the UNIQUE(user_id, name) constraint. */
    public static function findByNameAny(string $name, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM lenders WHERE name = ? AND user_id = ?");
        $stmt->execute([$name, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @param int|null $categoryId Which loan-type category this lender's payments fall under
     *   (Personal Loan, Bank Loan, Car Loan, Mortgage, Credit Card, or any custom category with
     *   type='loan'). Null falls back to whatever the Type-based mapping picks (Bank Loan/Personal Loan)
     *   wherever a category is actually needed — see MonthController::categoryIdForLoan().
     */
    public static function create(int $userId, string $name, float $initialBalance = 0, string $type = 'person', int $sortOrder = 0, ?int $categoryId = null): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO lenders (user_id, name, type, category_id, initial_balance, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $name, $type, $categoryId, $initialBalance, $sortOrder]);
        return (int) Database::connection()->lastInsertId();
    }

    /** Edits the lender's own record (name, type, loan category, opening balance). Does NOT rename matching Fixed Cost rows — call FixedCostModel::renameLoanItem() first if the name changed, or the loan ledger's name-matching breaks. Scoped to $userId so one user can't edit another's lender by id. */
    public static function update(int $id, int $userId, string $name, string $type, float $initialBalance, ?int $categoryId = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE lenders SET name = ?, type = ?, category_id = ?, initial_balance = ? WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$name, $type, $categoryId, $initialBalance, $id, $userId]);
    }

    /**
     * Retire a lender once their loan is paid off (or otherwise no longer
     * tracked). Doesn't delete anything — Lender::all() defaults to active-only,
     * so an inactive lender simply stops being included the next time the loan
     * ledger is recalculated for a new month. Past months keep their history.
     * Scoped to $userId so one user can't deactivate another's lender by id.
     */
    public static function deactivate(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare("UPDATE lenders SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    public static function activate(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare("UPDATE lenders SET is_active = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }
}
