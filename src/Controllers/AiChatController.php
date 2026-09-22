<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/AiChat.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/Setting.php';
require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Models/AiChatLog.php';
require_once ROOT_PATH . '/src/Services/Ai/ai.php';

/**
 * Консультант в чате (`FR-AI-003`) — доступен Гостю и Покупателю без
 * входа, поэтому без `requireRole()`. Виджет ещё не построен (Таск 6)
 * — эндпоинт проверяется напрямую (`curl`/будущий `fetch`).
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

        $contacts = [
            'phone'    => setting('shop_phone'),
            'whatsapp' => setting('shop_whatsapp_url'),
        ];

        if (!aiClassAvailable(aiClassForAssistant('consultant'))) {
            logWarning('Консультант ИИ недоступен — класс задачи не настроен', ['assistant' => 'consultant']);
            echo json_encode(chatFallbackPayload($contacts), JSON_UNESCAPED_UNICODE);
            return;
        }

        $conversationId = $this->conversationId();
        $history        = trimChatHistory((array) ($_SESSION['ai_consultant_history'] ?? []));

        $pages = [
            'delivery-payment' => (string) (findContentPageBySlug('delivery-payment')['body'] ?? ''),
            'return-warranty'  => (string) (findContentPageBySlug('return-warranty')['body'] ?? ''),
        ];

        $messages   = [['role' => 'system', 'content' => buildConsultantPrompt($pages, $contacts)]];
        $messages   = array_merge($messages, $history);
        $messages[] = ['role' => 'user', 'content' => $question];

        $result = aiComplete('consultant', $messages);
        if ($result === null) {
            echo json_encode(chatFallbackPayload($contacts), JSON_UNESCAPED_UNICODE);
            return;
        }

        $answer = trim($result['text']);

        if ($questionCount === 0 && AI_CHAT_LIMIT_ENABLED) {
            $answer = buildDemoGreeting(env('AI_YANDEX_MODEL', '')) . "\n\n" . $answer;
        }

        logAiChatMessage($conversationId, 'consultant', 'user', $question);
        logAiChatMessage($conversationId, 'consultant', 'assistant', $answer);
        maybeCleanupAiChatLogs();

        $history[] = ['role' => 'user', 'content' => $question];
        $history[] = ['role' => 'assistant', 'content' => $answer];
        $_SESSION['ai_consultant_history']        = trimChatHistory($history);
        $_SESSION['ai_consultant_question_count'] = $questionCount + 1;

        echo json_encode(['answer' => $answer], JSON_UNESCAPED_UNICODE);
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
