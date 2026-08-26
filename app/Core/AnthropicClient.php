<?php

namespace App\Core;

/**
 * Minimal client for the Claude Messages API (api.anthropic.com), using
 * plain cURL — no SDK/Composer dependency, matching the rest of this
 * app's "no framework" approach. Configuration (API key, model) lives in
 * config/ai.php.
 */
class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public static function isConfigured(): bool
    {
        $cfg = self::config();
        return trim((string) $cfg['api_key']) !== '';
    }

    /**
     * @param string $systemPrompt The advisor's role/guardrails + grounding context (see InsightsEngine::contextBrief()).
     * @param array $messages [['role' => 'user'|'assistant', 'content' => string], ...] — the conversation so far, oldest first.
     * @return array{ok: bool, text?: string, error?: string}
     */
    public static function send(string $systemPrompt, array $messages): array
    {
        $cfg = self::config();
        if (trim((string) $cfg['api_key']) === '') {
            return ['ok' => false, 'error' => 'The AI Advisor isn\'t configured yet — an Anthropic API key needs to be added to config/ai.php.'];
        }

        $payload = [
            'model'      => $cfg['model'],
            'max_tokens' => $cfg['max_tokens'] ?? 1024,
            'system'     => $systemPrompt,
            'messages'   => array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $messages),
        ];

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $cfg['api_key'],
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT    => 60,
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

        $text = '';
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        if ($text === '') {
            return ['ok' => false, 'error' => 'The AI service returned an empty response.'];
        }

        return ['ok' => true, 'text' => $text];
    }

    private static function config(): array
    {
        return require dirname(__DIR__, 2) . '/config/ai.php';
    }
}
