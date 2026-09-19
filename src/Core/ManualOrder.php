<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';

/**
 * Чистая нормализация и валидация формы ручного создания Заказа
 * (`FR-MGR-002`, Таск 5 Фазы 4) — без обращения к БД. Разрешение
 * артикула в `variant_id` (`findActiveVariantIdBySku()`) и проверка
 * найденного Покупателя (`findUserById()`) требуют БД — выполняются в
 * `AdminOrderController` до вызова `validateManualOrderInput()`;
 * сюда `$input['items']` приходит уже с разрешённым `variant_id`
 * (0 — артикул не найден/не указан).
 */

const MANUAL_ORDER_CUSTOMER_USER  = 'user';
const MANUAL_ORDER_CUSTOMER_GUEST = 'guest';

// Число строк позиций на форме по умолчанию, без JS — Менеджер может
// сразу заполнить до стольки позиций за один заход; кнопка «Добавить
// позицию» (admin.js) добавляет ещё сверху клонированием `<template>`.
// Здесь, а не в config.php — нужна внутри тестируемых чистых функций,
// а tests/bootstrap.php config.php не подключает (тот же приём, что
// CART_MAX_QUANTITY в Core/Cart.php).
const MANUAL_ORDER_DEFAULT_ITEM_ROWS = 3;

function normalizeManualOrderInput(array $input): array
{
    $customerMode = trim((string) ($input['customer_mode'] ?? '')) === MANUAL_ORDER_CUSTOMER_USER
        ? MANUAL_ORDER_CUSTOMER_USER
        : MANUAL_ORDER_CUSTOMER_GUEST;

    return [
        'customer_mode'      => $customerMode,
        'user_id'            => (int) ($input['user_id'] ?? 0),
        'guest_name'         => trim((string) ($input['guest_name'] ?? '')),
        'guest_phone'        => normalizePhone((string) ($input['guest_phone'] ?? '')),
        'guest_email'        => mb_strtolower(trim((string) ($input['guest_email'] ?? '')), 'UTF-8'),
        'fulfillment_method' => trim((string) ($input['fulfillment_method'] ?? '')),
        'delivery_address'   => trim((string) ($input['delivery_address'] ?? '')),
        'payment_method'     => trim((string) ($input['payment_method'] ?? '')),
        'comment'            => trim((string) ($input['comment'] ?? '')),
        'prepaid_amount'     => trim((string) ($input['prepaid_amount'] ?? '')),
        'items'              => array_values($input['items'] ?? []),
    ];
}

/**
 * `$rawItems` — сырые повторяющиеся строки `items[i][...]` из POST.
 * Пустая шаблонная строка (ни артикул, ни `variant_id` не заполнены —
 * лишний ряд формы, не тронутый Менеджером) отбрасывается здесь, до
 * похода в БД за разрешением артикула.
 */
function normalizeManualOrderItems(array $rawItems): array
{
    $items = [];

    foreach ($rawItems as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }

        $sku       = trim((string) ($rawItem['sku'] ?? ''));
        $variantId = (int) ($rawItem['variant_id'] ?? 0);

        if ($sku === '' && $variantId <= 0) {
            continue;
        }

        $items[] = [
            'sku'        => $sku,
            'variant_id' => $variantId,
            'color'      => trim((string) ($rawItem['color'] ?? '')),
            'quantity'   => max(1, (int) ($rawItem['quantity'] ?? 1)),
        ];
    }

    return $items;
}

/**
 * `$input` — результат `normalizeManualOrderInput()`. Контакты гостя
 * проверяются, только если выбран `customer_mode = guest`
 * (`MANUAL_ORDER_CUSTOMER_USER` — контакт уже подтверждён найденным
 * Покупателем, отдельно проверяется в Controller через `findUserById()`
 * и роль `customer`). Позиция считается невалидной, если список пуст
 * либо хотя бы у одной строки `variant_id = 0` (артикул не найден).
 */
function validateManualOrderInput(array $input): array
{
    $isGuest = $input['customer_mode'] === MANUAL_ORDER_CUSTOMER_GUEST;

    return [
        'guest_name'         => $isGuest && $input['guest_name'] === '',
        'guest_phone'        => $isGuest && !validatePhone($input['guest_phone']),
        'guest_email'        => $input['guest_email'] !== '' && !validateEmail($input['guest_email']),
        'fulfillment_method' => !array_key_exists($input['fulfillment_method'], FULFILLMENT_LABELS),
        'delivery_address'   => $input['fulfillment_method'] === FULFILLMENT_DELIVERY && $input['delivery_address'] === '',
        'payment_method'     => !array_key_exists($input['payment_method'], PAYMENT_METHOD_LABELS),
        'prepaid_amount'     => !preg_match('/^\d+(\.\d{1,2})?$/', $input['prepaid_amount'])
            || bccomp($input['prepaid_amount'], '0.00', 2) <= 0,
        'items' => $input['items'] === [] || in_array(0, array_column($input['items'], 'variant_id'), true),
    ];
}
