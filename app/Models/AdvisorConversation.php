<?php

namespace App\Models;

use App\Core\Database;

/** Each conversation belongs to exactly one user, same ownership pattern as everything else in this app. */
class AdvisorConversation
{
    public static function allForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM advisor_conversations WHERE user_id = ? ORDER BY id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM advisor_conversations WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, string $title = 'New conversation'): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO advisor_conversations (user_id, title) VALUES (?, ?)"
        );
        $stmt->execute([$userId, $title]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateTitle(int $id, int $userId, string $title): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE advisor_conversations SET title = ? WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$title, $id, $userId]);
    }
}
