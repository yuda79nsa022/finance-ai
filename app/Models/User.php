<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function all(): array
    {
        return Database::connection()->query(
            "SELECT id, name, email, role, is_active, created_at FROM users ORDER BY id"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Normalizes an email for storage/lookup: trims stray whitespace (easy
     * to pick up when pasting an address from Gmail/Outlook, which often
     * copies a trailing space) and lowercases it, so "Sarah@Gmail.com " and
     * "sarah@gmail.com" are always treated as the same account. This must be
     * applied identically in create() and findByEmail(), or a user created
     * with unnoticed whitespace/case will never match at login even though
     * the password is correct.
     */
    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([self::normalizeEmail($email)]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $password, string $role = 'user'): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([trim($name), self::normalizeEmail($email), password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public static function updatePassword(int $id, string $newPassword): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public static function setRole(int $id, string $role): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
}
