<?php

namespace App\Core;

use App\Models\AiSettings;

/**
 * Talks to whichever AI provider is configured in Settings > AI Advisor
 * (AiSettings — one app-wide row: provider, api_key, model). Plain cURL,
 * no SDK dependency, matching the rest of this app.
 *
 * OpenAI and DeepSeek both speak the same "chat completions" shape
 * (DeepSeek's API is intentionally OpenAI-compatible), so they share one
 * code path; Anthropic's Messages API has a slightly different shape
 * (system prompt is a separate top-level field, not a message).
 */
class AIClient
{
    private const ANTHROPIC_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const DEEPSEEK_ENDPOINT = 'https://api.deepseek.com/chat/completions';

    private const DEFAULT_MODELS = [
        'anthropic' => 'claude-sonnet-4-5-20250929',
        'openai'    => 'gpt-4o',
        'deepseek'  => 'deepseek-chat',
    ];

    public const PROVIDER_LABELS = [
        'anthropic' => 'Claude (Anthropic)',
        'openai'    => 'ChatGPT (OpenAI)',
        'deepseek'  => 'DeepSeek',
    ];

    public static function isConfigured(): bool
    {
        return trim((string) AiSettings::get()['api_key']) !== '';
    }

    /**
     * @param string $systemPrompt The advisor's role/guardrails + grounding context.
     * @param array $messages [['role' => 'user'|'assistant', 'content' => string], ...] oldest first.
     * @return array{ok: bool, text?: string, error?: string}
     */
    public static function send(string $systemPrompt, array $messages): array
    {
        $settings = AiSettings::get();
        $apiKey = trim((string) $settings['api_key']);
        $provider = $settings['provider'] ?: 'anthropic';
        $model = trim((string) $settings['model']) !== '' ? $settings['model'] : self::DEFAULT_MODELS[$provider];

        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'The AI Advisor isn\'t configured yet — an admin needs to add a provider and API key in Settings > AI Advisor.'];
        }

        return match ($provider) {
            'openai'   => self::sendOpenAiCompatible(self::OPENAI_ENDPOINT, $apiKey, $model, $systemPrompt, $messages),
            'deepseek' => self::sendOpenAiCompatible(self::DEEPSEEK_ENDPOINT, $apiKey, $model, $systemPrompt, $messages),
            default    => self::sendAnthropic($apiKey, $model, $systemPrompt, $messages),
        };
    }

    private static function sendAnthropic(string $apiKey, string $model, string $systemPrompt, array $messages): array
    {
        $payload = [
            'model'      => $model,
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $messages),
        ];

        $result = self::post(self::ANTHROPIC_ENDPOINT, $payload, [
            'content-type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
        ]);
        if (!$result['ok']) {
            return $result;
        }

        $text = '';
        foreach ($result['decoded']['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        return $text !== '' ? ['ok' => true, 'text' => $text] : ['ok' => false, 'error' => 'The AI service returned an empty response.'];
    }

    /** Shared by OpenAI and DeepSeek — both implement the OpenAI chat-completions shape. */
    private static function sendOpenAiCompatible(string $endpoint, string $apiKey, string $model, string $systemPrompt, array $messages): array
    {
        $chatMessages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($messages as $m) {
            $chatMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $payload = [
            'model'    => $model,
            'messages' => $chatMessages,
        ];

        $result = self::post($endpoint, $payload, [
            'content-type: application/json',
            'authorization: Bearer ' . $apiKey,
        ]);
        if (!$result['ok']) {
            return $result;
        }

        $text = $result['decoded']['choices'][0]['message']['content'] ?? '';
        return $text !== '' ? ['ok' => true, 'text' => $text] : ['ok' => false, 'error' => 'The AI service returned an empty response.'];
    }

    private static function post(string $endpoint, array $payload, array $headers): array
    {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Could not reach the AI service: ' . $error . '. Check that this server has outbound internet access.'];
        }

        $decoded = json_decode((string) $response, true);

        if ($httpCode !== 200) {
            $apiMessage = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            return ['ok' => false, 'error' => 'AI service error: ' . $apiMessage];
        }

        return ['ok' => true, 'decoded' => $decoded];
    }
}
