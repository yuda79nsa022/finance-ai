<?php

namespace App\Controllers;

use App\Core\AIClient;
use App\Core\Controller;
use App\Core\InsightsEngine;
use App\Models\AdvisorConversation;
use App\Models\AdvisorMessage;
use App\Models\AiSettings;
use App\Models\FinancialYear;
use App\Models\MonthModel;

/**
 * "AI Advisor" — a computed-insights panel (InsightsEngine, no API call)
 * plus a chat interface backed by whichever provider is configured in
 * Settings > AI Advisor (AIClient — Claude, ChatGPT, or DeepSeek). The
 * chat is always grounded in the same numbers the rest of the app
 * computes, handed to the model as a system-prompt brief, so it reasons
 * over real figures instead of guessing them. This is informational
 * analysis of the user's own numbers, not licensed financial advice —
 * see the disclaimer baked into the system prompt and shown in the UI.
 */
class AdvisorController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(string $conversationId = ''): void
    {
        $userId = $this->currentUserId();
        $year = FinancialYear::active($userId);
        $months = $year ? MonthModel::allForYear($year['id']) : [];
        $currentMonth = $months ? end($months) : null;

        $yearInsights = $year ? InsightsEngine::forYear($year['id']) : [];
        $monthInsights = $currentMonth ? InsightsEngine::forMonth($currentMonth['id']) : [];

        $conversations = AdvisorConversation::allForUser($userId);
        $activeConversation = null;
        $messages = [];
        if ($conversationId !== '') {
            $activeConversation = AdvisorConversation::find((int) $conversationId, $userId);
            if ($activeConversation) {
                $messages = AdvisorMessage::forConversation($activeConversation['id']);
            }
        }

        $this->view('advisor/index', [
            'yearInsights'   => $yearInsights,
            'monthInsights'  => $monthInsights,
            'currentMonth'   => $currentMonth,
            'year'           => $year,
            'conversations'  => $conversations,
            'activeConversation' => $activeConversation,
            'messages'       => $messages,
            'aiConfigured'   => AIClient::isConfigured(),
        ]);
    }

    /**
     * Handles one chat turn. Accepts conversation_id=new to start a fresh
     * thread. Returns JSON (this page's chat box uses fetch(), same
     * pattern as the Wizard) so the page doesn't have to reload per
     * message.
     */
    public function ask(): void
    {
        $userId = $this->currentUserId();
        $question = trim((string) $this->input('message', ''));
        if ($question === '') {
            $this->json(['ok' => false, 'error' => 'Type a question first.']);
            return;
        }

        $conversationIdInput = (string) $this->input('conversation_id', 'new');
        if ($conversationIdInput === 'new') {
            $title = mb_substr($question, 0, 60) . (mb_strlen($question) > 60 ? '…' : '');
            $conversationId = AdvisorConversation::create($userId, $title);
        } else {
            $conversation = AdvisorConversation::find((int) $conversationIdInput, $userId);
            if (!$conversation) {
                $this->json(['ok' => false, 'error' => 'Conversation not found.']);
                return;
            }
            $conversationId = $conversation['id'];
        }

        AdvisorMessage::add($conversationId, 'user', $question);

        $year = FinancialYear::active($userId);
        $months = $year ? MonthModel::allForYear($year['id']) : [];
        $currentMonth = $months ? end($months) : null;
        $context = InsightsEngine::contextBrief($currentMonth['id'] ?? null, $year['id'] ?? null);

        $systemPrompt = $this->systemPrompt($context);
        $history = AdvisorMessage::forConversation($conversationId);
        $apiMessages = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $history);

        $result = AIClient::send($systemPrompt, $apiMessages);

        if (!$result['ok']) {
            $this->json(['ok' => false, 'error' => $result['error'], 'conversation_id' => $conversationId]);
            return;
        }

        AdvisorMessage::add($conversationId, 'assistant', $result['text']);

        $this->json([
            'ok'              => true,
            'reply'           => $result['text'],
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * Builds the system prompt from the (admin-editable, Settings > AI
     * Advisor) template, substituting {{context}} with this user's real
     * numbers. If the saved template dropped the {{context}} placeholder
     * entirely, the numbers are appended at the end instead of silently
     * vanishing — the advisor should never lose its grounding data.
     */
    private function systemPrompt(string $context): string
    {
        $template = AiSettings::get()['prompt'];
        if (str_contains($template, '{{context}}')) {
            return str_replace('{{context}}', $context, $template);
        }
        return $template . "\n\nThe user's current financial position:\n" . $context;
    }
}
