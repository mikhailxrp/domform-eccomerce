<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Чистая нормализация и валидация формы оформления заказа — без
 * обращения к БД/сессии (`tests/bootstrap.php` не поднимает сессию),
 * по образцу `Core/Cart.php`. Пересчёт цены/остатка из БД —
 * `CheckoutController` (Таск 4).
 */

const FULFILLMENT_DELIVERY = 'delivery';
const FULFILLMENT_PICKUP   = 'pickup';

const FULFILLMENT_LABELS = [
    FULFILLMENT_DELIVERY => 'Доставка',
    FULFILLMENT_PICKUP   => 'Самовывоз',
];

const PAYMENT_CARD_ONLINE   = 'card_online';
const PAYMENT_CASH          = 'cash';
const PAYMENT_BANK_TRANSFER = 'bank_transfer';

/**
 * `BR-001` — все способы оплаты доступны при любом способе получения,
 * список не фильтруется по `fulfillment_method`.
 */
const PAYMENT_METHOD_LABELS = [
    PAYMENT_CARD_ONLINE   => 'Картой онлайн',
    PAYMENT_CASH          => 'Наличными при получении',
    PAYMENT_BANK_TRANSFER => 'Банковский перевод',
];

function normalizeCheckoutInput(array $input): array
{
    return [
        'name'               => trim((string) ($input['name'] ?? '')),
        'phone'              => normalizePhone((string) ($input['phone'] ?? '')),
        'email'              => mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8'),
        'fulfillment_method' => trim((string) ($input['fulfillment_method'] ?? '')),
        'delivery_address'   => trim((string) ($input['delivery_address'] ?? '')),
        'comment'            => trim((string) ($input['comment'] ?? '')),
        'payment_method'     => trim((string) ($input['payment_method'] ?? '')),
        'create_account'     => (string) ($input['create_account'] ?? '') === '1',
        'password'           => (string) ($input['password'] ?? ''),
    ];
}

/**
 * `$input` — результат `normalizeCheckoutInput()`. `$isAuthenticated`
 * покупатель не видит чекбокс «Создать аккаунт?» (`checkout/index.php`)
 * — поля `email`/`password` для создания аккаунта не проверяются.
 */
function validateCheckoutInput(array $input, bool $isAuthenticated): array
{
    $requiresAccountFields = $input['create_account'] && !$isAuthenticated;

    $errors = [
        'name'               => $input['name'] === '',
        'phone'              => !validatePhone($input['phone']),
        'email'              => $input['email'] !== '' && !validateEmail($input['email']),
        'fulfillment_method' => !array_key_exists($input['fulfillment_method'], FULFILLMENT_LABELS),
        'delivery_address'   => $input['fulfillment_method'] === FULFILLMENT_DELIVERY && $input['delivery_address'] === '',
        'comment'            => $input['comment'] === '',
        'payment_method'     => !array_key_exists($input['payment_method'], PAYMENT_METHOD_LABELS),
    ];

    if ($requiresAccountFields) {
        $errors['email']    = $errors['email'] || $input['email'] === '';
        $errors['password'] = !validatePassword($input['password']);
    } else {
        $errors['password'] = false;
    }

    return $errors;
}
