<?php

namespace App\Models;

use App\Core\Database;

class PaymentMethod
{
    public static function all(): array
    {
        return Database::connection()->query("SELECT * FROM payment_methods ORDER BY id")->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM payment_methods WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name): int
    {
        $stmt = Database::connection()->prepare("INSERT INTO payment_methods (name) VALUES (?)");
        $stmt->execute([$name]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->execute([$id]);
    }
}
