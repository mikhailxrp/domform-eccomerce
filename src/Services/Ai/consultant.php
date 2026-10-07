<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/AiChat.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/Setting.php';
require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once __DIR__ . '/ai.php';

/**
 * Единое ядро Консультанта — его вызывают и чат на сайте
 * (`AiChatController`), и API для внешних сайтов
 * (`ApiConsultantController`), чтобы логика ответа не расходилась.
 * Состояние диалога (история, счётчик, лог) остаётся у вызывающего:
 * на сайте оно в `$_SESSION`, в API — в `ai_chat_logs`.
 *
 * Возвращает `['status' => 'ok', 'answer' => string, 'product' =>
 * ?array]` либо `['status' => 'unavailable', 'contacts' => array]`
 * (провайдер выключен/недоступен — вызывающий показывает контакты).
 * `product['url']` — относительный (`/product/{slug}`).
 *
 * @param list<array{role: string, content: string}> $history
 */
function runConsultant(string $question, array $history): array
{
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

        return ['status' => 'ok', 'answer' => $answer, 'product' => null];
    }

    $contacts = [
        'phone'      => setting('shop_phone'),
        'whatsapp'   => setting('shop_whatsapp_url'),
        'address'    => setting('workshop_address'),
        'work_hours' => setting('work_hours'),
    ];

    if (!aiClassAvailable(aiClassForAssistant('consultant')) || !aiAssistantEnabled('consultant')) {
        logWarning('Консультант ИИ недоступен — класс задачи не настроен или помощник выключен', ['assistant' => 'consultant']);
        return ['status' => 'unavailable', 'contacts' => $contacts];
    }

    $pages = [
        'delivery-payment' => (string) (findContentPageBySlug('delivery-payment')['body'] ?? ''),
        'return-warranty'  => (string) (findContentPageBySlug('return-warranty')['body'] ?? ''),
        'showroom'         => (string) (findContentPageBySlug('showroom')['body'] ?? ''),
    ];
    $catalog = getConfirmedCatalogSnapshotForAi(AI_CATALOG_SNAPSHOT_LIMIT);

    $messages   = [['role' => 'system', 'content' => buildConsultantPrompt($pages, $contacts, $catalog)]];
    $messages   = array_merge($messages, trimChatHistory($history));
    $messages[] = ['role' => 'user', 'content' => $question];

    $result = aiComplete('consultant', $messages);
    if ($result === null) {
        return ['status' => 'unavailable', 'contacts' => $contacts];
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

    return ['status' => 'ok', 'answer' => $parsed['reply'], 'product' => $product];
}
