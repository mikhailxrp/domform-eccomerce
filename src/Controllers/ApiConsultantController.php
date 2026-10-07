<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Core/ApiAuth.php';
require_once ROOT_PATH . '/src/Core/AiChat.php';
require_once ROOT_PATH . '/src/Models/ApiClient.php';
require_once ROOT_PATH . '/src/Models/AiChatLog.php';
require_once ROOT_PATH . '/src/Services/Ai/consultant.php';

/**
 * Консультант для внешних сайтов (`POST /api/v1/consultant`) — тот же
 * `runConsultant()`, что и чат на сайте, но без сессии и CSRF: чужой
 * домен cookie не передаёт. Доступ — публичный ключ клиента +
 * проверка `Origin` по его списку доменов. Историю и счётчик вопросов
 * хранит `ai_chat_logs` (по `conversation_id`, который выдаёт сервер).
 * Лимит — `AI_API_CHAT_LIMIT` вопросов на диалог, всегда включён.
 *
 * Запрос — `application/x-www-form-urlencoded` (простой CORS-запрос,
 * без preflight): `key`, `question`, необязательный `conversation_id`.
 */
class ApiConsultantController
{
    /** Запросов в минуту на пару «клиент + IP» — как у чата на сайте. */
    private const RATE_PER_MINUTE = 15;
    private const RATE_PER_HOUR   = 60;

    public function preflight(): void
    {
        $origin = normalizeOrigin((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

        if (!isOriginAllowed($origin, getAllActiveApiOrigins())) {
            http_response_code(403);
            return;
        }

        $this->sendCors((string) $origin);
        http_response_code(204);
    }

    public function consultant(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $origin = normalizeOrigin((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $key    = (string) ($_POST['key'] ?? '');
        $client = isValidApiKeyFormat($key) ? findActiveApiClientByKey($key) : null;

        if ($client === null || !isOriginAllowed($origin, parseAllowedOrigins((string) $client['allowed_origins']))) {
            logWarning('API консультанта: доступ отклонён', [
                'origin'    => $origin ?? '',
                'has_key'   => $key !== '',
                'client_id' => $client['id'] ?? null,
            ]);
            http_response_code(403);
            echo json_encode(['error' => 'Доступ запрещён.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->sendCors((string) $origin);

        $minuteKey = 'api_consultant_' . $client['id'];
        $hourKey   = $minuteKey . '_hour';
        if (tooManyAttempts($minuteKey, self::RATE_PER_MINUTE, 60) || tooManyAttempts($hourKey, self::RATE_PER_HOUR, 3600)) {
            http_response_code(429);
            echo json_encode(['error' => 'Слишком много сообщений — подождите немного.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        hitRateLimit($minuteKey, 60);
        hitRateLimit($hourKey, 3600);

        $question = normalizeChatQuestion((string) ($_POST['question'] ?? ''));
        if ($question === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Введите вопрос.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $conversationId = (string) ($_POST['conversation_id'] ?? '');
        if (!isValidConversationId($conversationId)) {
            $conversationId = generateConversationId();
        }

        if (apiChatLimitReached(countAiChatQuestions($conversationId))) {
            echo json_encode(
                chatLimitReachedPayload(AI_API_CHAT_LIMIT) + ['conversation_id' => $conversationId],
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        $outcome = runConsultant($question, getAiChatHistory($conversationId, AI_CHAT_HISTORY_LIMIT));

        if ($outcome['status'] === 'unavailable') {
            echo json_encode(
                chatFallbackPayload($outcome['contacts']) + ['conversation_id' => $conversationId],
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        logAiChatMessage($conversationId, 'consultant', 'user', $question);
        logAiChatMessage($conversationId, 'consultant', 'assistant', $outcome['answer']);
        maybeCleanupAiChatLogs();

        $response = ['answer' => $outcome['answer'], 'conversation_id' => $conversationId];
        if ($outcome['product'] !== null) {
            $response['product']        = $outcome['product'];
            $response['product']['url'] = absoluteUrl(APP_URL, $outcome['product']['url']);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    private function sendCors(string $origin): void
    {
        foreach (corsHeadersForOrigin($origin) as $header) {
            header($header);
        }
    }
}
