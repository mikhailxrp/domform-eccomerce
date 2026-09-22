<?php

declare(strict_types=1);

/**
 * Консультант в чате (`FR-AI-003`, Таск 5 Фазы 9) — чистые функции:
 * сборка системного промпта, нормализация вопроса и истории,
 * запасной ответ при недоступном провайдере. Никакой БД — тексты
 * страниц и реквизиты читает `AiChatController` и передаёт сюда уже
 * готовыми массивами.
 */

/**
 * Ограничивает стоимость одного запроса (в токенах), не литературную
 * длину вопроса.
 */
const AI_MAX_QUESTION_LENGTH = 500;

/**
 * Сколько последних реплик диалога уходит в промпт вместе с новым
 * вопросом — иначе долгий диалог линейно наращивает стоимость каждого
 * следующего запроса.
 */
const AI_CHAT_HISTORY_LIMIT = 10;

/**
 * `$pages` — `['delivery-payment' => body, 'return-warranty' => body]`,
 * сырой текст `content_pages.body` (`ADR-050`, правка `ADR-019`); `$contacts`
 * — `['phone' => ..., 'whatsapp' => ...]` из `settings`. Модель не имеет
 * доступа к БД и не видит ничего, кроме этого промпта и текста вопроса —
 * ни имени, ни телефона Покупателя, ни данных Заказа (`FR-AI-003`
 * правило 1).
 */
function buildConsultantPrompt(array $pages, array $contacts): string
{
    $deliveryPayment = trim((string) ($pages['delivery-payment'] ?? ''));
    $returnWarranty  = trim((string) ($pages['return-warranty'] ?? ''));
    $phone           = trim((string) ($contacts['phone'] ?? ''));
    $whatsapp        = trim((string) ($contacts['whatsapp'] ?? ''));

    return <<<PROMPT
        Ты — консультант интернет-магазина мебели «ДомФорм». Отвечай
        Покупателю ТОЛЬКО по темам доставки, оплаты, возврата и гарантии
        — строго по тексту документов ниже.

        Правила:
        - Не отвечай по общим знаниям и не придумывай факты, которых нет
          в тексте документов ниже.
        - Не определяй статус, дату или состав конкретного Заказа — тебе
          такие данные не передаются и не могут быть переданы.
        - Не называй имя, телефон или другие личные данные Покупателя —
          у тебя их нет.
        - Вопрос вне доставки/оплаты/возврата/гарантии, или требующий
          данных конкретного Заказа — не пытайся ответить по существу,
          вежливо предложи позвонить Менеджеру: телефон {$phone},
          WhatsApp {$whatsapp}.
        - Отвечай кратко, на русском языке, без markdown-разметки.

        Документ «Доставка и оплата»:
        {$deliveryPayment}

        Документ «Возврат и гарантия»:
        {$returnWarranty}
        PROMPT;
}

/**
 * `AI_MAX_QUESTION_LENGTH` — защита от аномально длинного вопроса
 * (стоимость запроса растёт с числом токенов), не литературное
 * ограничение.
 */
function normalizeChatQuestion(string $question): string
{
    return mb_substr(trim($question), 0, AI_MAX_QUESTION_LENGTH);
}

/**
 * `$history` — список `['role' => 'user'|'assistant', 'content' =>
 * string]` из `$_SESSION`, в хронологическом порядке. Оставляет только
 * последние `AI_CHAT_HISTORY_LIMIT` реплик — ограничивает размер (и
 * стоимость) запроса к провайдеру, не память сессии целиком.
 */
function trimChatHistory(array $history): array
{
    if (count($history) <= AI_CHAT_HISTORY_LIMIT) {
        return $history;
    }

    return array_slice($history, -AI_CHAT_HISTORY_LIMIT);
}

/**
 * Ответ при недоступном классе `user_input` (ключ не настроен,
 * провайдер отключён) — виджет чата (Таск 6) показывает контакты
 * вместо ответа, покупка при этом не блокируется (`AC-06`).
 */
function chatFallbackPayload(array $contacts): array
{
    return [
        'unavailable' => true,
        'phone'       => (string) ($contacts['phone'] ?? ''),
        'whatsapp'    => (string) ($contacts['whatsapp'] ?? ''),
    ];
}

/**
 * Демо-лимит числа вопросов на один диалог — включается переменной
 * `LIMIT_REQUESTS` в `.env` (`AI_CHAT_LIMIT_ENABLED`,
 * `config/config.php`). Ограничение показа для демо/портфолио-стенда,
 * не часть `FR-AI-003` — отдельный счётчик от лимита частоты
 * `tooManyAttempts('ai_chat', ...)`, который защищает от скриптовой
 * накрутки по времени, а не от общего числа вопросов в диалоге.
 */
const AI_CHAT_DEMO_LIMIT = 7;

/**
 * `$questionCount` — число уже отвеченных вопросов в текущем диалоге
 * (`$_SESSION`), до текущего запроса. При `$limitEnabled === false`
 * лимита нет вовсе — переменная `LIMIT_REQUESTS` не задана или `false`.
 */
function chatLimitReached(int $questionCount, bool $limitEnabled): bool
{
    return $limitEnabled && $questionCount >= AI_CHAT_DEMO_LIMIT;
}

/**
 * Отдельная заглушка, без вызова провайдера — не путать с
 * `chatFallbackPayload()` (временная недоступность провайдера):
 * лимит демо-версии исчерпан осознанно, это не сбой.
 */
function chatLimitReachedPayload(): array
{
    return [
        'limit_reached' => true,
        'message'       => 'Лимит демо-версии консультанта исчерпан ('
            . AI_CHAT_DEMO_LIMIT . ' из ' . AI_CHAT_DEMO_LIMIT . ' запросов в этом диалоге).',
    ];
}

/**
 * Приветствие первого сообщения диалога (только пока `LIMIT_REQUESTS`
 * включён) — `$model` передаётся вызывающим кодом (`env('AI_YANDEX_MODEL')`),
 * эта функция самого `.env` не читает и остаётся чистой/тестируемой.
 */
function buildDemoGreeting(string $model): string
{
    return 'Здравствуйте! Это демо-версия консультанта, работающая на '
        . "реальной модели {$model}. Действует лимит — " . AI_CHAT_DEMO_LIMIT
        . ' запросов в диалоге для одного пользователя. Спасибо.';
}
