<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/AiChat.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/Setting.php';
require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Models/AiChatLog.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Services/Ai/ai.php';

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

        // Инфо о заказе — детект и ответ ДО вызова модели, без единого
        // обращения к провайдеру (`FR-AI-003`, объединено с `FR-AI-004`):
        // номер Заказа и телефон/email должны совпасть в БД, иначе
        // ответ не отдаётся вовсе (`findOrderForChatLookup()`).
        $orderLookup = extractOrderLookupQuery($question);
        if ($orderLookup !== null) {
            $contact = $orderLookup['phone'] ?? $orderLookup['email'] ?? '';
            $order   = $contact !== '' ? findOrderForChatLookup($orderLookup['order_id'], $contact) : null;

            $answer = $order !== null
                ? buildOrderLookupReply($order)
                : 'Не нашли заказ с такими данными — проверьте номер и телефон/email, указанные при оформлении, или позвоните менеджеру.';

            $this->respond($question, $questionCount, $answer);
            return;
        }

        $contacts = [
            'phone'      => setting('shop_phone'),
            'whatsapp'   => setting('shop_whatsapp_url'),
            'address'    => setting('workshop_address'),
            'work_hours' => setting('work_hours'),
        ];

        if (!aiClassAvailable(aiClassForAssistant('consultant')) || !aiAssistantEnabled('consultant')) {
            logWarning('Консультант ИИ недоступен — класс задачи не настроен или помощник выключен', ['assistant' => 'consultant']);
            echo json_encode(chatFallbackPayload($contacts), JSON_UNESCAPED_UNICODE);
            return;
        }

        $history = trimChatHistory((array) ($_SESSION['ai_consultant_history'] ?? []));

        $pages = [
            'delivery-payment' => (string) (findContentPageBySlug('delivery-payment')['body'] ?? ''),
            'return-warranty'  => (string) (findContentPageBySlug('return-warranty')['body'] ?? ''),
            'showroom'         => (string) (findContentPageBySlug('showroom')['body'] ?? ''),
        ];
        $catalog = getConfirmedCatalogSnapshotForAi(AI_CATALOG_SNAPSHOT_LIMIT);

        $messages   = [['role' => 'system', 'content' => buildConsultantPrompt($pages, $contacts, $catalog)]];
        $messages   = array_merge($messages, $history);
        $messages[] = ['role' => 'user', 'content' => $question];

        $result = aiComplete('consultant', $messages);
        if ($result === null) {
            echo json_encode(chatFallbackPayload($contacts), JSON_UNESCAPED_UNICODE);
            return;
        }

        $parsed = decodeConsultantReply(decodeAiJson($result['text']), $result['text']);

        $product = null;
        if ($parsed['product_slug'] !== null) {
            $found = findConfirmedProductForAi($parsed['product_slug']);
            if ($found !== null) {
                $description = trim((string) $found['description']);
                $product = [
                    'name'    => $found['name'],
                    'url'     => '/product/' . $found['slug'],
                    'excerpt' => mb_substr($description, 0, 160) . (mb_strlen($description) > 160 ? '…' : ''),
                    'price'   => formatPrice((string) $found['min_price']),
                ];
            }
        }

        $this->respond($question, $questionCount, $parsed['reply'], $product);
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
