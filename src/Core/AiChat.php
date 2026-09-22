<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';
require_once ROOT_PATH . '/src/Core/OrderStatus.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';
require_once ROOT_PATH . '/src/Core/functions.php';

/**
 * Консультант в чате (`FR-AI-003`, Таск 5 Фазы 9; подбор товара и
 * инфо о заказе объединены сюда из исходного `FR-AI-004` —
 * `planning-log.md`) — чистые функции: сборка системного промпта,
 * нормализация вопроса и истории, разбор структурированного ответа
 * модели, детект и форматирование инфо о заказе, запасной ответ при
 * недоступном провайдере. Никакой БД — тексты страниц, реквизиты,
 * снимок каталога и данные заказа читает `AiChatController`/
 * `Models/*` и передаёт сюда уже готовыми массивами.
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
 * `$pages` — `['delivery-payment' => body, 'return-warranty' => body,
 * 'showroom' => body]`, сырой текст `content_pages.body` (`ADR-050`,
 * правка `ADR-019`); `$contacts` — `['phone', 'whatsapp', 'address',
 * 'work_hours']` из `settings`; `$catalog` — снимок подтверждённого
 * каталога (`getConfirmedCatalogSnapshotForAi()`), уже отфильтрованный
 * по `is_active=1 AND specs_status='confirmed'` — модель не решает,
 * какие Товары существуют, только выбирает из уже проверенного списка.
 * Модель не имеет доступа к БД и не видит ничего, кроме этого промпта
 * и текста вопроса — ни имени, ни телефона Покупателя, ни данных
 * Заказа (`FR-AI-003` правило 1; инфо о заказе обрабатывается сервером
 * до вызова модели, см. `extractOrderLookupQuery()`).
 */
function buildConsultantPrompt(array $pages, array $contacts, array $catalog): string
{
    $deliveryPayment = trim((string) ($pages['delivery-payment'] ?? ''));
    $returnWarranty  = trim((string) ($pages['return-warranty'] ?? ''));
    $showroom        = trim((string) ($pages['showroom'] ?? ''));
    $phone           = trim((string) ($contacts['phone'] ?? ''));
    $whatsapp        = trim((string) ($contacts['whatsapp'] ?? ''));
    $address         = trim((string) ($contacts['address'] ?? ''));
    $workHours       = trim((string) ($contacts['work_hours'] ?? ''));
    $catalogText     = buildCatalogSnapshotText($catalog);

    return <<<PROMPT
        Ты — консультант интернет-магазина мебели «ДомФорм». Помогаешь
        Покупателю строго по документам, реквизитам и каталогу ниже —
        без общих знаний и выдумывания фактов.

        Темы, на которые отвечаешь:
        - доставка, оплата, возврат, гарантия — по документам ниже;
        - адрес шоурума, режим работы, контакты — по документу «Шоурум»
          и реквизитам ниже;
        - подбор товара по каталогу ниже — если Покупатель описывает,
          что ищет, найди РОВНО ОДИН наиболее подходящий товар из
          списка и укажи его slug в поле "product_slug"; если
          подходящего нет — верни null; никогда не предлагай товар,
          которого нет в списке ниже, и не выдумывай его название,
          цену или характеристики — их покажет сайт по slug;
        - вопрос про статус или состав своего Заказа — тебе не
          передаются данные Заказов ни при каких условиях; попроси
          номер Заказа и телефон или email, указанные при оформлении,
          сервер сам сверит их и ответит без твоего участия.

        Правила:
        - Не отвечай по общим знаниям, не придумывай факты, которых нет
          в документах или каталоге ниже.
        - Не называй имя, телефон или другие личные данные Покупателя —
          у тебя их нет.
        - Вопрос вне этих тем — вежливо предложи позвонить Менеджеру:
          телефон {$phone}, WhatsApp {$whatsapp}.
        - Отвечай кратко, на русском языке, без markdown-разметки
          внутри текста ответа.
        - Ответ — ТОЛЬКО чистый JSON без обрамления в ```, ровно такой
          формы: {"reply": "текст для покупателя", "product_slug":
          "slug-товара-или-null"}

        Документ «Доставка и оплата»:
        {$deliveryPayment}

        Документ «Возврат и гарантия»:
        {$returnWarranty}

        Документ «Шоурум»:
        {$showroom}

        Реквизиты: адрес цеха/шоурума — {$address}; режим работы —
        {$workHours}.

        Каталог товаров с подтверждёнными характеристиками (slug:
        название (категория), цена):
        {$catalogText}
        PROMPT;
}

/**
 * `$products` — строки `getConfirmedCatalogSnapshotForAi()` (`slug`,
 * `name`, `category_name`, `min_price`). Только текстовое
 * представление для промпта — сама выборка уже отфильтрована по
 * `is_active`/`specs_status` в Model.
 */
function buildCatalogSnapshotText(array $products): string
{
    if ($products === []) {
        return 'Каталог временно пуст.';
    }

    $lines = [];
    foreach ($products as $product) {
        $price     = isset($product['min_price']) ? formatPrice((string) $product['min_price']) : '';
        $lines[] = "- {$product['slug']}: {$product['name']} ({$product['category_name']}), {$price}";
    }

    return implode("\n", $lines);
}

/**
 * `$decoded` — результат `decodeAiJson()` на тексте ответа модели;
 * `$rawText` — исходный текст на случай, если модель всё же не
 * вернула валидный JSON (тогда весь текст идёт как есть, без подбора
 * товара — деградация без падения, не отказ в ответе). `product_slug`
 * `null`/`"null"`/пустая строка — товар не предложен, это штатный
 * случай (`FR-AI-003` правило подбора: «нет подходящего — верни
 * null»), не ошибка формата.
 */
function decodeConsultantReply(?array $decoded, string $rawText): array
{
    if (is_array($decoded) && isset($decoded['reply']) && is_string($decoded['reply']) && trim($decoded['reply']) !== '') {
        $slugRaw = isset($decoded['product_slug']) && is_string($decoded['product_slug']) ? trim($decoded['product_slug']) : '';
        $slug    = $slugRaw !== '' && strtolower($slugRaw) !== 'null' ? $slugRaw : null;

        return ['reply' => trim($decoded['reply']), 'product_slug' => $slug];
    }

    return ['reply' => trim($rawText), 'product_slug' => null];
}

/**
 * Детект «покупатель называет номер Заказа + контакт» до вызова
 * модели — сервер отвечает сам, без единого обращения к провайдеру
 * (`FR-AI-003`, инфо о заказе). Требует слово «заказ» в любой форме,
 * телефон ИЛИ email где-то в сообщении, и число (номер Заказа) в
 * оставшемся тексте. Ложноотрицательный результат безвреден — вопрос
 * просто уйдёт модели как обычно, и она текстом попросит уточнить;
 * поэтому регэксп сознательно не пытается покрыть все формулировки.
 */
function extractOrderLookupQuery(string $question): ?array
{
    if (preg_match('/заказ/iu', $question) !== 1) {
        return null;
    }

    $remaining = $question;
    $email     = null;
    $phone     = null;

    if (preg_match('/[^\s,;]+@[^\s,;]+\.[^\s,;]+/u', $remaining, $matches) === 1) {
        $email     = $matches[0];
        $remaining = str_replace($matches[0], ' ', $remaining);
    }

    if (preg_match('/(?:\+7|8|7)?[\s(.-]*\d{3}[\s).-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}/u', $remaining, $matches) === 1) {
        $candidate = normalizePhone($matches[0]);
        if (validatePhone($candidate)) {
            $phone     = $candidate;
            $remaining = str_replace($matches[0], ' ', $remaining);
        }
    }

    if ($email === null && $phone === null) {
        return null;
    }

    if (preg_match('/№?\s*(\d{1,10})\b/u', $remaining, $matches) !== 1) {
        return null;
    }

    return ['order_id' => (int) $matches[1], 'phone' => $phone, 'email' => $email];
}

/**
 * `$order` — строка `findOrderForChatLookup()` (только после
 * подтверждённого совпадения контакта). Показывает статус/сумму/дату/
 * способ получения — намеренно без адреса доставки и внутренних
 * комментариев (минимум достаточного, не вся строка `orders`).
 */
function buildOrderLookupReply(array $order): string
{
    $status      = orderStatusLabel((string) $order['status']);
    $total       = formatPrice((string) $order['total']);
    $date        = date('d.m.Y', strtotime((string) $order['created_at']));
    $fulfillment = FULFILLMENT_LABELS[$order['fulfillment_method']] ?? (string) $order['fulfillment_method'];

    return "Заказ №{$order['id']} от {$date}: статус «{$status}», сумма {$total}, способ получения — {$fulfillment}.";
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
