<?php

namespace App\Models;

use App\Core\Database;

/** One app-wide row (id always 1) — Settings > AI Advisor. See AIClient for how provider/model/api_key are used, and AdvisorController for how the prompt template's {{context}} placeholder gets filled in. */
class AiSettings
{
    /**
     * The prompt template used when no custom prompt has been saved yet
     * (or the saved one is blank). {{context}} is replaced with the
     * user's real financial numbers (InsightsEngine::contextBrief()) at
     * send time — keep that placeholder in a custom prompt, or the
     * advisor won't see any of the user's actual figures.
     */
    public const DEFAULT_PROMPT = <<<PROMPT
        You are a financial insights assistant built into this user's personal finance tracker. You help them think through financial decisions using the real numbers from their own tracker, given to you below.

        Ground rules:
        - You are not a licensed financial advisor, and you should say so if the user is about to make a significant decision (large purchase, investment, taking on new debt) — remind them to verify anything with legal, tax, or investment implications with a licensed professional.
        - Only use the figures given to you below as fact. Do not invent numbers, balances, or transactions that aren't provided.
        - Be concise and direct. Give a clear recommendation with your reasoning, not just a list of considerations.
        - If the user asks something the data below can't answer, say so plainly rather than guessing.

        The user's current financial position:
        {{context}}
        PROMPT;

    public static function get(): array
    {
        $row = Database::connection()->query("SELECT * FROM ai_settings WHERE id = 1")->fetch();
        if (!$row) {
            $row = ['id' => 1, 'provider' => 'anthropic', 'api_key' => '', 'model' => '', 'prompt' => ''];
        }
        // Fall back to the built-in default whenever no custom prompt is saved,
        // so the view always has something sensible to display/edit and
        // AdvisorController always has something to send.
        $row['prompt'] = trim((string) ($row['prompt'] ?? '')) !== '' ? $row['prompt'] : self::DEFAULT_PROMPT;
        return $row;
    }

    public static function update(string $provider, string $apiKey, string $model, string $prompt): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO ai_settings (id, provider, api_key, model, prompt) VALUES (1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), api_key = VALUES(api_key), model = VALUES(model), prompt = VALUES(prompt)"
        );
        $stmt->execute([$provider, $apiKey, $model, $prompt]);
    }
}
