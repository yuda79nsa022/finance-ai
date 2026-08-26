<?php

namespace App\Models;

use App\Core\Database;

class AdvisorMessage
{
    /** All messages in a conversation, oldest first — the shape the Anthropic API expects for multi-turn context. */
    public static function forConversation(int $conversationId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM advisor_messages WHERE conversation_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    public static function add(int $conversationId, string $role, string $content): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO advisor_messages (conversation_id, role, content) VALUES (?, ?, ?)"
        );
        $stmt->execute([$conversationId, $role, $content]);
        return (int) Database::connection()->lastInsertId();
    }
}
