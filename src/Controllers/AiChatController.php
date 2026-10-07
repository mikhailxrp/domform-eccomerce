<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Core/AiChat.php';
require_once ROOT_PATH . '/src/Models/AiChatLog.php';
require_once ROOT_PATH . '/src/Services/Ai/consultant.php';

/**
 * Консультант в чате (`FR-AI-003`) — доступен Гостю и Покупателю без
 * входа, поэтому без `requireRole()`. Подбор товара и инфо о заказе
 * объединены сюда из исходного `FR-AI-004` (`planning-log.md`, ADR
 * по явному запросу владельца продукта) — отдельного помощника-
 * «Подбора диалогом» больше нет.
 *
 * `AI_CHAT_LIMIT_ENABLED` (`LIMIT_REQUESTS` в `.env`) — демо-лимит на
 * число вопросов в одном диалоге, не часть `FR-AI-003`: ограничение
 * показа для демо/портфолио-стенда, добавлено отдельным запросом
 * (`dev-log.md`, 22.09.2026).
 */
class AiChatController
{
    public function consultant(): void
    {
        requireCsrf();

        header('Content-Type: application/json; charset=utf-8');

        if (tooManyAttempts('ai_chat', 15, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Слишком много сообщений — подождите минуту.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        hitRateLimit('ai_chat');

        $question = normalizeChatQuestion((string) input('question', ''));
        if ($question === '') {
            echo json_encode(['error' => 'Введите вопрос.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $questionCount = (int) ($_SESSION['ai_consultant_question_count'] ?? 0);
        if (chatLimitReached($questionCount, AI_CHAT_LIMIT_ENABLED)) {
            echo json_encode(chatLimitReachedPayload(), JSON_UNESCAPED_UNICODE);
            return;
        }

        $history = trimChatHistory((array) ($_SESSION['ai_consultant_history'] ?? []));
        $outcome = runConsultant($question, $history);

        if ($outcome['status'] === 'unavailable') {
            echo json_encode(chatFallbackPayload($outcome['contacts']), JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->respond($question, $questionCount, $outcome['answer'], $outcome['product']);
    }

    /**
     * Общая бухгалтерия ответа — приветствие демо-режима на первом
     * сообщении, лог переписки, история сессии, счётчик вопросов.
     * Общая для ветки инфо о заказе и обычного ответа модели, чтобы не
     * дублировать её в трёх местах.
     */
    private function respond(string $question, int $questionCount, string $answer, ?array $product = null): void
    {
        if ($questionCount === 0 && AI_CHAT_LIMIT_ENABLED) {
            $answer = buildDemoGreeting(env('AI_YANDEX_MODEL', '')) . "\n\n" . $answer;
        }

        $conversationId = $this->conversationId();

        logAiChatMessage($conversationId, 'consultant', 'user', $question);
        logAiChatMessage($conversationId, 'consultant', 'assistant', $answer);
        maybeCleanupAiChatLogs();

        $history   = trimChatHistory((array) ($_SESSION['ai_consultant_history'] ?? []));
        $history[] = ['role' => 'user', 'content' => $question];
        $history[] = ['role' => 'assistant', 'content' => $answer];
        $_SESSION['ai_consultant_history']        = trimChatHistory($history);
        $_SESSION['ai_consultant_question_count'] = $questionCount + 1;

        $response = ['answer' => $answer];
        if ($product !== null) {
            $response['product'] = $product;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Один `conversation_id` на сессию (`CHAR(32)` в `ai_chat_logs`) —
     * диалог не связывается с личным кабинетом и не переживает новую
     * сессию (`FR-AI-003` правило 7).
     */
    private function conversationId(): string
    {
        if (empty($_SESSION['ai_consultant_conversation_id'])) {
            $_SESSION['ai_consultant_conversation_id'] = bin2hex(random_bytes(16));
        }

        return (string) $_SESSION['ai_consultant_conversation_id'];
    }
}
