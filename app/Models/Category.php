<?php

namespace App\Models;

use App\Core\Database;

class Category
{
    public static function all(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM categories" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY sort_order";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Every category of one type (e.g. 'loan') — used by the Loan Tracker so it isn't limited to just Personal Loan / Bank Loan; any category an admin creates with type=loan shows up here. */
    public static function allByType(string $type, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM categories WHERE type = ?" . ($activeOnly ? " AND is_active = 1" : "") . " ORDER BY sort_order";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public static function create(string $name, string $type, int $sortOrder = 0): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO categories (name, type, sort_order) VALUES (?, ?, ?)"
        );
        $stmt->execute([$name, $type, $sortOrder]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $type): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE categories SET name = ?, type = ? WHERE id = ?"
        );
        $stmt->execute([$name, $type, $id]);
    }

    public static function deactivate(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function activate(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE categories SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Best-effort category guess from a free-text description (a bank
     * statement line, a wizard quick-add phrase) — a substring match
     * against each active category's own name, first match wins in
     * sort_order. Falls back to "Other" if nothing matches, or null if
     * there's no "Other" category at all. Deliberately simple: this is a
     * starting suggestion for the user to confirm/correct, not a claim of
     * real classification — see StatementImporter and WizardController's
     * quick-add parsing, both of which use it the same way.
     */
    public static function guessFromText(string $text): ?int
    {
        $categories = self::all();
        foreach ($categories as $cat) {
            if (stripos($text, $cat['name']) !== false) {
                return (int) $cat['id'];
            }
        }
        foreach ($categories as $cat) {
            if (strcasecmp($cat['name'], 'Other') === 0) {
                return (int) $cat['id'];
            }
        }
        return null;
    }
}
