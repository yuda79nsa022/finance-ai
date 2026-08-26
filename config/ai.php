<?php

/**
 * Settings for the AI Advisor feature (Core/AnthropicClient + AdvisorController).
 *
 * 'api_key' — an Anthropic API key from https://console.anthropic.com
 *   (Settings > API Keys > Create Key). Paste it below between the quotes.
 *   Until this is filled in, the Advisor page still shows the computed
 *   insight cards (no API needed for those) but the chat box will show a
 *   "not configured yet" message instead of calling out to Claude.
 *
 * 'model' — which Claude model to call. The default below is a good
 *   general-purpose choice; you can swap in any model id listed at
 *   https://docs.claude.com/en/docs/about-claude/models if you want a
 *   different cost/quality tradeoff.
 */
return [
    'api_key' => '',
    'model'   => 'claude-sonnet-4-5-20250929',
    'max_tokens' => 1024,
];
